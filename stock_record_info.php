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

		$extra_joins = '';

		if( !empty( $_GET['shipping_id'] ) )
		{
			$extra_joins = ' JOIN shipping_item ON shipping_item.id = stock_record.shipping_item_id
				AND shipping_item.shipping_id = "'.DBTable::escape($_GET['shipping_id']).'"';
		}

		$extra_constraints = array();

		return $this->genericGet("stock_record",$extra_constraints,$extra_joins);
	}

	function getInfo($stock_record_array)
	{
		$sr_props	= ArrayUtils::getItemsProperties($stock_record_array,'item_id');
		$item_array	= item::search(array('id'=>$sr_props['item_id']),false, 'id');
		$category_ids	= ArrayUtils::getItemsProperty($item_array, 'category_id');
		$category_array	= category::search(array('id'=>$category_ids ), false, 'id');

		$result = array();

		foreach( $stock_record_array as $stock_record)
		{
			$item		= $item_array[ $stock_record['item_id'] ];
			$category	= $item['category_id'] ? $category_array[ $item['category_id'] ] : null;
			$result[]	= array
			(
				'stock_record'	=> $stock_record,
				'category'	=> $category,
				'item'	=> $item
			);
		}

		return $result;
	}
}
$l = new Service();
$l->execute();
