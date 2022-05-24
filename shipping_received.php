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

class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		return $this->genericGet("shipping");
	}

	function getInfo($shipping_info_array)
	{
		$shipping_props			= ArrayUtils::getItemsProperties($shipping_info_array,'id','store_id');
		$shipping_item_array	= shipping_item::search(array('shipping_id'=>$shipping_props['id']),false,'id');
		$stock_record_array		= stock_record::search(array('shipping_item_id'=>array_keys($shipping_item_array)),false);
		$item_ids				= ArrayUtils::getItemsProperty($stock_record_array,'item_id');
		$item_array				= item::search(array('id'=>$item_ids ),false,'id');
		$category_ids			= ArrayUtils::getItemsProperty( $item_array, 'category_id' );
		$category_array			= category::search(array('id'=>$category_ids),false,'id');

		$result = array();

		$shipping_item_grouped	= ArrayUtils::groupByIndex($shipping_item_array, 'shipping_id');

		foreach( $shipping_info_array as $shipping)
		{
			$si_array	= $shipping_item_grouped[ $shipping['id'] ];

			$shipping_item_info_array = array();

			foreach( $si_array  as $shipping_item )
			{
				$shipping_item_info_array[] = array
				(

				);
			}


			$result[] = array
			(
				'shipping'	=> $shipping,
				'items'		=> $shippint_item_info_array
			);
		}
	}
}

$l = new Service();
$l->execute();
