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


class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		return $this->genericGet("serial_number_record");
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
		$results = array();
		$user = app::getUserFromSession();

		foreach($array as $params )
		{
			$except = array('id','created','updated','tiempo_creacion','tiempo_actualizacion','updated_by_user_id','created_by_user_id');
			$properties = serial_number_record::getAllPropertiesExcept( $except );

			$serial_number_record = new serial_number_record();
			$serial_number_record->assignFromArray( $params, $properties );
			$serial_number_record->unsetEmptyValues( DBTable::UNSET_BLANKS );

			if( $user )
			{
				$user_array = array('updated_by_user_id'=>$user->id,'created_by_user_id'=>$user->id);
				$serial_number_record->assignFromArray( $user_array );
			}

			if( !empty( $serial_number_record->order_item_id ) )
			{
				$order_item	= order_item::get( $serial_number_record->order_item_id );

				if( !$order_item )
					throw new ValidationException('Ocurrio un error por favor intentar mas tarde');

				$count = 0;
				$previous_serial_number_records = serial_number_record::search(array('order_item_id'=>$order_item->id),false);

				//$this->debug('order_item',$order_item->toArray());
				//$this->debug('previus', $previous_serial_number_records);

				foreach($previous_serial_number_records as $psnr )
				{
					$count += $psnr['qty'];
				}

				$count += $serial_number_record->qty;

				if( $count > $order_item->qty )
				{
					throw new ValidationException('La cantidad de Codigos asignados es mayor que la cantidad en la orden');
				}
			}

			if( !$serial_number_record->insert() )
			{
				throw new SystemException('Ocurrio un error por favor intente más tarde. '.$serial_number_record->_conn->error);
			}

			$start_code = intVal( $serial_number_record->start_code );
			for($i=0;$i<$params['qty'];$i++)
			{
				$serial_number = new serial_number();
				$serial_number->code = $start_code+$i;
				$serial_number->type_item_id = $serial_number_record->type_item_id;
				$serial_number->serial_number_record_id = $serial_number_record->id;
				$serial_number->created_by_user_id = $user->id;
				$serial_number->updated_by_user_id = $user->id;
				$serial_number->assigned_to_user_id = $serial_number_record->assigned_to_user_id;

				if( !$serial_number->insert() )
				{
					if( $serial_number->_conn->errno == 1062 )
						throw new SystemException('El codigo de la serial_number "'.$serial_number->code.'" ya existe');

					throw new SystemException('Ocurrio un error por favor intente más tarde. '.$serial_number->_conn->error);
				}
			}

			//No se si va aqui
			//app::addSerialNumberRecord($serial_number_record, $user );

			$results [] = $serial_number_record->toArray();
		}

		return $results;
	}
}
$l = new Service();
$l->execute();
