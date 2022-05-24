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

		$stock_record_array = stock_record::search(array('box_content_id'.DBTable::NULL_SYMBOL=>true, 'box_id'.DBTable::NOT_NULL_SYMBOL=>true),true);

		foreach($stock_record_array as $stock_record )
		{
			$box_content_array = box_content::search(array('box_id'=>$stock_record->box_id,'item_id'=>$stock_record->item_id));
			if( count( $box_content_array ) == 1 )
			{
				$stock_record->box_content_id = $box_content_array[0]->id;
				if( !$stock_record->update('box_content_id') )
				{
					error_log('Error al actualizar '.$stock_record->getLastQuery() );
				}
			}
			else
			{
				error_log('quien sabe que paso');
			}
		}

		$stock_record_array = stock_record::search(array('palle_id'.DBTable::NULL_SYMBOL=>true, 'shipping_item_id'.DBTable::NOT_NULL_SYMBOL=>true),true);

		foreach($stock_record_array as $stock_record )
		{
			$shipping_item = shipping_item::get( $stock_record->shipping_item_id );
			if( $shipping_item->pallet_id )
			{
				$stock_record->pallet_id= $shipping_item->pallet_id;

				if( !$stock_record->update('pallet_id') )
				{
					error_log('Error al actualizar '.$stock_record->getLastQuery() );
				}
			}
			else
			{
				error_log('quien sabe que paso');
			}
		}


		return $this->sendStatus(200)->json(true);
	}
}
$l = new Service();
$l->execute();
