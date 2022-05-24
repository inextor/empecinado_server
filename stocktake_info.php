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

		return $this->genericGet("stocktake");
	}

	function getInfo($stocktake_array)
	{
		$result = array();
		$stocktake_props= ArrayUtils::getItemsProperties($stocktake_array,'id','store_id');
		$store_array	= store::search(array('id'=>$stocktake_props['store_id']),false,'id');

		$stocktake_item_array		= stocktake_item::search(array('stocktake_id'=>$stocktake_props['id']),false,'id');
		$stocktake_item_props		= ArrayUtils::getItemsProperties($stocktake_item_array,'id','item_id','pallet_id','box_id','box_content_id');

		$pallet_array				= pallet::search(array('id'=>$stocktake_item_props['pallet_id']),false,'id');
		$box_array			= box::search(array('id'=>$stocktake_item_props['box_id']),false,'id');
		$_content_array	= box_content::search(array('id'=>$stocktake_item_props['box_content_id']),false,'id');
		$item_array					= item::search(array('id'=>$stocktake_item_props['item_id']),false,'id');

		$pallet_content_array		= pallet_content::search(array('box_id'=>array_keys($box_array),'status'=>'ACTIVE'),false,'box_id');


		$category_ids				= ArrayUtils::getItemsProperty($item_array,'category_id');
		$category_array				= category::search(array('id'=>$category_ids),false,'id');

		$stocktake_scan_array		= stocktake_scan::search(array('stocktake_id'=>$stocktake_props['id']),false,'id');

		$stocktake_scan_by_pallet = ArrayUtils::getDictionaryByIndex($stocktake_scan_array,'pallet_id');
		$this->debug('array scan', $stocktake_scan_array );

		$stocktake_scan_by_box	= ArrayUtils::getDictionaryByIndex($stocktake_scan_array,'box_id');
		$stocktake_scan_by_box_content	= ArrayUtils::getDictionaryByIndex($stocktake_scan_array,'box_content_id');
		//$stocktake_scan_by_item	= ArrayUtils::getDictionaryByIndex($stocktake_scan_array,'item_id');

		$grouped_stocktake_item_array = ArrayUtils::groupByIndex($stocktake_item_array,'stocktake_id');

		foreach($stocktake_array as $stocktake )
		{
			$si_array = array();
			if(isset( $grouped_stocktake_item_array[ $stocktake['id'] ]  ) )
				$si_array = $grouped_stocktake_item_array[ $stocktake['id'] ];

			$pallets[] = [];

			$stocktake_info = array(
				'stocktake'=> $stocktake,
				'store'=>$store_array[ $stocktake['store_id'] ],
				'items'=>array()
			);

			foreach( $si_array as $stocktake_item)
			{
				$stocktake_item_info = array(
					'stocktake_item'=>$stocktake_item
				);

				if( $stocktake_item['pallet_id'] )
				{
					$stocktake_item_info['pallet'] = $pallet_array[ $stocktake_item['pallet_id'] ];

					if( isset( $stocktake_scan_by_pallet[ $stocktake_item['pallet_id'] ] ) )
						$stocktake_item_info['stocktake_scan']= $stocktake_scan_by_pallet[ $stocktake_item['pallet_id'] ];
					//else
					//	error_log('Pallet not found on stocktake scan');
				}
				else if( $stocktake_item['box_id'] )
				{
					$box = $box_array[ $stocktake_item['box_id'] ];
					$stocktake_item_info['box'] = $box;

					if( isset( $stocktake_scan_by_box[ $stocktake_item['box_id'] ] ) )
					{
						$stocktake_item_info['stocktake_scan']= $stocktake_scan_by_box[ $stocktake_item['box_id'] ];
					}

					if( isset( $pallet_content_array[ $box['id'] ] ) )
					{
						$pallet_content = $pallet_content_array[ $box['id'] ];
						$stocktake_item_info['pallet_content'] = $pallet_content;
					}
				}
				else if( $stocktake_item['box_content_id'] )
				{
					$stocktake_item_info['box_content'] = $_content_array[ $stocktake_item['box_content_id'] ];

					if( isset( $stocktake_scan_by_box_content[ $stocktake_item['box_content_id'] ] ) )
						$stocktake_item_info['stocktake_scan']= $stocktake_scan_by_box_content[ $stocktake_item['box_content_id'] ];
				}
				else if( $stocktake_item['item_id'] )
				{
					$item = $item_array[ $stocktake_item['item_id'] ];
					$stocktake_item_info['item'] = $item;
					$stocktake_item_info['category'] = $category_array[ $item['category_id'] ];
				}
				$stocktake_info['items'][] = $stocktake_item_info;
			}

			$result[] = $stocktake_info;
		}

		return $result;
	}
}
$l = new Service();
$l->execute();
