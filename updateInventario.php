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

		//$user = app::getUserFromSession();
		$user = user::get(2);

		if( !$user )
			throw new ValidationException('Por favor iniciar sesion');

		$qty_by_item_id = array();
		$box_content_ids = array();

		$pallet_array			= pallet::search(array('store_id'=>$_GET['store_id']),true,'id');
		$pallet_content_array	= pallet_content::search(array('pallet_id'=>array_keys($pallet_array)),true,'box_id'); //CHECK
		$box_array		= box::search(array('store_id'=>$_GET['store_id']),true,'id');
		$box_ids 		= array_merge(array_keys($pallet_content_array),array_keys($box_array));
		$box_content_array	= box_content::search(array('box_id'=>$box_ids),true,'id');

		foreach($box_content_array as $box_content )
		{
			//Por posibles duplicado no deberia entrar nunca aqui pero pues lo pongo
			if( $box_content_ids[ $box_content->id ] )
				return;

			$box_content_ids[ $box_content->id ] = true;

			if( !isset( $qty_by_item_id[ $box_content->item_id ] ) )
				$qty_by_item_id[ $box_content->item_id ] = 0;

			$qty_by_item_id[ $box_content->item_id ] += $box_content->qty;
		}

		foreach($qty_by_item_id as $item_id => $qty )
		{
			$stock_record = app::getLastStockRecord($_GET['store_id'], $item_id);

			if( $stock_record->qty < $qty )
			{
				app::addStock($item_id,$_GET['store_id'],$user->id,$qty-$stock_record->qty,'Se ajusto en base al contenido de las cajas');
			}
			else if( $stock_record->qty > $qty )
			{
				app::removeStock($item_id,$_GET['store_id'],$user->id,$stock_record->qty-$qty,'Se ajusto en base al contenido de las cajas');
			}
			//Si es igual no movemos nada
		}

		return $this->sendStatus(200)->json( $qty_by_item_id );
	}
}
$l = new Service();
$l->execute();
