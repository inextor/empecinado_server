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

		return $this->genericGet("stocktake");
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
			$result		= $this->batchInsert( $is_assoc	? array($params) : $params );
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
			$result		= $this->batchUpdate( $is_assoc	? array($params) : $params );
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

		if( !$user )
			throw new ValidationException('Por favor iniciar sesion');

		$result = array();
		$props	= stocktake::getAllPropertiesExcept('id','created','updated','created_by_user_id','updated_by_user_id');

		foreach($array as $params)
		{
			$stocktake = new stocktake();
			$stocktake->assignFromArray($params, $props );

			if( !$stocktake->insertDb() )
				throw new ValidationException('Ocurrio un error por favor intentar mas tarde .'.$stocktake->getError());

			$pallet_array			= pallet::search(array('store_id'=>$stocktake->store_id),true,'id');
			$pallet_content_array	= pallet_content::search(array('pallet_id'=>array_keys($pallet_array)),true,'box_id'); //CHECK
			$box_array		= box::search(array('store_id'=>$stocktake->store_id),true,'id');
			$box_ids 		= array_merge(array_keys($pallet_content_array),array_keys($box_array));
			$box_content_array	= box_content::search(array('box_id'=>$box_ids),true,'id');


			foreach( $pallet_array as $pallet)
			{
				$stocktake_item = new stocktake_item();
				$stocktake_item->stocktake_id = $stocktake->id;
				$stocktake_item->pallet_id = $pallet->id;

				if( !$stocktake_item->insertDb() )
				{
					throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$stocktake_item->getError());
				}
			}

			foreach($box_ids as $box_id)
			{
				$stocktake_item = new stocktake_item();
				$stocktake_item->box_id = $box_id;
				$stocktake_item->stocktake_id = $stocktake->id;

				if( !$stocktake_item->insert() )
				{
					throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$stocktake_item->getError());
				}
			}

			foreach($box_content_array as $box_content )
			{
				$stocktake_item = new stocktake_item();
				$stocktake_item->box_content_id	= $box_content->id;
				$stocktake_item->stocktake_id			= $stocktake->id;
				$stocktake_item->creation_qty			= $box_content->qty;

				if( !$stocktake_item->insert() )
				{
					throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$stocktake_item->getError());
				}
			}

			$stock_records_ids_sql	= 'SELECT MAX(id) AS max_id,store_id,item_id FROM stock_record
				WHERE stock_record.store_id = "'.DBTable::escape( $stocktake->store_id ).'"
				GROUP BY item_id, store_id';

			$stock_records_ids	= DBTable::getArrayFromQuery( $stock_records_ids_sql, 'max_id');
			$stock_record_array	= stock_record::search(array('id'=>array_keys( $stock_records_ids )),true);

			foreach( $stock_record_array as $stock_record)
			{
				if( $stock_record->qty == 0 )
					continue;

				$stocktake_item = new stocktake_item();
				$stocktake_item->stocktake_id	= $stocktake->id;
				$stocktake_item->item_id		= $stock_record->item_id;
				$stocktake_item->creation_qty	= $stock_record->qty;

				if( !$stocktake_item->insert() )
				{
					throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$stocktake_item->getError());
				}
			}

			$stocktake->load(true);
			$result[] = $stocktake->toArray();
		}


		return $result;
	}

	function batchUpdate($array)
	{
		$insert_with_ids = false;
		return $this->genericUpdate($array, "stocktake", $insert_with_ids );
	}

	/*
	function delete()
	{
		try
		{
			return $this->genericDelete("stocktake");
		}
		catch(LoggableException $e)
		{
			DBTable::rollback();
			return $this->sendStatus( $e->code )->json(array("error"=>$e->getMessage()));
		}
		catch(Exception $e)
		{
			DBTable::rollback();
			return $this->sendStatus( 500 )->json(array("error"=>$e->getMessage()));
		}
	}
	*/
}
$l = new Service();
$l->execute();
