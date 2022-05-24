<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\DBTable;
use \akou\ArrayUtils;
use \akou\ValidationException;
use \akou\LoggableException;
use \akou\SystemException;

class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		$extra_constraints = array();

		if( $_GET['store_id'] )
		{
			$extra_constraints[] = '( from_store_id = "'.DBTable::escape($_GET['store_id']).'" OR to_store_id = "'.DBTable::escape($_GET['store_id']).'" )';
		}

		$extra_sort = array();
		$extra_fields = array();
		$extra_joins = '';

		return $this->genericGet('shipping',$extra_constraints,$extra_joins,$extra_sort,$extra_fields);
	}


	function getInfo($shipping_array)
	{
		return app::getShippingInfo($shipping_array);
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
		DBTable::autocommit(false );

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
		$shipping_props = shipping::getAllPropertiesExcept('id','created','updated','created_by_user_id','updated_by_user_id','status');
		$shipping_item_props = shipping_item::getAllPropertiesExcept('id','shipping_id','created','updated','received_qty','shrinkage_qty');

		$result = array();
		foreach($array as $shipping_info )
		{
			$shipping = new shipping();
			$shipping->assignFromArray( $shipping_info['shipping'], $shipping_props );

			$shipping_item_array = ArrayUtils::getItemsProperty($shipping_info['items'],'shipping_item');
			$this->debug('shipping_item_array',$shipping_item_array );

			if( !empty( $shipping_info['shipping']['requisition_id']) )
			{
				$requisition = requisition::get( $shipping_info['shipping']['requisition_id'] );

				if( !$requisition )
					throw new ValidationException('El id de la requisicion no es valido');

				$requisition->status = 'SHIPPED';

				if( !$requisition->update('status') )
				{
					error_log( $requisition->getLastQuery());
					throw new SystemException('ocurrio un errror por favor intentar mas terde'.$requisition->getError());
				}
			}

			if( empty( $shipping_item_array) )
				throw  new ValidationException('por favor agregar al menos 1 item');

			if( !$shipping->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intente de nuevo'.$shipping->getError() );
			}

			foreach($shipping_item_array  as $si )
			{
				$shipping_item = new shipping_item();
				$shipping_item->assignFromArray( $si, $shipping_item_props );
				$shipping_item->shipping_id = $shipping->id;

				$sql = 'SELECT SUM(qty) AS total
					FROM  box_content
					WHERE box_id = "'.DBTable::escape( $shipping_item->box_id ).'"
					GROUP BY box_id';

				$sql_result  = DBTable::query($sql);

				if( $sql_result && ($row = $sql_result->fetch_array() ) )
				{
					$shipping_item->qty = $row['total'];
				}

				if(!$shipping_item->insertDb())
				{
					throw new SystemException('Ocurrio un error por favor intente de nuevo'.$shipping_item->getError() );
				}
			}
			$result[] = $shipping->toArray();
		}

		return $result;
	}

	function batchUpdate($array)
	{
		$shipping_props = shipping::getAllPropertiesExcept('id','created','updated','created_by_user_id','updated_by_user_id','status');
		$shipping_item_props = shipping_item::getAllPropertiesExcept('id','shipping_id','created','updated','shrinkage_qty');

		$result = array();
		foreach($array as $shipping_info )
		{

			if( empty( $shipping_info['shipping']['id']) )
			{
				throw new ValidationException('El id no puede estar vacio');
			}
			$shipping = shipping::get( $shipping_info['shipping']['id'] );

			if( $shipping->status !== 'PENDING' )
				throw new ValidationException('El envio ya fue procesado');

			$shipping->assignFromArray( $shipping_info['shipping'], $shipping_props );

			$shipping_item_array = ArrayUtils::getItemsProperty($shipping_info['items'],'shipping_item');

			if( empty( $shipping_item_array) )
				throw  new ValidationException('por favor agregar al menos 1 item');

			if( !$shipping->update($shipping_props) )
			{
				throw new SystemException('Ocurrio un error por favor intente de nuevo'.$shipping->getError() );
			}


			$shipping_item_ids = array();

			$sql = 'DELETE FROM shipping_item WHERE shipping_id = "'.DBTable::escape( $shipping->id ).'"';
			DBTable::query( $sql );

			foreach($shipping_item_array  as $si )
			{
				$shipping_item = new shipping_item();
				$shipping_item->assignFromArray( $si, $shipping_item_props );
				$shipping_item->shipping_id = $shipping->id;
				$this->debug('si',$shipping_item);

				if(!$shipping_item->insertDb())
				{
					throw new SystemException('Ocurrio un error por favor intente de nuevo'.$shipping_item->getError() );
				}
			}

			$result[] = $shipping->toArray();
		}

		return $result;
	}
}
$l = new Service();
$l->execute();
