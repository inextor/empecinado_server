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

		return $this->genericGet("stocktake_item");
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

	function put()
	{
		$this->setAllowHeader();
		$params = $this->getMethodParams();
		app::connect();
		DBTable::autocommit( false );

		try
		{
			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			$is_assoc	= $this->isAssociativeArray( $params );
			$result		= $this->batchUpdate( $is_assoc  ? array($params) : $params );
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
		return $this->batchUpdate($array);
	}

	function batchUpdate($array)
	{
		$result = array();
		$user = app::getUserFromSession();

		foreach($array as $params)
		{
			if( empty( $params['stocktake_id'] ) )
				throw new ValidationException('El id de la toma de inventario no puede estar vacio');

			if( !empty( $params['item_id'] ) )
			{
				$stocktake_item =  new stocktake_item();
				$stocktake_item->assignFromArray($params,'item_id','stocktake_id','qty');
				$stocktake_item->created_by_user_id = $user->id;
				$stocktake_item->updated_by_user_id = $user->id;
				$stocktake_item->insertDb();
				throw new ValidationException('Revisar implementacion');
				return;
			}
			else if( !empty( $params['pallet_id'] ) )
			{
				$stocktake_item = stocktake_item::searchFirst(array('pallet_id'=>$params['pallet_id'],'stocktake_id'=>$params['stocktake_id']));

				if( $stocktake_item !== null )
				{
					throw new ValidationException('La tarima con id "'.$params['pallet_id'].'" ya ha sido inventariada' );
				}
				$stocktake_item =  new stocktake_item();
				$stocktake_item->assignFromArray($params,'stocktake_id','pallet_id');
				$stocktake_item->created_by_user_id = $user->id;
				$stocktake_item->updated_by_user_id = $user->id;

				$this->debug('stocktake_id',$stocktake_item->toArray());
				if( !$stocktake_item->insertDb() )
				{
					throw new SystemException('Ocurrio un error al guardar la informacion por favor intente de nuevo.'.$stocktake_item->getError());
				}

				$result[] = $stocktake_item->toArray();
			}
			else if( !empty( $params['box_id'] ) )
			{
				$pallet_content = pallet_content::searchFirst(array('box_id'=>$params['box_id'],'status'=>"ACTIVE"));

				if( $pallet_content !== null )
				{
					throw new ValidationException('La caja esta dentro de la tarima "'.$pallet_content->pallet_id.'"');
				}

				$stocktake_item = stocktake_item::searchFirst(array('box_id'=>$params['box_id'],'stocktake_id'=>$params['stocktake_id']));

				if( $stocktake_item !== null )
				{
					throw new ValidationException('La caja con id "'.$params['box_id'].'" ya ha sido inventariada' );
				}

				$stocktake_item =  new stocktake_item();
				$stocktake_item->assignFromArray($params,'stocktake_id','box_id');
				$stocktake_item->created_by_user_id = $user->id;
				$stocktake_item->updated_by_user_id = $user->id;

				if( !$stocktake_item->insertDb() )
				{
					throw new SystemException('Ocurrio un error al guardar la informacion por favor intente de nuevo.'.$stocktake_item->getError());
				}

				$result[] = $stocktake_item->toArray();
			}
		}
	}
}
$l = new Service();
$l->execute();
