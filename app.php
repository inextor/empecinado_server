<?php

namespace APP;

include_once( __DIR__.'/akou/src/LoggableException.php' );
include_once( __DIR__.'/akou/src/Utils.php' );
include_once( __DIR__.'/akou/src/DBTable.php' );
include_once( __DIR__.'/akou/src/RestController.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php' );
include_once( __DIR__.'/akou/src/Image.php' );
include_once( __DIR__.'/SuperRest.php');
include_once( __DIR__.'/schema.php');
include_once( __DIR__.'/akou/src/Curl.php');

use \akou\DBTable;
use \akou\Utils;
use \akou\SystemException;
use \akou\ValidationException;
use \akou\ArrayUtils;
use AKOU\Curl;
use \akou\SessionException;

date_default_timezone_set('UTC');
//error_reporting(E_ERROR | E_PARSE);
Utils::$DEBUG				= TRUE;
Utils::$DEBUG_VIA_ERROR_LOG	= TRUE;
#Utils::$LOG_CLASS				= '\bitacora';
#Utils::$LOG_CLASS_KEY_ATTR	= 'titulo';
#Utils::$LOG_CLASS_DATA_ATTR	= 'descripcion';

class App
{
	const DEFAULT_EMAIL					= '';
	const LIVE_DOMAIN_PROTOCOL				= 'http://';
	const LIVE_DOMAIN						= '';
	const DEBUG							= FALSE;
	const APP_SUBSCRIPTION_COST			= '20.00';

	public static $GENERIC_MESSAGE_ERROR	= 'Please verify details and try again later';
	public static $image_directory		= './user_images';
	public static $attachment_directory = './user_files';
	public static $is_debug			= false;
	public static $endpoint			= 'http://127.0.0.1/Empecinado';

	public static function connect()
	{
		DBTable::$_parse_data_types = TRUE;

		$test_servers = array('127.0.0.1','192.168.0.2','2806:1000:8201:71d:42b0:76ff:fed9:5901');

		$domain = app::getCustomHttpReferer();

		//error_log($domain);
		$is_test_server = strpos($domain,'test') !== false;

		if( isset( $_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'],$test_servers ) || $is_test_server )
		{
				$__user		= 'root';
				$__password = 'asdf';
				$__db		= 'empecinado';
				$__host		= '127.0.0.1';
				$__port		= '3306';
				app::$image_directory = './user_images';
				app::$attachment_directory = './user_files';
				app::$is_debug	= true;
		}
		else
		{

				$__db			= 'empecinado';
				$__host		= '127.0.0.1';
				$__password		= 'Soluciones01';
				$__port		= '3306';
				$__user		= 'dbuser';
				Utils::$DB_MAX_LOG_LEVEL	= Utils::LOG_LEVEL_ERROR;
				Utils::$DEBUG				= FALSE;
				Utils::$DEBUG_VIA_ERROR_LOG	= FALSE;
				Utils::$LOG_LEVEL				= Utils::LOG_LEVEL_ERROR;
				app::$attachment_directory = './user_files';
				app::$endpoint = 'http://'.$_SERVER['SERVER_ADDR'].'/Empecinado/api';
				app::$image_directory = './user_images';
				app::$is_debug	= false;
		}

		$mysqli = new \mysqli($__host, $__user, $__password, $__db, $__port );
		if( $mysqli->connect_errno )
		{
			echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
			exit();
		}

		date_default_timezone_set('UTC');

		$mysqli->query("SET NAMES 'utf8';");
		$mysqli->query("SET time_zone = '+0:00'");
		$mysqli->set_charset('utf8');


		DBTable::$connection							= $mysqli;
		DBTable::$connection				= $mysqli;
		//DBTable::importDbSchema('APP');

	}

	static function getPasswordHash( $password, $timestamp )
	{
		return sha1($timestamp.$password.'sdfasdlfkjasld');
	}

	/* https://stackoverflow.com/questions/40582161/how-to-properly-use-bearer-tokens */

	static function getAuthorizationHeader(){
		$headers = null;
		if (isset($_SERVER['Authorization'])) {
			$headers = trim($_SERVER["Authorization"]);
		}
		else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
			$headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
		} elseif (function_exists('apache_request_headers')) {
			$requestHeaders = apache_request_headers();
			// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
			$requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
			//print_r($requestHeaders);
			if (isset($requestHeaders['Authorization'])) {
				$headers = trim($requestHeaders['Authorization']);
			}
		}
		return $headers;
	}
	/**
	* get access token from header
	* */
	static function getBearerToken() {
		$headers = App::getAuthorizationHeader();
		// HEADER: Get the access token from the header
		if (!empty($headers)) {
			if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
				return $matches[1];
			}
		}
		return null;
	}

	//static function getOrganizationFromDomain()
	//{
	//		$returned_var = app::getCustomHttpReferer();
	//		$domain_url	= parse_url( $returned_var );

