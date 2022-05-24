<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\DBTable;

class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();
		$this->is_debug = false;

		$stock_ids 	= 'SELECT MAX(id) AS max_id,store_id,item_id FROM stock_record GROUP BY item_id, store_id';
		$ids_array	= DBTable::getArrayFromQuery($stock_ids, 'max_id');

		if( empty( $ids_array ) )
			return $this->sendStatus(200)->json(array('total'=>0,'data'=>array()));


		$pagination	= $this->getPagination();

		$constraints = array();
		$constraints[] = 'stock_record.id IN ('.implode(',', array_keys($ids_array)).')';
		$constraints[] = 'stock_record.store_id = "'.DBTable::escape($_GET['store_id']).'"';

		if( !empty( $_GET['name'] ) )
		{
			$escaped_name = DBTable::escape( trim( $_GET['name'] ) );
			$constraints[] = 'category.name LIKE "'.$escaped_name.'%" OR item.name LIKE "'.$escaped_name.'%" ';
		}


		if( !empty( $_GET['type'] ) )
		{
			$constraints[] = 'category.type = "'.$_GET['type'].'"';
		}

		$constraints_str =  join(' AND ',$constraints );

		$sort_string = '';

		$sql	= 'SELECT SQL_CALC_FOUND_ROWS '.stock_record::getUniqSelect().',
			'.item::getUniqSelect().',
			'.category::getUniqSelect().'
			FROM stock_record
			JOIN item ON stock_record.item_id = item.id
			JOIN category ON item.category_id = category.id
			WHERE '.$constraints_str.' '.$sort_string.'
			LIMIT '.$pagination->limit.'
			OFFSET '.$pagination->offset;

		$res = DBTable::query( $sql );
		$result = array();

		$types_info =  DBTable::getFieldsInfo( $res );

		while($data = $res->fetch_assoc() )
		{
			$row = DBTable::getRowWithDataTypes( $data, $types_info );

			$item = item::createFromUniqArray($row);
			$stock_record = stock_record::createFromUniqArray( $row );
			$category = category::createFromUniqArray( $row );

			$result[] = array(
				'item'=> $item->toArray(),
				'stock_record'=>$stock_record->toArray(),
				'category'=>$category->toArray()
			);
		}
		$total  = DBTable::getTotalRows();

		return $this->sendStatus(200)->json(array('total'=>$total, 'data'=>$result ));
	}
}
$l = new Service();
$l->execute();
