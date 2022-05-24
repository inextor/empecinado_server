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
		$this->is_debug = false;
		$extra_join = '';
		$extra_sort = array();

		if( !empty( $_GET['category_type'] )  || !empty($_GET['category_name']) )
		{
			$extra_join = 'JOIN category ON category.id = item.category_id ';
			$extra_join.= (!empty( $_GET['category_type'] ) ? ' AND category.type = "'.$_GET['category_type'].'"' : '');
			$extra_sort = array('category.name');
		}

		$constraints = array();

		if( !empty( $_GET['category_name'] ) )
		{
			$constraints=array('category.name LIKE "%'.DBTable::escape( $_GET['category_name'] ).'%"');
		}

		$this->is_debug = true;
		return $this->genericGet("item",$constraints,$extra_join,$extra_sort);
	}


	function getInfo($item_array)
	{
		$item_props = ArrayUtils::getItemsProperties( $item_array, 'id','category_id');
		$store_constraint = empty( $_GET['store_id'] ) ? '' : ' AND store_id = "'.DBTable::escape( $_GET['store_id']) .'" ';

		$stock_ids_sql 	= 'SELECT MAX(id) AS max_id,store_id,item_id FROM stock_record
			WHERE  stock_record.item_id IN ('.DBTable::escapeArrayValues( $item_props['id'] ).')
			'.$store_constraint.'
			GROUP BY item_id, store_id';


		$stock_record_array = array();

		if( !empty( $item_props['id'] ) )
		{
			$stock_ids_array = DBTable::getArrayFromQuery( $stock_ids_sql, 'max_id' );
			$stock_ids	= array_keys( $stock_ids_array );
			$stock_record_array = stock_record::searchGroupByIndex(array('id'=>$stock_ids),false,'item_id');
		}

		$category_array = category::search(array('id'=>$item_props['category_id']),false,'id');

		$result = array();

		foreach( $item_array as $item )
		{
			$total	= 0;
			$stocks = isset( $stock_record_array[ $item['id'] ] )
				? $stock_record_array[ $item['id'] ]
				: array();

			foreach( $stocks as $s )
			{
				$total += $s['qty'];
			}

			$result[] = array(
				'item'=>$item,
				'category'=>$category_array[ $item['category_id'] ],
				'records'=> $stocks,
				'total'=>$total
			);
		}

		return $result;
	}
}
$l = new Service();
$l->execute();
