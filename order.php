<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\DBTable;
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

		return $this->genericGet("order");
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

	function batchUpdate($array)
	{

		$props = order::getAllPropertiesExcept('store_id','delivery_status','paid_status','total','subtotal','tax','amount_paid','created_by_user_id','updated_by_user_id','created','updated');
		$this->debug('Props',$props);

		$results = array();

		foreach($array as $order_data )
		{
			$order = order::get( $order_data['id'] );
			$order->assignFromArray($order_data,$props);


			if( !$order->update( $props ) )
			{
				throw new SystemException('Ocurrio un error por favor intetar mas tarde. '.$order->getError());
			}
			error_log($order->getLastQuery());

			$results[]= $order->toArray();
		}
		return $results;
	}

	function batchInsert($array)
	{
		$user = app::getUserFromSession();

		$this->debug('array',$array);
		return $this->genericInsert($array, 'order', $optional_values=array('tota'=>0,'subtotal'=>0,'tax'=>0), $system_values=array('cashier_user_id'=>$user->id));

		//$props = order::getAllPropertiesExcept('store_id','total','subtotal','tax','amount_paid','created_by_user_id','updated_by_user_id','created','updated');

		//foreach($array as $order )
		//{
		//	$order = order::get( $order['id'] );
		//	$order->assignFromArray($order,$props);

		//	if( !$order->update( $props ) )
		//	{
		//		throw new SystemException('Ocurrio un error por favor intetar mas tarde. '.$order->getError());
		//	}
		//}
	}
	/*

	function delete()
	{
		try
		{
			return $this->genericDelete("order");
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
