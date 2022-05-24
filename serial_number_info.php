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

		$extra_constraints = array();
		$extra_joins = '';

		if( !empty( $_GET['client_name'] ) )
		{
			//function genericGet($table_name,$extra_constraints=array(),$extra_joins='',$extra_sort=array())
			$extra_joins = 'JOIN user ON user.type = "CLIENT" AND serial_number.assigned_to_user_id = user.id';
			$extra_constraints[] = 'user.name LIKE "'.trim( DBTable::escape( $_GET['client_name'] ) ).'%"';
		}

		$this->is_debug = true;
		return $this->genericGet("serial_number",$extra_constraints, $extra_joins);
	}

	function getInfo($serial_number_array)
	{
		$props	 		= ArrayUtils::getItemsProperties($serial_number_array,'item_id','box_id','assigned_to_user_id','order_id');
		$user_array		= user::search(array('id'=>$props['assigned_to_user_id']),true,'id');
		$item_array		= item::search(array('id'=>$props['item_id']),true,'id');
		$category_ids	= ArrayUtils::getItemsProperty($item_array,'category_id');
		$category_array	= category::search(array('id'=>$category_ids),false,'id');
		$result			= array();

		$user_props = user::getAllPropertiesExcept('password');

		foreach($serial_number_array as $serial_number)
		{
			$category 	= null;
			$item		= $item_array[ $serial_number['item_id'] ];
			if( $item )
			{
				$category	= empty( $category_array[ $item->category_id ] ) ? NULL : $category_array[ $item->category_id ];
			}

			$user		= empty( $serial_number['assigned_to_user_id'] ) ? NULL : $user_array[ $serial_number['assigned_to_user_id'] ];

			$result[] = array
			(
				'serial_number'	=> $serial_number,
				'user'			=> $user ? $user->toArray($user_props): NULL,
				'item'			=> $item ? $item->toArray(): null,
				'category'		=> $category?? NULL,
		//		'order'			=> $order_array[ $serial_number['order_id'] ]??NULL
			);
		}

		return $result;
	}
}
$l = new Service();
$l->execute();
