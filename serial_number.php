<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\Utils;
use \akou\DBTable;
use \akou\RestController;
use \akou\ArrayUtils;
use \akou\ValidationException;
use \akou\LoggableException;
use \akou\SystemException;
use \akou\SessionException;

/*
 *  Los registros no deben de editarse directamente
 *
 */


class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		return $this->genericGet("serial_number");
	}

	function post()
	{
		$this->setAllowHeader();
		$params = $this->getMethodParams();
		app::connect();
		DBTable::autocommit(false );

		try
		{
			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			$is_assoc	= $this->isAssociativeArray( $params );
			$result		= $this->batchInsert( $is_assoc  ? array($params) : $params );
			DBTable::commit();
			return $this->sendStatus( 200 )->json( $is_assoc ? $result[0] : $result );
		}
		catch(LoggableException $e)
		{
			DBTable::rollback();
			return $this->sendStatus( $e->code )->json(array("error"=>$e->getMessage()));
		}
		catch(\Exception $e)
		{
			DBTable::rollback();
			return $this->sendStatus( 500 )->json(array("error"=>$e->getMessage()));
		}
	}

	function batchInsert($array)
	{
		$user = app::getUserFromSession();
		$except = array('created_by_user_id','updated_by_user_id','id','created','updated');

		$props= serial_number::getAllPropertiesExcept( $except );


		foreach($array as $params )
		{
			$serial_number = serial_number::searchFirst(array('type_item_id'=>$params['type_item_id'],'code'=>$params['code']));

			if( $serial_number )
			{
				if( $serial_number->item_id  )
				{
					throw new ValidationException('El Codigo "'.$params['code'].'" Ya fue asignado');
				}
				else
				{
					$serial_number->item_id = $params['item_id'];
					$serial_number->updated_by_user_id = $user->id;
					$serial_number->store_id = $params['store_id'];

					if( !$serial_number->update('item_id','store_id','updated_by_user_id') )
					{
						throw new SystemException('ocurrio un error al actualizar el codigo"'.$params['code'].'" '.$serial_number->getError());
					}
					$result[] = $serial_number->toArray();
				}
			}
			else
			{
				$serial_number = new serial_number();
				$serial_number->assignFromArray( $params, $props );
				$serial_number->created_by_user_id = $user->id;
				$serial_number->updated_by_user_id = $user->id;

				if( !$serial_number->insert() )
				{
					throw new SystemException('ocurrio un error al actualizar el codigo"'.$params['code'].'" '.$serial_number->getError());
				}
				$serial_number->load( true );
				$result = $serial_number->toArray();
			}
		}
	}
}
$l = new Service();
$l->execute();
