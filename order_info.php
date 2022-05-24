<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\ArrayUtils;
use \akou\DBTable;
use \akou\SystemException;
use \akou\LoggableException;
use \akou\ValidationException;
use \akou\SessionException;


class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();
		$user = app::getUserFromSession();


		if( !$user )
			return $this->sendStatus( 401 )->json(array("error"=>'Por favor iniciar sesion'));

		//$this->is_debug = true;

		return $this->genericGet("order");
	}

	function getInfo($order_array)
	{
		$order_props		= ArrayUtils::getItemsProperties($order_array,'id','store_id','client_user_id','cashier_user_id','shipping_address_id','billing_address_id','price_type_id');

		$store_array		= store::search(array('id'=>$order_props['store_id']), false, 'id');
		$price_type_array	= price_type::search(array('id'=>$order_props['price_type_id']),false,'id');

		$person_in_charge_user_ids = ArrayUtils::getItemsProperty( $store_array, 'person_in_charge_user_id');
		$user_ids			= array_merge($order_props['client_user_id'],$order_props['cashier_user_id'], $person_in_charge_user_ids);
		$user_array			= user::search(array('id'=>$user_ids), false, 'id');
		$order_item_array	= order_item::search(array('order_id'=>$order_props['id'],'status'=>'ACTIVE'), false, 'id');
		$order_item_props	= ArrayUtils::getItemsProperties($order_item_array, 'id','item_id' );

		$item_array			= item::search(array('id'=>$order_item_props['item_id']),false,'id');

		$category_ids		= ArrayUtils::getItemsProperty($item_array, 'category_id' );
		$category_array		= category::search(array('id'=>$category_ids),false,'id');

		$address_array		=  address::search(array('id'=>array_merge($order_props['shipping_address_id'],$order_props['billing_address_id'])),false,'id');
		$order_item_grouped	= ArrayUtils::groupByIndex( $order_item_array, 'order_id');
		$result = array();

		$snra_search = array('order_id'=>$order_props['id']);
		$serial_number_array = serial_number::searchGroupByIndex($snra_search,false,'order_id');
		//$sql = serial_number_record::getSearchSql( $snra_search );
		//error_log('Busqueda_marbetes'. $sql );

		foreach( $order_array as $order )
		{
			$client_user			= empty( $order['client_user_id'] ) ? null : $user_array[$order['client_user_id']];
			$cashier_user  	= empty( $order['cashier_user_id'] ) ? null : $user_array[$order['cashier_user_id']];

			if( $client_user )
				$client_user['password'] = '';

			if( $cashier_user )
				$cashier_user['password'] = '';

			$order_items	= empty( $order_item_grouped[ $order['id'] ] ) ? array() : $order_item_grouped[ $order['id'] ];

			$items_result = array();

			$order_records = $serial_number_array[ $order['id'] ] ?? array();

			foreach($order_items as $order_item )
			{
				$item			= $item_array[ $order_item['item_id'] ];
				$category		= $category_array[ $item['category_id'] ];
				$records		= ArrayUtils::filterByValue($order_records,'item_id',$item['id']);
				//isset( $serial_number_record_array[$order_item['id'] ] ) ? $serial_number_record_array[$order_item['id'] ] : array();

				$items_result[]=array
				(
					'order_item'=>$order_item,
					'item'=>$item,
					'category'=>$category,
					'serial_numbers'=>$records
				);
			}

			$order_info = array
			(
				'client'	=> $client_user,
				'cashier'	=> $cashier_user,
				'items'		=> $items_result,
				'order'		=> $order,
				'store'		=> $store_array[ $order['store_id'] ],
				'price_type'	=> $price_type_array[ $order['price_type_id'] ],
			);

			if( !empty( $order_info['store']['person_in_charge_user_id'] ) )
			{
				$order_info['person_in_charge'] = $user_array[ $order_info['store']['person_in_charge_user_id']];
			}

			if( $order['shipping_address_id'] )
				$order_info['shipping_address'] = $address_array[ $order['shipping_address_id'] ];

			if( $order['billing_address_id'] )
				$order_info['billing_address'] = $address_array[ $order['billing_address_id'] ];

			$result[] = $order_info;
		}

		return $result;
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
			$info		= $this->getInfo( $result );
			DBTable::commit();
			return $this->sendStatus( 200 )->json( $is_assoc ? $info[0] : $info );
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
			$info = $this->getInfo( $result );

			DBTable::commit();
			return $this->sendStatus( 200 )->json( $is_assoc ? $info[0] : $info );
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

	function batchUpdate($array)
	{
		$user = app::getUserFromSession();

		if( empty($user) )
			throw new SessionException("Por favor iniciar sesion");


		$props = order::getAllPropertiesExcept('delivery_status','paid_status','total','subtotal','tax','amount_paid','created_by_user_id','updated_by_user_id','created','updated');
		$this->debug('Props',$props);

		$results = array();

		foreach($array as $order_data )
		{
			$order = order::get( $order_data['order']['id'] );

			//if( $order->delivery_status == 'DELIVERED')
			//{
			//	throw new ValidationException('La orden ya se entrego');
			//}

			$order->assignFromArray($order_data['order'],$props);

			if( !$order->update( $props ) )
			{
				throw new SystemException('Ocurrio un error por favor intetar mas tarde. '.$order->getError());
			}

			$ids = array();

			foreach($order_data['items'] as $oi )
			{
				if( empty( $oi['order_item']['is_free_of_charge'] ) )
				{
					throw new ValidationException('Es cortesia no puede estar vacio');
				}

				$order_item = new order_item();
				$order_item->assignFromArray( $oi['order_item'] );
				$order_item->order_id = $order->id;

				$oi = app::saveOrderItem( $order_item->toArray() );
				$ids[] = $oi->id;
			}

			$deleted_items = order_item::search(array('order_id'=>$order->id,'id'.DBTable::DIFFERENT_THAN_SYMBOL=>$ids),true);

			foreach($deleted_items as $order_item )
			{
				$order_item->status = 'DELETED';

				if( !$order_item->update() )
				{
					throw new SystemException('Ocurrio un error por favor intentar mas tarde');
				}
			}

			app::updateOrderTotal( $order->id );

			$results[]= $order->toArray();
		}
		return $results;
	}


	function batchInsert($array)
	{
		$user = app::getUserFromSession();

		if( empty($user) )
			throw new SessionException("Por favor iniciar sesion");

		$optional_values=array('tota'=>0,'subtotal'=>0,'tax'=>0);
		$system_values=array('cashier_user_id'=>$user->id, 'created_by_user_id'=>$user->id,'updated_by_user_id'=>$user->id);

		$except = array('id','created','updated','tiempo_creacion','tiempo_actualizacion','updated_by_user_id','created_by_user_id');
		$properties = order::getAllPropertiesExcept( $except );

		foreach( $array as $order_info_params )
		{
			$order = new order();
			$order->assignFromArray( $optional_values );
			$order->assignFromArray( $order_info_params['order'], $properties );
			$order->assignFromArray( $system_values );
			$order->unsetEmptyValues( DBTable::UNSET_BLANKS );

			if( !$order->insert() )
			{
				throw new ValidationException('An error Ocurred please try again later',$order->_conn->error );
			}

			foreach($order_info_params['items'] as $oi )
			{
				if( empty( $oi['order_item']['is_free_of_charge'] ) )
				{
					throw new ValidationException('Es cortesia no puede estar vacio');
				}

				$order_item = new order_item();
				$order_item->assignFromArray( $oi['order_item'] );
				$order_item->order_id = $order->id;

				app::saveOrderItem( $order_item->toArray() );
			}

			$results [] = $order->toArray();
		}

		return $results;
	}
}

$l = new Service();
$l->execute();
