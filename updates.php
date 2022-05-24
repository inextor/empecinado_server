<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use AKOU\ArrayUtils;
use \akou\DBTable;
use \akou\ValidationException;
use \akou\LoggableException;
use AKOU\NotFoundException;
use \akou\SystemException;
use \akou\SessionException;

class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		$name = $_GET['method'];

		DBTable::autocommit(false);
		try
		{
			if( is_callable(array($this, $name) ))
			{
				$result = $this->$name();
				DBTable::commit();
				return $result;
			}
			else
			{
				throw new ValidationException('No se encontro la función '.$name);
			}

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

	function post()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		$params = $this->getMethodParams();
		$name = $params['method'];

		DBTable::autocommit(false);
		try
		{
			if( is_callable(array($this, $name) ))
			{
				$result = $this->$name();
				DBTable::commit();
				return $result;
			}
			else
			{
				throw new ValidationException('No se encontro la función '.$name);
			}
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

	function addDestructionEvidence()
	{
		$user = app::getUserFromSession();

		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		$params = $this->getMethodParams();

		$params = $this->getMethodParams();

		if( empty( $params['evidence_image_id']) || empty($params['code']) )
		{
			throw new ValidationException('Codigo y la imagen no pueden estar vacios');
		}

		$serial_number = serial_number::searchFirst(array('code'=>$params['code'],'type_item_id'=>5)); //This sucks

		if( empty( $serial_number ) )
		{
			throw new ValidationException('No se encontro ninguna elemento con el codigo"'.$params['code'].'"');
		}

		if( $serial_number->status == 'DESTROYED')
		{
			throw new ValidationException('La elemento ya ha sido destruido');
		}

		$serial_number->evidence_image_id = $params['evidence_image_id'];
		$serial_number->status = 'DESTROYED';
		$serial_number->updated_by_user_id = $user->id;

		if( !$serial_number->update('evidence_image_id','status','updated_by_user_id') )
		{
			throw new SystemException('Ocurrio un error por favor intente mas tarde '.$serial_number->getError());
		}
		return $this->sendStatus(200)->json(true);
	}

	function agregarEvidenciaDestruccionMarbete()
	{
		$user = app::getUserFromSession();

		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		$params = $this->getMethodParams();

		if( empty( $params['code']) || empty($params['evidencia_destruccion_image_id']) )
		{
			throw new ValidationException('El marbete y la imagen no pueden estar vacios');
		}

		$botella = botella::searchFirst(array('marbete_id'=>$params['marbete_id']));

		if( empty( $botella ) )
		{
			throw new ValidationException('No se encontro ninguna botella con el marbete "'.$params['marbete_id'].'"');
		}

		if( $botella->estatus == 'DESTRUIDO')
		{
			throw new ValidationException('La botella ya ha sido destruida');
		}

		$botella->evidencia_destruccion_image_id = $params['evidencia_destruccion_image_id'];
		$botella->estatus = 'DESTRUIDO';

		if( !$botella->update('evidencia_destruccion_image_id','estatus') )
		{
			throw new SystemException('Ocurrio un error por favor intente mas tarde '.$botella->_conn->error);
		}
		return $this->sendStatus(200)->json(true);
	}

	function asignarACopeo()
	{
		$params = $this->getMethodParams();

		$user = app::getUserFromSession();

		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		if( empty( $params['codigo_botella'] ) )
		{
			throw new ValidationException('El codigo de la botella no puede estar vacio');
		}

		$botella = botella::searchFirst(array('codigo_botella'=>$params['codigo_botella']));

		if( $botella == null )
		{
			throw new ValidationException('No se encontro la botella con codigo "'.$params['codigo_botella'].'"');
		}

		if( empty( $botella->marbete_id  ) )
		{
			throw new ValidationException('La botella no tiene asigando un marbete');
		}

		$botella->tipo_de_consumo = 'COPEO';
		$botella->tipo_de_mercado = 'NACIONAL';

		if(! $botella->update('tipo_de_consumo','tipo_de_mercado','updated_by_user_id') )
		{
			throw new ValidationException('Ocurrio un error por favor intentear mas tarde'. $botella->_conn->error );
		}

		return $this->sendStatus(200)->json(true);
	}

	function asignarMarbetes()
	{
		$user = app::getUserFromSession();

		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		$params = $this->getMethodParams();

		if( empty( $params['inicial_marbete_id'] ) || empty( $params['cantidad'] ) || empty( $params['inicial_codigo_botella'] ) )
		{
			throw new ValidationException('Por favor checa bien los parametros');
		}

		$inicial = intVal($params['inicial_marbete_id'],10);
		$final	= intVal($params['final_marbete_id'],10);
		$botella_inicial = intVal( $params['inicial_codigo_botella'],10);

		$diferencia  = $final-$inicial;

		for($i=0; $i<=$diferencia;$i++)
		{
			$marbete_id = $inicial+$i;
			$botella_id	= $botella_inicial+$i;
			$botella = botella::searchFirst(array('codigo_botella'=>$botella_id));

			if(!empty(  $botella->marbete_id ) )
				throw new ValidationException('La botella con id "'.$botella->codigo_botella.'" Ya cuenta con un marbete asignado');

			if( $botella == null )
			{
				throw new ValidationException('no se encontro la botella con id "'.$botella_id.'"');
			}

			$botella->marbete_id = $marbete_id;

			if(! $botella->update('marbete_id') )
			{
				throw new SystemException('Ocurrio un error por favor intente mas tarde');
			}

		}

		return $this->sendStatus(200)->json(true);
	}

	function markShippingAsSent()
	{
		$user = app::getUserFromSession();

		if( !$user  )
			throw new ValidationException('Por favor iniciar sesion');

		$params = $this->getMethodParams();

		$shipping = shipping::get( $params['shipping_id'] );

		if( $shipping->status !== 'PENDING' )
		{
			throw new ValidationException('El envio ya fue enviado');
		}

		if( $shipping  == null )
		{
			throw new ValidationException('No se encontro el envio. Por favor intente mas tarde');
		}

		$shipping_items_array = shipping_item::search(array('shipping_id'=>$shipping->id));

		foreach($shipping_items_array as $shipping_item)
		{
			$pallet = $shipping_item->pallet_id ? pallet::get( $shipping_item->pallet_id ) : null;
			$box = $shipping_item->box_id ? box::get( $shipping_item->box_id ) : null;

			if( $pallet )
			{
				$pallet_content_array = pallet_content::search(array('pallet_id'=>$pallet->id));
				foreach($pallet_content_array as $pallet_content)
				{
					$box = box::get( $pallet_content->box_id );
					$box_content_array =box_content::search(array('box_id'=>$box->id));
					foreach($box_content_array as $box_content)
					{
						$this->debug('user',$user );
						app::sendShippingBoxContent($shipping,$shipping_item,$box,$box_content,$user);
					}

					if( !$box->update('store_id') )
					{
						throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$box->getError());
					}
				}
				$pallet->store_id = $shipping->to_store_id;
				$pallet->update('store_id');
			}
			else if( $box )
			{
				$box_content_array =box_content::search(array('box_id'=>$box->id));
				foreach($box_content_array as $box_content)
				{
					app::sendShippingBoxContent($shipping,$shipping_item, $box,$box_content,$user);
				}

				if( !$box->update('store_id') )
				{
					throw new SystemException('Ocurrio un error por favor intentar mas tarde, '.$box->getError());
				}
			}
			else
			{
				app::sendShippingItem( $shipping, $shipping_item, $user );
			}
		}

		$shipping->status = 'SENT';
		$shipping->updated_by_user_id = $user->id;

		if(	!$shipping->update('status','updated_by_user_id') )
		{
			throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$shipping->getError());
		}

		$user_array = user::search(array('store_id'=>$shipping->to_store_id,'type'=>'USER'),true,'id');

		if( count( $user_array ) == 0 )
		{
			error_log('No hay usuario asignado al alamacen con id '.$shipping->to_store_id);
		}

		foreach($user_array as $user )
		{
			$push_notification				= new push_notification();
			$push_notification->object_type	= 'shipping';
			$push_notification->object_id	= $shipping->id;
			$push_notification->app_path	= '/view-shipping/'.$shipping->id;
			$push_notification->title		= 'Cargamento enviado';
			$push_notification->body		= 'Por favor ponerse al pendiente';
			$push_notification->link		= app::$endpoint.'#/view-shipping/'.$shipping->id;
			$push_notification->user_id		= $user->id;

			if( !$push_notification->insertDb() )
			{
				error_log('Fallo guardar la notificaciones');
			}

			app::sendNotification( $push_notification, array_keys($user_array) );
		}


		return $this->sendStatus(200)->json( $shipping->toArray() );
	}

	function recibirShipping()
	{
		$user = app::getUserFromSession();

		if( !$user )
		{
			throw new ValidationException('Por favor inicia sesion');
		}

		$params = $this->getMethodParams();
		$shipping = shipping::get( $params['shipping_id'] );


		if( $shipping->status == 'DELIVERED' )
			throw new ValidationException('El envio ya fue recibido previamente');

		if( $shipping ->status != 'SENT' )
		{
			throw new ValidationException('El envio primero se tiene que marcar como enviado');
		}

		if( $shipping  == null )
		{
			throw new ValidationException('No se encontro el envio. Por favor intente mas tarde');
		}

		$shipping_items_array = shipping_item::search(array('shipping_id'=>$shipping->id));

		foreach($shipping_items_array as $shipping_item)
		{
			$received_items_count = 0;
			$pallet = $shipping_item->pallet_id ? pallet::get( $shipping_item->pallet_id ) : null;
			$box = $shipping_item->box_id ? box::get( $shipping_item->box_id ) : null;

			if( $pallet )
			{
				$pallet_content_array = pallet_content::search(array('pallet_id'=>$pallet->id));
				foreach($pallet_content_array as $pallet_content)
				{
					$box = box::get( $pallet_content->box_id );
					$box_content_array =box_content::search(array('box_id'=>$box->id));
					foreach($box_content_array as $box_content)
					{
						$qtys = $params['quantities']['content_id-'.$box_content->id];
						app::receiveShippingBoxContent($shipping,$shipping_item,$box,$box_content,$qtys['qty'],$user);
						$received_items_count = $qtys['qty'];
					}

					$box->store_id = $shipping->to_store_id;

					if( !$box->update('store_id') )
					{
						throw new SystemException('Ocurrio un error por favor intentar mas tarde'.$box->getError());
					}
				}
				$pallet->store_id = $shipping->to_store_id;
				$pallet->update('store_id');
			}
			else if( $box )
			{
				$box_content_array =box_content::search(array('box_id'=>$box->id));
				foreach($box_content_array as $box_content)
				{
					$qtys = $params['quantities']['content_id-'.$box_content->id];
					app::receiveShippingBoxContent($shipping,$shipping_item,$box,$box_content,$qtys['qty'],$user);
					$received_items_count = $qtys['qty'];
				}

				$box->store_id = $shipping->to_store_id;

				if( !$box->update('store_id') )
				{
					throw new SystemException('Ocurrio un error por favor intentar mas tarde'.$box->getError());
				}
			}
			else
			{
				$qtys = $params['quantities']['item_id-'.$shipping_item->id];
				error_log('FOOOO'.print_r( $qtys,true) );
				app::receiveShippingItem($shipping, $shipping_item, $qtys['qty'], $user );
			}

			$shipping_item->received_qty = $received_items_count;

			if( !$shipping_item->update('received_qty') )
			{
				throw new ValidationException('Ocurrio un error al guardar la informacion por favor intentar mas tarde');
			}
		}
		$shipping->status = 'DELIVERED';
		$shipping->updated_by_user_id = $user->id;
		if( !$shipping->update('status','updated_by_user_id') )
		{
			throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$shipping->getError());
		}

		$user_array = user::search(array('store_id'=>$shipping->to_store_id,'type'=>'USER'));

		foreach($user_array as $user )
		{
			$push_notification				= new push_notification();
			$push_notification->object_type	= 'shipping';
			$push_notification->object_id	= $shipping->id;
			$push_notification->app_path	= '/view-shipping/'.$shipping->id;
			$push_notification->link		= app::$endpoint.'#/view-shipping/'.$shipping->id;
			$push_notification->user_id		= $user->id;

			if( !$push_notification->insertDb() )
			{
				error_log('Fallo guardar la notificaciones');
			}
		}

		$this->sendStatus(200)->json( $shipping );
	}

	function closeProduction()
	{
		$params = $this->getMethodParams();

		$user = app::getUserFromSession();

		if( !$user )
			throw new SessionException('Por favor iniciar sesion');


		$production = production::get( $params['production_id'] );
		if( $production == null )
			throw new ValidationException("No se encontro la produccion");


		$production->status = 'CLOSED';
		if( !$production->update('status') )
		{
			throw new SystemException('ocurrio un error por favor intentar mas tarde '.$production->getError());
		}
		return $this->sendStatus( 200 )->json( $production->toArray() );
	}

	function asignarRangoMarbeteCajas()
	{
		$params = $this->getMethodParams();
		$user = app::getUserFromSession();

		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		foreach( $params['boxes'] as $box_info )
		{
			if( empty( $box_info['box']['id'] ) )
			{
				throw new ValidationException('El id de la caja no puede ser nula');
			}

			$box = box::get( $box_info['box']['id'] );

			if( $box->serial_number_range_end !== null )
			{
				throw new ValidationException('Ya se registraron los marbetes para la caja "'.$box->id.'"');
			}

			if( !empty( $box->serial_number_range_start ) )
			{
				throw new ValidationException('La caja Ya tiene asignado marbetes');
			}


			$box->serial_number_range_start = $params['serial_number_range_start'];
			$box->serial_number_range_end	= $params['serial_number_range_end'];
			$box->updated_by_user_id = $user->id;

			if( !$box->update('serial_number_range_end','serial_number_range_start') )
			{
				error_log( $box->getLastQuery() );
				throw new SystemException('Ocurrio un error por favor intentar mas tarde'.$box->getError());
			}

			$box_content_array = box_content::search(array('box_id'=>$box->id,'qty>'=>0));

			if( empty( $box_content_array) || count( $box_content_array ) > 1 )
			{
				throw new ValidationException('Ocurrio un error la caja tiene diferentes tipos de producto o esta vacia');
			}

			$box_content = $box_content_array[0];

			for($i=$box->serial_number_range_start;$i<=$box->serial_number_range_end;$i++)
			{
				$serial_number			= serial_number::searchFirst(array('code'=>$i));

				if( $serial_number == null )
				{
					$serial_number = new serial_number();
					$serial_number->code = $i;
					$serial_number->created_by_user_id = $user->id;
				}

				$serial_number->code	= $i;
				$serial_number->type_item_id = 5;//This sucks
				$serial_number->box_id	= $box->id;
				$serial_number->item_id	= $box_content->item_id;
				$serial_number->store_id = $box->store_id;
				$serial_number->updated_by_user_id = $user->id;

				if( $serial_number->id )
				{
					if(! $serial_number->updateDb(array('box_id','item_id','store_id')) )
						throw new SystemException('Ocurrio un error al guardar los datos '.$serial_number->getError());
				}
				else
				{
					if(! $serial_number->insertDb() )
						throw new SystemException('Ocurrio un error al guardar los datos '.$serial_number->getError());
				}

				error_log('Se guardo/actualizo'.$serial_number->code);
			}
		}

		return true;
	}

	function markOrderAsDelivered()
	{
		$user=  app::getUserFromSession();
		if( !$user )
			throw new SessionException("por favor iniciar sesion");

		$params = $this->getMethodParams();

		$order = order::get( $params['order_id'] );

		if( !$order )
		{
			throw new ValidationException('La orden no se encontro');
		}

		if( $order->delivery_status == 'DELIVERED')
			throw new ValidationException('La orden ya ha sido entregada previamente');

		app::updateOrderTotal($order->id);

		$order_item_array = order_item::search(array('delivery_status'=>"PENDING"));

		foreach($order_item_array as $order_item )
		{
			$order_item->status = 'DELIVERED';
			app::extractOrderItem($order_item, $user);
		}


		$order->delivery_status = 'DELIVERED';
		$order->updated_by_user_id = $user->id;

		if( !$order->update('delivery_status','updated_by_user_id') )
		{
			throw new SystemException('Ocurrio un error, por favor intentar mas tarde. '.$order->getError());
		}


		//$push_notification = new push_notification();
		//$push_notification->user_id = $order->created_by_user_id;
		//$push_notification->title = 'Nueva Venta';
		//$push_notification->body = 'Nueva venta para '.$order->client_user_id.' en la sucursal '.$order->store_id;
		//$push_notification->icon_image_id = 51;
		//$push_notification->object_type = 'order';
		//$push_notification->app_path = '/view-order/'.$order->id;
		//$push_notification->object_id = $order->id;
		//$push_notification->insertDb();

		//app::sendNotification($notification,array($order->cashier_user_id));

		$order->load(true);

		return $this->sendStatus(200)->json( $order->toArray() );
	}

	function sendNotification()
	{
		if( empty( $_GET['push_notification_id'] ) )
		{
			throw new ValidationException('El id de la notificacion no puede estar vacio');
		}

		$push_notification = push_notification::get( $_GET['push_notification_id'] );

		if( !$push_notification )
		{
			throw new ValidationException('No se encontro la notification');
		}

		app::sendNotification( $push_notification, array( $push_notification->user_id ) );
	}

	function copyPricesFromStoreToStore()
	{
		$params = $this->getMethodParams();

		if( empty($params['from_store'] ) )
			throw new ValidationException('from store cant be empty');

		if( empty($params['to_store'] ) )
			throw new ValidationException('to store cant be empty');

		$from_store_id = DBTable::escape( $params['from_store_id'] );
		$to_store_id = DBTable::escape( $params['to_store_id'] );

		$sql = 'INSERT INTO `price` (item_id,"'.$to_store_id.'",price ) VALUES
					SELECT p.item_id,p.store_id,p.price FROM price AS p WHERE p.store_id = "'.$from_store_id.'"
					ON DUPLICATE KEY UPDATE price = p.price';

		$result = DBTable::query( $sql );
		if( $result )
			return $this->sendStatus(200)->json(true);
		else
			return $this->sendStatus(500)->json(DBTable::$connection->error );
	}

	function removeBoxFromPallet()
	{
		$user = app::getUserFromSession();

		if( !$user )
			throw new SessionException("Por favor iniciar session");

		$params = $this->getMethodParams();

		if( empty( $params['box_id'] ) )
			throw new ValidationException('El id de la caja no puede estar vacio');

		$pallet_content = pallet_content::searchFirst(array('box_id'=>$params['box_id'],'status'=>'ACTIVE') );

		if( $pallet_content == null )
		{
			throw new ValidationException('No se encontro la caja en ninguna tarima');
		}

		$pallet_content->status = 'REMOVED';

		$pallet_content->updated_by_user_id = $user->id;

		if( !$pallet_content->update('status','updated_by_user_id') )
		{
			throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$pallet_content->getError() );
		}

		error_log('query'.$pallet_content->getLastQuery());


		return $this->sendStatus(200)->json( true );
	}

	function markPushNotificationsAsRead()
	{
		$user = app::getUserFromSession();

		if( $user == null )
			throw new SessionException('Por favor iniciar sesion');

		$sql = 'UPDATE push_notification SET read_status = "READ" WHERE user_id = "'.DBTable::escape($user->id).'"';
		DBTable::query( $sql );
		return $this->sendStatus(200)->json(true);
	}

	function closeStocktake($stocktake)
	{
		$user = app::getUserFromSession();

		if( $user == null )
			throw new SessionException('Por favor iniciar sesion');

		if( empty( $_GET['id']  ) )
		{
			throw new ValidationException('El id no puede estar vacio');
		}
		$stocktake	= stocktake::get($_GET['id']);

		if( $stocktake == null )
			throw new ValidationException('No se encontro la toma de inventario con id "'.$_GET['id'].'"');


		$stocktake_scan_array		= stocktake_scan::search(array('stocktake_id'=>$stocktake->id),true,'id');
		$stocktake_scan_props		= ArrayUtils::getItemsProperties($stocktake_scan_array,'pallet_id','box_id','box_content_id','item_id');
		$pallet_array				= pallet::search(array('id'=>$stocktake_scan_props['pallet_id']),true,'id');
		$pallet_content_array		= pallet_content::search(array('pallet_id'=>array_keys($pallet_array),'status'=>'ACTIVE'),true,'id');


		$pallet_content_box_ids	= ArrayUtils::getItemsProperty($pallet_content_array,'box_id');
		$box_ids					= array_merge( $pallet_content_box_ids, isset( $stocktake_scan_props['box_id'] ) ?  $stocktake_scan_props['box_id'] : array() );

		$box_array			= box::search(array('id'=>$box_ids),false,'id');
		$box_content_array	=box_content::search(array('box_id'=>array_keys($box_array)),false,'id');

		$stocktake_item_array			= stocktake_item::search(array('stocktake_id'=>$stocktake->id),true,'id');
		$stocktake_item_by_cc			= ArrayUtils::getDictionaryByIndex($stocktake_item_array, 'box_content_id');
		$stocktake_scan_by_item			= ArrayUtils::getDictionaryByIndex($stocktake_scan_array, 'item_id');
		$stocktake_item_by_item			= ArrayUtils::getDictionaryByIndex($stocktake_item_array,'item_id');

		foreach($box_content_array as $box_content)
		{
			$stocktake_item = isset( $stocktake_item_by_cc[ $box_content['id'] ] ) ? $stocktake_item_by_cc[ $box_content['id'] ] : null;

			if( $stocktake_item == null )
			{
				error_log('Esto no debio suceder');
				//Agregar de mas aqui //Es cuando encuentran cosas que no habia
				continue;
			}

			$stocktake_item->current_qty		= $stocktake_item->creation_qty;
			$stocktake_item->updated_by_user_id	= $user->id;

			if( ! $stocktake_item->update('current_qty','updated_by_user_id') )
			{
				throw new SystemException('Ocurrio un error por favor intente mas tarde');
			}
		}

		$stocktake_scan_grouped_by_item_id	= ArrayUtils::groupByIndex($stocktake_scan_by_item,'item_id');

		foreach( $stocktake_scan_grouped_by_item_id	 as $item_id=>$ss_array)
		{
			$sum = 0;
			$stocktake_item = null;

			foreach($ss_array as $stocktake_scan)
			{
				$sum += $stocktake_scan->qty;
			}

			if( isset( $stocktake_item_by_item[ $item_id ] ) )
			{
				$stocktake_item = $stocktake_item_by_item[ $item_id ];
				$stocktake_item->current_qty = 'sum';
				$stocktake_item->updated_by_user_id = $user->id;

				if( $stocktake_item->update('current_qty','updated_by_user_id') )
					throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$stocktake_item->getError());
			}
			else
			{
				//Nuevo
			}
		}

		$stocktake->updated_by_user_id = $user->id;
		$stocktake->status = 'CLOSED';
		$stocktake->update('status','updated_by_user_id');
	}

	function approveBill()
	{

		$user = app::getUserFromSession();
		if( $user == null )
			throw new ValidationException('Por favor iniciar sesion');

		$params = $this->getMethodParams();

		$bill = bill::get($params['id'] );

		if( $bill == null )
			throw new ValidationException('No se encontro el recibo');


		$bill->accepted_status = 'ACCEPTED';
		$bill->updated_by_user_id = $user->id;
		$bill->approved_by_user_id = $user->id;
		$bill->update('accepted_status','updated_by_user_id','approved_by_user_id');

		return $this->sendStatus(200)->json( $bill->toArray() );
	}

	function rejectBill()
	{

		$user = app::getUserFromSession();
		if( $user == null )
			throw new ValidationException('Por favor iniciar sesion');

		$params = $this->getMethodParams();

		$bill = bill::get($params['id'] );

		if( $bill == null )
			throw new ValidationException('No se encontro el recibo');


		$bill->accepted_status = 'REJECTED';
		$bill->updated_by_user_id = $user->id;
		$bill->update('accepted_status','updated_by_user_id');

		return $this->sendStatus(200)->json( $bill->toArray() );
	}

	function removeProductionItem()
	{
		$user = app::getUserFromSession();
		if( $user == null )
			throw new ValidationException('Por favor iniciar sesion');

		$params = $this->getMethodParams();

		$production_item = production_item::get( $params['production_item_id'] );

		if( $production_item == null )
		{
			throw new ValidationException("No se encontro la produccion");
		}

		if( !$production_item->delete() )
		{
			throw new SystemException('Ocurrio un error al eliminar el detalle de la produccion. '.$production_item->getError());
		}

		$pallet_array = pallet::search(array('production_item_id'=> $production_item->id),true);
		$box_array = box::search(array('production_item_id'=>$production_item->id),true);

		foreach($pallet_array as $pallet)
		{
			if( !$pallet->delete() )
			{
				throw new SystemException('Ocurrio un error al eliminar las tarimas por favor intente mas tarde. '.$pallet->getError());
			}
		}

		foreach($box_array as $box )
		{
			if( !$box->delete())
			{
				throw new SystemException('Ocurrio un error al eliminar las cajas por favor intente mas tarde. '.$box->getError());
			}
		}

		$this->sendStatus(200)->json(true);
	}

	//Surtir Orden
	function fullfillOrder()
	{

		$user = app::getUserFromSession();
		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		$params = $this->getMethodParams();
		$order = order::get( $params['order_id'] );

		if( $order == null )
		{
			throw new ValidationException('Ocurrio un error  por favor intentar mas tarde. '.DBTable::$connection->error);
		}

		foreach( $params['items'] as $oif)
		{
			$order_item_fullfillment = order_item_fullfillment::createFromArray( $oif );

			if( $order_item_fullfillment->qty == 0 )
			{
				continue;
			}

			$order_item	= order_item::searchFirst(array('order_id'=>$params['order_id'], 'item_id'=>$order_item_fullfillment->item_id, 'is_free_of_charge'=>$order_item_fullfillment->is_free_of_charge ));
			$order_item_fullfillment_array = order_item_fullfillment::search(array('order_id'=>$order->id,'item_id'=>$order_item_fullfillment->item_id,'is_free_of_charge'=>$order_item_fullfillment->is_free_of_charge));

			$fullfilled_qty = 0;

			foreach($order_item_fullfillment_array as $tmp_oif)
			{
				$fullfilled_qty+= $tmp_oif->qty;
			}

			//Checar si ya se despacho anteriormente y que no se entregue cantidad de mas
			if( $fullfilled_qty+$order_item_fullfillment->qty > $order_item->qty )
			{
				throw new ValidationException('La cantidad entregada supera lo especificado en la orden');
			}

			$box_content	=box_content::get( $order_item_fullfillment->box_content_id);

			if( !$order_item )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde','No se encontro el item');
			}

			if( !$box_content )
				throw new SystemException('Ocurrio un error por favor intentar mas tarde','No se encontro el contenedor ');

			if( $box_content->box_id !== $order_item_fullfillment->box_id )
				throw new SystemException('La caja no corresponde');

			if( $box_content->qty < $order_item_fullfillment->qty )
			{
				throw new ValidationException('La caja no contine la cantidad de articulos solicitados');
			}

			if( !$order_item_fullfillment->insertDb() )
			{
				throw new ValidationException('Ocurrio un error por favor intentar mas tarde. '.$order_item_fullfillment->getError());
			}

			/*

			/*/
			if( !empty( $order_item_fullfillment->csv_serial_number_codes ) )
			{
				$codes_ids = explode(',', $order_item_fullfillment->csv_serial_number_codes );
				$serial_number_array = serial_number::search(array('code'=>$codes_ids));

				$this->debug('Serial numbers',$serial_number_array );

				foreach( $serial_number_array as $serial_number )
				{
					//if( $serial_number->status == 'ASIGNED_TO_USER' || $serial_number->status == 'SOLD' )
					//{
					//	throw new ValidationException('El marbete ya se vendio o asigno previamente');
					//}

					if( $serial_number->assigned_to_user_id )
					{
						error_log('El marbete se esta reasignando de "'.$serial_number->assigned_to_user_id.'" a "'.$order->client_user_id.'"');
					}

					if( $order->client_user_id )
					{
						$serial_number->assigned_to_user_id = $order->client_user_id;
						$serial_number->status = 'ASIGNED_TO_USER';
					}
					else
					{
						$serial_number->status = 'SOLD';
					}

					$serial_number->order_id = $order->id;

					if( empty($serial_number->item_id ) )
					{
						$serial_number->item_id = $order_item_fullfillment->item_id;
					}
					$serial_number->updated_by_user_id = $user->id;

					if( !$serial_number->update() )
					{
						throw new SystemException('Ocurrio un error, Por favor intentar mas tarde'.$serial_number->getError());
					}
				}
			}
			//*/

			app::reduceFullfillInfo($order, $order_item_fullfillment, $user );
		}

		//$order->fullfilled_by_user_id = $user->id;
		//$order->update('fullfilled_by_user_id');

		return $this->sendStatus(200)->json(true);
	}

	function removeBox()
	{
		$user = app::getUserFromSession();
		if( !$user )
			throw new SessionException('Por favor iniciar session');

		$params = $this->getMethodParams();

		if( empty( $params['note'] ) )
		{
			throw new ValidationException('La nota no puede estar vacia');
		}


		$box = box::get( $params['box_id'] );

		if( !$box )
			throw new ValidationException('La caja no se encontro');

		$box->status = 'DELETED';

		if( !$box->update('status') )
		{
			throw new SystemException('Ocurrio al actualizar el estatus de la caja por favor intentar mas tarde. '.$box->getError());
		}

		$stocktake = null;

		if( !empty( $params['stocktake'] ) )
		{
			$stocktake = stocktake::get( $params['stocktake_id']);
		}

		$temp_box_content_array  =box_content::search(array('box_id'=>array($box->id)),true);
		$box_content_array = ArrayUtils::removeElementsWithValueInProperty($temp_box_content_array,'qty',0);

		foreach($box_content_array as $box_content)
		{
			if( $box_content->qty > 0 )
			{
				error_log('Removing');
				app::addAllBoxContentMerma($stocktake,$box,$box_content,$params['note'],$user );
			}
			else{
				error_log('Box content doesnt have articles');
			}
		}

		$this->sendStatus(200)->json(true);
	}

	function cancelOrder()
	{
		$user = app::getUserFromSession();

		if( !$user )
			throw new SessionException('Por favor iniciar session');


		$params = $this->getMethodParams();

		if( empty( $params['order_id'] ) )
		{
			throw new ValidationException('El id de la orden esta vacio');
		}

		$order = order::get( $params['order_id'] );

		if( $order == null )
			throw new ValidationException('No se encontro la orden con id "'.$params['order_id'] );

		if( $order->delivery_status == 'DELIVERED' )
		{
			throw new ValidationException('La orden ya ha sido entregada al cliente');
		}

		$order_item_fullfillment_array = order_item_fullfillment::search(array('order_id'=>$params['order_id']),true);

		foreach($order_item_fullfillment_array as $order_item_fullfillment)
		{
			app::addItemsToBox
			(
				$order_item_fullfillment->box_id,
				$order_item_fullfillment->item_id,
				$order_item_fullfillment->qty,
				$user->id,
				'Se regreso por cancelacion de la orden '.$params['order_id']
			);
			if( !$order_item_fullfillment->delete() )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde', $order_item_fullfillment->getError() );
			}
		}


		if( $order->paid_status )
		{
			throw new ValidationException('La orden ya ha sido Pagada');
		}

		$order->status = 'CANCELLED';

		if( !$order->update('status') )
		{
			throw new SystemException('Ocurrio un error al guardar la informacion');
		}

		return $this->sendStatus(200)->json( true );
	}


	function updateBoxContent()
	{
		$user = app::getUserFromSession();
		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		$params = $this->getMethodParams();

		$box_content = box_content::get( $params['id'] );

		if( !$box_content )
			throw new NotFoundException('El contenido de la caja no se encontro');

		app::adjustBoxContent($box_content,$params['qty'],$user);
	}

	function addSerialNumbersToMerma()
	{
		$user = app::getUserFromSession();
		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		$params = $this->getMethodParams();

		foreach( $params['serial_number_ids'] as $id)
		{
			//Fijarse que es id y no code
			$serial_number = serial_number::get( $id );
			app::addSerialNumberToMerma( $serial_number, $user, $params['note']);
		}
	}
	function markSerialNumberAsReturned()
	{
		$user = app::getUserFromSession();
		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		$params = $this->getMethodParams();

		if( empty( $params['serial_number_id'] ) )
		{
			throw new ValidationException('El id no puede estar vacio');
		}
		$serial_number = serial_number::get( $params['serial_number_id'] );
		if( empty( $serial_number) )
		{
			throw new ValidationException('No se encontro el marbete');
		}
		$serial_number->status = 'RETURNED';
		if( ! $serial_number->update('status') )
		{
			throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$serial_number->getError());
		}

		return $this->sendStatus(200)->json( $serial_number->toArray() );
	}

	function saveSerialNumberCodeReturned()
	{
		$user = app::getUserFromSession();
		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		$params = $this->getMethodParams();

		if( empty( $params['serial_number_code'] ) )
		{
			throw new ValidationException('El codigo no puede estar vacio');
		}

		$serial_number = new serial_number();
		$serial_number->type_item_id = '5';
		$serial_number->code = $params['serial_number_code'];
		$serial_number->created_by_user_id	= $user->id;
		$serial_number->updated_by_user_id	= $user->id;
		$serial_number->status = 'RETURNED';
		if( !$serial_number->insert() )
		{
			throw new SystemException('Ocurrio un error por favor intentar mas tarde'.$serial_number->getError());
		}
		return $this->sendStatus(200)->json( $serial_number->toArray() );
	}
}

$l = new Service();
$l->execute();