	//		$domain_name	= $domain_url[ 'host' ];

	//		$domain = domain::searchFirst(array('name'=>$domain_name) );

	//		if( $domain )
	//			return organization::get( $domain->organization_id);

	//		return null;

	//}

	static function getUserFromSession()
	{
		$token = App::getBearerToken();
		if( $token == null )
			return null;

		return App::getUserFromToken( $token );
	}

	static function getRandomString($length)
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);

		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	static function getUserFromToken($token)
	{
		if( $token == null )
			return null;

		$user	= new user();
		$session		= new session();
		$session->id	= $token;
		//$session->estatus = 'SESION_ACTIVA';
		$session->setWhereString();


		if( $session->load() )
		{
			$user = new user();
			$user->id = $session->user_id;

			if( $user->load(true) )
			{
				return $user;
			}
		}
		return null;
	}

	static function getCustomHttpReferer()
	{
		$return_var	= FALSE;

		if( isset( $_SERVER['HTTP_REFERER'] ) )
		{
			$return_var = $_SERVER['HTTP_REFERER'];
		}
		else if( isset( $_SERVER['HTTP_ORIGIN'] ) )
		{
			$return_var = $_SERVER['HTTP_ORIGIN'];
		}
		else if( isset( $_SERVER['HTTP_HOST'] ) )
		{
			$return_var = $_SERVER['HTTP_HOST'];
		}
		else if( isset( $GLOBALS['domain'] ) )
		{

			if
			(
				isset( $GLOBALS['domain']['scheme'] )
				&&
				isset( $GLOBALS['domain']['host'] )
				&&
				isset( $GLOBALS['domain']['path'] )
			)
			{
				$return_var = $GLOBALS['domain']['scheme'] .
				'://' .
				$GLOBALS['domain'].
				$GLOBALS['domain']['path'];
			}
			else
			{
			}
		}

		if( empty( $return_var ) )
		{
			if( !empty( $_GET['domain'] ) )
			{
				$return_var = 'http://'.$_GET['domain'];
			}
		}

		if( !empty( $return_var ) )
		{
			$return_var = str_replace( 'www.', '', $return_var );
		}
		return $return_var;
	}

	static function getLastStockRecord($store_id,$item_id)
	{
		$sql = 'SELECT *
			FROM stock_record
			WHERE `store_id`="'.DBTable::escape($store_id).'" AND item_id="'.DBTable::escape($item_id).'"
			ORDER BY id DESC
			LIMIT 1';

		$stock_record_array = stock_record::getArrayFromQuery( $sql );

		if( count( $stock_record_array ) )
			return $stock_record_array[0];

		return null;
	}

	static function addSerialNumberToMerma($serial_number,$user,$note)
	{
		$box_content = box_content::searchFirst(array('qty>'=>0,'item_id'=>$serial_number->item_id,'box_id'=>$serial_number->box_id));

		if( $box_content )
		{
			app::removeItemFromBoxContent($box_content,1,$user,$note);
		}
		else
		{
			app::removeStock($serial_number->item_id, $serial_number->store_id, $user->id, 0, $note);
		}

		$serial_number->status = 'DESTROYED';

		if( ! $serial_number->update('status') )
		{
			error_log('Ocurrio aqui');
			throw new SystemException('Ocurrio un error Por favor intentar mas tarde. '.$serial_number->getError());
		}
	}

	static function removeItemFromBoxContent($box_content, $qty, $user, $note)
	{
		$box_content->qty -= $qty;

		if( $box_content->qty < 0 )
			$box_content->qty = 0;

		$box = box::get( $box_content->box_id );
		app::removeStock($box_content->item_id, $box->store_id, $user->id, $qty, $note);

		if( !$box_content->update('qty') )
		{
			throw new SystemException('Ocurrio un error por favor intentar mas tarde'.$box_content->getError());
		}
	}

	static function addItemsToBox($box_id, $item_id, $qty, $user_id, $note )
	{
		$box = box::get($box_id);
		$box_content = box_content::searchFirst(array('box_id'=>$box_id, 'item_id'=>$item_id),true);

		if( !$box_content )
		{
			$box_content = new box_content();
			$box_content->box_id = $box_id;
			$box_content->initial_qty = $qty;
			$box_content->qty	= $qty;

			if( !$box_content->insert() )
			{
				throw new SystemException('Ocurrio un error por favor intente mas tarde '.$box_content->getError());
			}
		}
		else
		{
			$box_content->qty += $qty;
			if( !$box_content->update('qty') )
			{
				throw new SystemException('Ocurrio un error por favor intente mas tarde '.$box_content->getError());
			}
		}

		app::addStock($item_id,$box->store_id,$user_id,$qty,$note);
	}

	//Esto es solo para ajustar el stock, no para remover ni agregar
	static function adjustBoxContent($box_content,$qty,$user,$note='Se agrego merma por manejo de inventario')
	{
		if( $box_content->qty > $qty )
		{
			$box = box::get( $box_content->box_id );

			if( $box == null )
				throw new SystemException('Ocurrio un error, no se encontro la caja');

			static::addMerma(null,$box->id,$box->store_id,$box_content->item_id,$box_content->qty - $qty, $user->id,$note);
			$box_content->qty = $qty;

			if( !$box_content->update('qty') )
			{
				throw new ValidationException('Ocurrio un error por favor intentar mas tarde, '.$box_content->getError());
			}
		}
		else
		{
			$box_content->qty = $qty;

			if( $box_content->update('qty') )
			{
				throw new SystemException('No se pudo ajustar el inventario',$box_content->getError());
			}
		}
	}

	static function addStocktakeMerma($stocktake,$box,$item_id,$qty,$user,$note)
	{
		$merma = new merma();
		$merma->box_id	= $box->id;
		$merma->stocktake_id	= $stocktake->id;
		$merma->item_id		= $item_id;
		$merma->qty				= $qty;
		$merma->note			= $note;
		$merma->created_by_user_id = $user->id;

		if( !	$merma->insert() )
		{
			throw new SystemException('Ocurrio un error al registrar la merma por favor intentar mas tarde. '.$merma->getError());
		}

		$box_content = box_content::searchFirst(array('item_id'=>$item_id,'box_id'=>$box->id));

		if( !$box_content )
		{
			throw new ValidationException('La caja no contiene el artículo especificado');
		}

		if( $box_content->qty < $qty )
		{
			throw new ValidationException('No se puede registrar una merma mayor al contenido de la caja');
		}

		$box_content->qty -= $qty;

		if( !$box_content->update('qty') )
		{
			throw new SystemException('Ocurrio un error al actualizar los valores. '.$box_content->getError());
		}

		if( ! $box_content->update('qty') )
			throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$box_content->getError());

		if( !$merma->insert() )
			throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$merma->getError());

		app::removeStock($merma->item_id,$stocktake->store_id,$user->id,$merma->qty,'Merma: '.$note);

	}

	static function addMerma($stocktake_id,$box_id,$store_id,$item_id,$qty,$user_id,$note)
	{
		$merma = new merma();
		$merma->box_id			= $box_id;
		$merma->stocktake_id	= $stocktake_id;
		$merma->item_id			= $item_id;
		$merma->store_id		= $store_id;
		$merma->qty				= $qty;
		$merma->note			= $note;
		$merma->created_by_user_id	=	$user_id;

		if(!$merma->insert())
		{
			throw new SystemException('Ocurrio un error al registrar la merma por favor intentar mas tarde. '.$merma->getError());
		}
	}

	static function addBoxContentMerma($stocktake,$box,$box_content,$note,$user)
	{

		$store_id = $box->store_id;

		if( empty( $store_id ) )
		{
			if( $stocktake != null )
			{
				$store_id = $stocktake->store_id;
			}
			else
			{
				//Se supone que nunca pasa
				throw new ValidationException('Ocurrio un error no se pudo ubicar la caja');
			}
		}

		$merma = new merma();
		$merma->box_id	= $box->id;
		$merma->stocktake_id	= $stocktake ? $stocktake->id : null;
		$merma->item_id		= $box_content->item_id;
		$merma->store_id		= $store_id;
		$merma->qty				= $box_content->qty;
		$merma->note			= $note;
		$merma->created_by_user_id		=		$user->id;


		if( !$merma->insert() )
		{
			throw new SystemException('Ocurrio un error al registrar la merma por favor intentar mas tarde. '.$box_content->getError());
		}

		$box_content->qty = 0;

		if( ! $box_content->update('qty') )
			throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$box_content->getError());

		app::removeStock($merma->item_id,$store_id,$user->id,$merma->qty,'Merma: '.$note);
	}

	static function reduceFullfillInfo($order,$order_item_fullfillment,$user)
	{
		$box_content = box_content::searchFirst(array('box_id'=>$order_item_fullfillment->box_id, 'item_id'=>$order_item_fullfillment->item_id ));

		$box_content->qty -= $order_item_fullfillment->qty;

		if( !$box_content->update('qty') )
		{

		}
		//static::removeStock($order_item_fullfillment->item_id, $order->store_id, $user->id, $order_item_fullfillment->qty,'Surtiendo la orden '.$order->id);
	}

	static function addStock($item_id, $store_id, $user_id, $qty, $description)
	{
		$previous_stock_record = app::getLastStockRecord($store_id,$item_id);

		$previous_stock_qty = $previous_stock_record == null ? 0 :		$previous_stock_record->qty;

		$stock_record = new stock_record();
		$stock_record->item_id			= $item_id;
		$stock_record->store_id		= $store_id;
		$stock_record->previous_qty		= $previous_stock_qty;
		$stock_record->qty					= $previous_stock_qty+$qty;
		$stock_record->movement_type	= "POSITIVE";
		$stock_record->movement_qty		= $qty;
		$stock_record->user_id			= $user_id;
		$stock_record->description			= $description;
		$stock_record->created_by_user_id = $user_id;
		$stock_record->updated_by_user_id = $user_id;

		$stock_record->unsetEmptyValues( DBTable::UNSET_BLANKS );
		print_r('Debug'.print_r($stock_record->toArray(),true), true);

		if( !$stock_record->insertDb() )
		{
			error_log( $stock_record->getLastQuery() );
			throw new SystemException("Ocurrio un error al actualizar el inventario");
		}

		return $stock_record;
	}

	static function sendShippingBoxContent($shipping,$shipping_item, $box, $box_content, $user )
	{
		$message = 'Se envio en la caja'.$box->id.' del envio '.$shipping->id;
		$stock_record = static::removeStock
		(
			$box_content->item_id,
			$shipping->from_store_id,
			$user->id,
			$box_content->qty,
			$message
		);

		$stock_record->shipping_item_id	= $shipping_item->id;
		$stock_record->box_id			= $box->id;
		$stock_record->pallet_id		= $shipping_item->pallet_id;
		$stock_record->box_content_id	= $box_content->id;

		if( !$stock_record->update('shipping_item_id','box_id','box_content_id','pallet_id') )
		{
			throw new SystemException("Ocurrio un error por favor intentar mas tarde",$stock_record->getError());
		}
	}

	static function receiveShippingBoxContent($shipping,$shipping_item,$box,$box_content,$received_qty,$user)
	{
		$message = 'Se Recibio en el envio '.$shipping->id.' en la caja '.$box->id;
		$stock_record = static::addStock
		(
			$box_content->item_id,
			$shipping->to_store_id,
			$user->id,
			$received_qty,
			$message
		);

		$stock_record->shipping_item_id = $shipping_item->id;
		$stock_record->box_content_id	= $box_content->id;
		$stock_record->pallet_id		= $shipping_item->pallet_id;
		$stock_record->box_id			= $box_content->box_id;
		$merma = $box_content->qty - $received_qty;

		if( !$stock_record->update('shipping_item_id','box_content_id','pallet_id','box_id') )
		{
			throw new SystemException('Ocurrio un error por favor intentar mas tarde');
		}

		if( $merma > 0 )
		{
			error_log('Se detecto merma'.$merma);
			//Agregar la merma
		}
	}

	static function receiveShippingItem($shipping,$shipping_item,$received_qty,$user)
	{
		$message = 'Se Recibio en el envio '.$shipping->id;




		$stock_record = static::addStock
		(
			$shipping_item->item_id,
			$shipping->to_store_id,
			$user->id,
			$received_qty,
			$message
		);

		$stock_record->shipping_item_id = $shipping_item->id;
		$merma = $shipping_item->qty - $received_qty;

		if( !$stock_record->update('shipping_item_id') )
		{
			throw new SystemException('Ocurrio un error por favor intentar mas tarde');
		}

		if( $merma > 0 )
		{
			error_log('Se detecto merma'.$merma);
			//Agregar la merma
		}
	}

	static function sendShippingItem($shipping, $shipping_item, $user )
	{
		if( $shipping_item->box_id || $shipping_item->pallet_id )
		{
			throw new ValidationException('Please use function sendShippingBoxContent');
		}

		$message = 'Se envio en el envio "'.$shipping->id.'"';
		$stock_record = static::removeStock
		(
			$shipping_item->item_id,
			$shipping->from_store_id,
			$user->id,
			$shipping_item->qty,
			$message
		);
		$stock_record->shipping_item_id = $shipping_item->id;
		if( !$stock_record->update('shipping_item_id') )
		{
			throw new SystemException('Ocurrio un error por favor intentar mas tarde. '. $stock_record->getError());
		}
	}

	static function removeStock($item_id, $store_id, $user_id, $qty, $description)
	{
		$previous_stock_record = app::getLastStockRecord($store_id,$item_id);

		$p_qty = 0;
		$previous_stock_qty = $qty;

		if( $previous_stock_record !== null )
		{
			$p_qty = $previous_stock_record->qty;
			$previous_stock_qty = $previous_stock_record->qty > $qty ? $previous_stock_record->qty : $qty;
		}

		$stock_record = new stock_record();
		$stock_record->item_id		= $item_id;
		$stock_record->store_id		= $store_id;
		$stock_record->previous_qty	= $p_qty;
		$stock_record->qty						= $previous_stock_qty-$qty;
		$stock_record->movement_type	= "NEGATIVE";
		$stock_record->movement_qty	= $qty;
		//$stock_record->user_id				= $user_id;
		$stock_record->description	= $description;
		$stock_record->created_by_user_id	= $user_id;
		$stock_record->updated_by_user_id	= $user_id;

		if( !$stock_record->insertDb() )
			throw new SystemException("Ocurrio un error al actualizar el inventario");

		return $stock_record;
	}

	static function extractShippingItem($shipping_item,$user)
	{
		$shipping = shipping::get($shipping_item->shipping_id);
		$stock_record = app::removeStock( $shipping_item->id, $shipping->from_store_id, $user->id, $shipping->qty,'Se quito stock por envio un transpaso' );
		$stock_record->shipping_id = $shipping->id;

		if(! $stock_record->update('shipping_id') )
		{
			throw new ValidationException('Ocurrio un error al actualizar el inventario. '. $stock_record->_conn->error );
		}
	}

	static function addSerialNumberRecord($serial_number_record, $user)
	{
		$stock_record = app::addStock
		(
			$serial_number_record->type_item_id,
			$serial_number_record->store_id,
			$user->id,
			$serial_number_record->qty,
			'Se agrego atravez de Registro De Serial'
		);

		$stock_record->serial_number_record_id = $serial_number_record->id;

		if( !$stock_record->update('serial_number_record_id') )
		{
			throw new SystemException('Ocurrio un error al actualizar el inventario'. $stock_record->getError() );
		}
	}

	static function addShippingItem( $shipping_item, $user )
	{

		//static function addStock($item_id, $store_id, $user_id, $qty, $description)
		$shipping= shipping::get($shipping_item->shipping_id);
		$stock_record = app::addStock( $shipping_item->item_id, $shipping->to_store_id, $user->id, $shipping_item->received_qty,'Se agrego atravez de Envio');
		$stock_record->shipping_id = $shipping->id;

		if(! $stock_record->update('shipping_id') )
		{
			throw new ValidationException('Ocurrio un error al actualizar el inventario '.$stock_record->getError() );
		}
	}

	static function addProductionItem($production_item,$production, $user )
	{
		$stock_record = app::addStock
		(
			$production_item->item_id,
			$production->store_id,
			$user->id,
			$production_item->qty,
			'Se agrego atravez de producción'
		);

		$stock_record->production_item_id = $production_item->id;

		if( ! $stock_record->update('production_item_id') )
		{
			error_log( $stock_record->getLastQuery() );
			throw new ValidationException('Ocurrio un error al actualizar el inventario', $stock_record->_conn->error );
		}
	}

	static function extractOrderItem($order_item, $user)
	{
		if( $order_item->delivery_status == 'DELIVERED' )
			throw new ValidationException('uno de los elementos ya fue entregado previamente '.$order_item->id);


		$order_item->delivery_status= 'DELIVERED';
		$order_item->updated_by_user_id = $user->id;
		$order_item->update('delivery_status','updated_by_user_id');

		$order = order::get($order_item->order_id);

		if( $order == null )
			throw new ValidationException("La orden no se encontro");

		$stock_record = app::removeStock( $order_item->item_id, $order->store_id, $user->id,$order_item->qty,'Stock removed from order');
		$stock_record->order_item_id = $order_item->id;

		if(! $stock_record->update('order_item_id') )
		{
			throw new ValidationException('Ocurrio un error al actualizar el inventario', $stock_record->_conn->error );
		}
	}

	static function getPalletInfo($pallet_array,$as_dictionary = FALSE )
	{
		$result = array();

		$pallets_ids			= ArrayUtils::getItemsProperty($pallet_array,'id');
		$pallet_content_array	= pallet_content::search(array('pallet_id'=>$pallets_ids,'status'=>'ACTIVE'),false, 'id');

		$boxes_ids				= ArrayUtils::getItemsProperty($pallet_content_array,'box_id',true);
		$box_array				= box::search(array('id'=>$boxes_ids),false,'id');
		$box_content_array		= box_content::search(array('box_id'=>$boxes_ids),false,'id');

		$item_ids			= ArrayUtils::getItemsProperty($box_content_array,'item_id',true);
		$item_array			= item::search(array('id'=>$item_ids),false,'id');
		$category_ids	= ArrayUtils::getItemsProperty($item_array,'category_id',true);
		$category_array	= category::search(array('id'=>$category_ids),false,'id');

		$pallet_content_grouped = ArrayUtils::groupByIndex($pallet_content_array,'pallet_id');
		$box_content_grouped = ArrayUtils::groupByIndex($box_content_array,'box_id');

		foreach( $pallet_array as $pallet )
		{

			$pc_array		= isset( $pallet_content_grouped[ $pallet['id'] ] ) ? $pallet_content_grouped[ $pallet['id'] ]: array();
			$content_info = array();

			foreach($pc_array as $pallet_content )
			{
				$box = $box_array[ $pallet_content['box_id'] ];
				//box Content Array cc_array
				$cc_array = isset( $box_content_grouped[ $box['id' ] ] ) ? $box_content_grouped[ $box['id' ] ] : array();

				$cc_info = array();

				foreach($cc_array as $box_content )
				{
					$item = $item_array[ $box_content['item_id'] ];
					$category = $category_array[ $item['category_id'] ];

					$cc_info[] = array(
						'box_content'=>$box_content,
						'item'=> $item,
						'category'=> $category,
					);
				}

				$content_info[] = array(
					'pallet_content' => $pallet_content,
					'box'=> $box,
					'content'=> $cc_info
				);
			}

			if( $as_dictionary )
			{
				$result [ $pallet['id'] ] = array
				(
					'pallet'=>$pallet,
					'content'=>$content_info
				);
			}
			else
			{
				$result[] = array(
					'pallet'=>$pallet,
					'content'=>$content_info
				);
			}
		}

		return $result;
	}


	static function getBoxInfo($box_array,$_as_dictionary=FALSE)
	{
		$box_props		= ArrayUtils::getItemsProperties($box_array,'id','production_item_id');
		//$production_item_array	= production_item::search(array('id'=>$box_props['production_item_id']),false,'id');

		$box_content_array		= box_content::search(array('box_id'=>$box_props['id'],'qty>'=>0),false,'id');
		$serial_number_array	= serial_number::search(array('box_id'=>$box_props['id']),false,'box_id');

		$item_ids				= ArrayUtils::getItemsProperty( $box_content_array,'item_id');
		$item_array				= item::search(array('id'=>$item_ids),false,'id');
		$category_ids			= ArrayUtils::getItemsProperty($item_array,'category_id');
		$category_array			= category::search(array('id'=>$category_ids),false,'id');
		$pallet_content_array	= pallet_content::search(array('box_id'=>$box_props['id'],'status'=>'ACTIVE'),false,'box_id');

		$box_content_group		= ArrayUtils::groupByIndex($box_content_array,'box_id');

		$result = array();

		foreach($box_array as $box)
		{
			$content_result = array();
			$cc_array = isset( $box_content_group[ $box['id'] ] ) ? $box_content_group[ $box['id'] ] : array();

			foreach($cc_array as $box_content)
			{
				$item		= $item_array[ $box_content['item_id'] ];
				$category	= $category_array[ $item['category_id'] ];

				$content_result[] = array(
					'item'			=> $item,
					'category'		=> $category,
					'box_content'	=> $box_content
				);
			}

			$pallet_content = isset( $pallet_content_array[ $box['id'] ] ) ? $pallet_content_array[ $box['id'] ] : null;

			if( $_as_dictionary )
			{
				//$serial_numbers = isset( $serial_number_array[ $box['id']  ]
				$box_info = array(
					'box'			=> $box,
					//'serial_number'	=> $serial_number_array[ $box['id'] ],
					'content'		=> $content_result,
				);

				if( $pallet_content )
					$box_info['pallet_content'] = $pallet_content;

				$result[ $box['id'] ] =	$box_info;
			}
			else
			{
				$box_info = array(
					'box'		=> $box,
					//'serial_number' => $serial_number_array[ $box['id'] ],
					'content'			=> $content_result
				);

				if( $pallet_content )
					$box_info['pallet_content'] = $pallet_content;

				$result [] =	$box_info;
			}
		}
		return $result;
	}
	static function getShippingInfo($shipping_array)
	{
		$shipping_ids			= ArrayUtils::getItemsProperty($shipping_array,'id', true);
		$shipping_item_array	= shipping_item::search(array('shipping_id'=>$shipping_ids),false,'id');

		$shipping_item_props	= ArrayUtils::getItemsProperties($shipping_item_array,'pallet_id','box_id','item_id');

		$pallet_array			= pallet::search(array('id'=>$shipping_item_props['pallet_id']),false,'id');
		$box_array				= box::search(array('id'=>$shipping_item_props['box_id']),false,'id');

		$pallets_info_array		= app::getPalletInfo( $pallet_array, TRUE );
		$box_info_array			= app::getBoxInfo( $box_array, TRUE );

		$items_array			= item::search(array('id'=>$shipping_item_props['item_id']),false, 'id');
		$category_ids			= ArrayUtils::getItemsProperty($items_array,'category_id');
		$category_array			= category::search(array('id'=>$category_ids), false, 'id');


		$shipping_item_grouped	= ArrayUtils::groupByIndex($shipping_item_array,'shipping_id');

		$result = array();

		foreach($shipping_array as $shipping)
		{
			$shipping_items = isset( $shipping_item_grouped[ $shipping['id'] ] )
				? $shipping_item_grouped[ $shipping['id'] ]
				: array();

			$items_info = array();

			foreach($shipping_items as $si)
			{
				$pallet_info = null;
				$pallet_info = $si['pallet_id'] ? $pallets_info_array[ $si['pallet_id'] ] : null;
				$box_info = $si['box_id'] ? $box_info_array[ $si['box_id'] ] : null;

				$item = $si['item_id'] ? $items_array[ $si['item_id'] ]: null;
				$category = $si['item_id'] ? $category_array[ $item['category_id'] ] : null;

				$stock_record = null;

				if( $box_info )
				{
					$stock_record_array = stock_record::searchFirst(array('shipping_item_id'=>$si['id'], 'box_id'=> $si['box_id'],'item_id'=>$si['item_id']),false);
					$box_info['records']	=$stock_record_array;
				}


				$items_info[]= array
				(
					'shipping_item'	=> $si,
					'pallet_info'	=> $pallet_info,
					'box_info'		=> $box_info,
					'item'			=> $item,
					'category'		=> $category,
					'stock_record'	=> $stock_record
				);
			}

			$result[] = array(
				'items'=> $items_info,
				'shipping'=>$shipping
			);
		}

		return $result;
	}

	static function updateOrderTotal($order_id)
	{
		$order = order::get( $order_id );
		$order_item_array = order_item::search(array('order_id'=>$order_id,'status'=>'ACTIVE') );

		$order->total	= 0;
		$poner_precios	= $order->status == 'PENDING';
		//Una vez que se hace el trato el precio no se modifica
		if( $poner_precios )
		{
			foreach($order_item_array as $order_item)
			{
				$store = store::get($order->store_id );

				error_log('Price type is	==============>'.$order->price_type_id);
				$price = price::searchFirst(array('price_type_id'=>$order->price_type_id,'item_id'=>$order_item->item_id,'store_id'=>$store->id));

				if( $price == NULL )
				{
					$item	= item::get($order_item->item_id);
					$category		= category::get( $item->category_id );
					throw new ValidationException('No existe precio para "'.$category->name.' '.$item->name.'" en sucursal '.$store->name.' codigo: utv1');
				}

				$order_item->price				= $price->price;

				if( $order_item->is_free_of_charge == 'YES' )
				{
					$order_item->total			= 0;
					$order_item->subtotal			= 0;
					$order_item->tax			= 0;
					$order_item->unitary_price	= $price->price;
				}
				else
				{
					$order_item->total				= $price->price*$order_item->qty;
					$order_item->subtotal		= sprintf('%0.6f',$order_item->total/(1+($store->tax_percent*0.01) ));
					$order_item->tax			= sprintf('%0.6f',$order_item->total-$order_item->subtotal);
					$order_item->unitary_price		= $order_item->subtotal/$order_item->qty;
				}

				if(!$order_item->update('price','total','subtotal','tax','unitary_price') )
				{
					throw new SystemException('Ocurrio un error por favor intente mas tarde Codigo: utv2');
				}

				$order->total			+= $order_item->total;
				$order->pending_amount	= $order->total;
				$order->subtotal		+= $order_item->subtotal;
				$order->tax				+= $order_item->tax;
			}

			$order->total		+= $order->shipping_cost;
		}


		if( !$order->update('total','subtotal','tax') )
		{
			throw new SystemException('Ocurrio un error al actualizar el total de la orden');
		}
	}

	static function saveOrderItem($order_item_values )
	{
		if( empty( $order_item_values['item_id'] ) )
			throw new ValidationException('item id cant be empty');

		$item = item::get( $order_item_values['item_id'] );

		if( empty( $item ) )
		{
			throw new ValidationException('El producto o servicio no se encontro');
		}

		$order = order::get( $order_item_values['order_id'] );

		if( $order->status !== 'PENDING' )
			throw new ValidationException('La orden ya fue procesada y no se puede editar');

		if( $order == null )
			throw new ValidationException('No se encontro la orden');

	//	error_log('checking order item_values'.print_r( $order_item_values,true ));

		$search_item_array = array( 'item_id'=> $item->id ,'order_id'=> $order_item_values['order_id'], 'is_free_of_charge'=>$order_item_values['is_free_of_charge']);
		$sql		= order_item::getSearchFirstSql( $search_item_array );
		$order_item	= order_item::searchFirst( $search_item_array );

//		error_log('Sql search'. $sql );

		if( empty( $order_item) )
		{
			$order_item = new order_item();
			$order_item->order_id = $order->id;
		}

		$price_search = array('item_id'=>$item->id,'store_id'=>$order->store_id,'price_type_id'=>$order->price_type_id);

		$price	= price::searchFirst( $price_search );

		if( !$price )
		{
			throw new ValidationException('El precio del artículo "'.$item->name.'" no se encontro');
		}

		$store = store::get( $order->store_id );

		if( !$store )
			throw new ValidationException('No se encontrol el almacen');

		$order_item->item_id		= $order_item_values['item_id'];
		$order_item->qty			= $order_item_values['qty'];
		$order_item->return_required	= empty( $order_item_values['return_required'] ) ? 'NO' : $order_item_values['return_required'];
		$order_item->is_free_of_charge = empty( $order_item_values['is_free_of_charge'] ) ? 'NO' : $order_item_values['is_free_of_charge'] ;

		if( $order_item->is_free_of_charge == 'YES' )
		{
			$order_item->price			= $price->price;
			$order_item->total			= 0;
			$order_item->subtotal		= 0;
			$order_item->unitary_price	= $price->price;//sprintf('%0.6f',$order_item->total/(1+($store->tax_percent*0.01) ));
			$order_item->tax			= 0;//sprintf('%0.6f',$order_item->total-$order_item->subtotal);
		}
		else
		{
			$order_item->price				= $price->price;
			$order_item->total				= $price->price*$order_item->qty;
			$order_item->subtotal			= sprintf('%0.6f',$order_item->total/(1+($store->tax_percent*0.01) ));
			$order_item->unitary_price		= $order_item->subtotal/$order_item->qty;
			$order_item->tax				= sprintf('%0.6f',$order_item->total-$order_item->subtotal);
		}

		if( empty( $order_item->id ) )
		{
			if( !$order_item->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde',print_r( $order_item->toArray(),true));
			}
		}
		else if( !$order_item->updateDb() )
		{
			throw new SystemException('Ocurrio un error por favor intentar mas tarde',print_r( $order_item->toArray(),true));
		}

		app::updateOrderTotal($order_item->order_id);

		return $order_item;
	}

	static function sendNotification($push_notification, $user_ids_array)
	{
		//https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#notification

		$notification_token_array = notification_token::search(array('user_id'=>$user_ids_array,'status'=>"ACTIVE"));
		$tokens = ArrayUtils::getItemsProperty($notification_token_array,'token', true );

		if( app::$is_debug )
			return;

		if( empty( $notification_token_array ) )
		{
			error_log("no existe tokens de notificaiones para el usuario: ".$push_notification->user_id);
			return;
		}

		//AAAAXJGUlwU:APA91bFT6NJRYvaj6hzmVb0efeFy9UlLuiVAn1bUvBPmqVHBxzBOg7gJj-e30EZuVZ0bejvgu3ADVqqw5ijHgrkdL2qzHcWFKt6hXcJjruTYDsIBZl7DYCpisRRHsrtYYfrjcSXry4g6

		$notification_info = array
		(
			'notification'=> array
			(
				"title"=>$push_notification->title,
				"body"=>$push_notification->body,
			),
			'webpush'=>array
			(
				"title"=> $push_notification->title,
				"body"=>$push_notification->body
			)
		);

		if( $push_notification->object_type )
		{
			$notification_info['data'] = array('object_type'=>$push_notification->object_type, 'object_id'=>''.$push_notification->object_id );
		}

		if( count( $tokens ) == 1	)
		{
			$notification_info['to'] = $notification_token_array[0]->token;
		}
		else
		{
			$tokens = ArrayUtils::getItemsProperty($notification_token_array,'token');
			$notification_info['notification']['registration_ids'] = $tokens;
			$notification_info['notification']['dry-run'] = app::$is_debug;
		}

		if( $push_notification->icon_image_id )
		{
			$notification_info['notification']['image']	= app::$endpoint.'/image.php?id='.$push_notification->icon_image_id;
			//Si no funcionas para push
			$notification_info['webpush']['headers']	= array( 'image'=>app::$endpoint.'/image.php?id='.$push_notification->icon_image_id);
		}

		if( $push_notification->link )
		{
			$notification_info['fcm_options'] = array('link'=> $push_notification->link );
		}

		$curl = new Curl('https://fcm.googleapis.com/fcm/send');
		$curl->setHeader('Authorization','key=AAAAXJGUlwU:APA91bFT6NJRYvaj6hzmVb0efeFy9UlLuiVAn1bUvBPmqVHBxzBOg7gJj-e30EZuVZ0bejvgu3ADVqqw5ijHgrkdL2qzHcWFKt6hXcJjruTYDsIBZl7DYCpisRRHsrtYYfrjcSXry4g6');
		$curl->setHeader('Content-Type','application/json');
		$curl->setMethod('POST');
		$payload = json_encode($notification_info);
		$curl->setPostData( $payload );
		$curl->debug = true;
		$curl->execute();

		if( $curl->status_code >= 200 && $curl->status_code <300 )
		{
			$push_notification->response = $curl->raw_response;
			$push_notification->updateDb('response');
		}
		else
		{

		}
	}


	static function updateBalances($bank_account)
	{
		$sql = 'SELECT * FROM bank_movement WHERE bank_account_id = "'.DBTable::escape($bank_account->id).'" ORDER BY paid_date ASC FOR UPDATE';

		$bank_movement_array = bank_movement::getArrayFromQuery($sql);
		$balance = 0;
		foreach($bank_movement_array as $bank_movement)
		{

			if( $bank_movement->type == "income")
			{
				$balance += $bank_movement->amount;
			}
			else
			{
				$balance -= $bank_movement->amount;
			}

			$bank_movement->balance = "$balance";
			$bank_movement->update('balance');
		}
	}
}
