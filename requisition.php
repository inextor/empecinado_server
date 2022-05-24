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
use AKOU\NotFoundException;
use \akou\SessionException;
use \akou\SystemException;


class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		return $this->genericGet("requisition");
	}

	function getInfo( $requisition_array )
	{
		$props					= ArrayUtils		::getItemsProperties($requisition_array,'id','created_by_user_id','required_by_store_id','requested_to_store_id');
		$user_array				= user				::search(array('id'=>$props['created_by_user_id']),true,'id');
		$requisition_item_array = requisition_item	::search(array('requisition_id'=>$props['id'],'status'=>"ACTIVE"),false,'id');
		$items_ids 				= ArrayUtils		::getItemsProperty($requisition_item_array,'item_id',TRUE);
		$item_array				= item				::search( array( 'id'=> $items_ids ), false, 'id' );
		$category_ids			= ArrayUtils		::getItemsProperty( $item_array, 'category_id', TRUE );
		$category_array			= category			::search( array('id'=>$category_ids),false,'id');
		$store_array			= store				::search( array('id'=>array_merge($props['requested_to_store_id'],$props['required_by_store_id'])),false,'id');

		$result						= array();
		$user_props					= user::getAllPropertiesExcept('password');
		$requisition_items_grouped	= ArrayUtils::groupByIndex($requisition_item_array,'requisition_id');

		foreach($requisition_array as $requisition)
		{
			$temp_items_array = $requisition_items_grouped[ $requisition['id']	]??array();
			$items = array();

			foreach($temp_items_array as $requisition_item )
			{
				$item = $item_array[ $requisition_item['item_id'] ];
				$category = $category_array[ $item['category_id'] ]??null;

				$items[] = array(
					'requisition_item'	=>$requisition_item,
					'item'				=> $item,
					'category'			=> $category
				);
			}

			$user = $user_array[ $requisition['created_by_user_id'] ];

//			$this->debug('Reuiqitiohns',$requisition );

			$result[] = array
			(
				'requisition'	=> $requisition,
				'requested_to_store' => $store_array[$requisition['requested_to_store_id']],
				'required_by_store' => $store_array[$requisition['required_by_store_id']],
				'user'			=> $user->toArray( $user_props ),
				'items'			=> $items
			);

		}
		return $result;
	}

	function post()
	{
		$this->setAllowHeader();
		$params = $this->getMethodParams();
		app::connect();
		DBTable::autocommit(false );

		try
		{
			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			$is_assoc	= $this->isAssociativeArray( $params );
			$result		= $this->batchInsert( $is_assoc  ? array($params) : $params );
			DBTable::commit();
			return $this->sendStatus( 200 )->json( $is_assoc ? $result[0] : $result );
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

	function put()
	{
		$this->setAllowHeader();
		$params = $this->getMethodParams();
		app::connect();
		DBTable::autocommit( false );

		try
		{
			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			$is_assoc	= $this->isAssociativeArray( $params );
			$result		= $this->batchUpdate( $is_assoc  ? array($params) : $params );
			DBTable::commit();
			return $this->sendStatus( 200 )->json( $is_assoc ? $result[0] : $result );
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

	function batchInsert($array)
	{

		$user = app::getUserFromSession();
		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		$result = array();
		$requisition_props = requisition::getAllPropertiesExcept('id','created_by_user_id','updated_by_user_id','created','updated');


		foreach($array as $requisition_info)
		{
			$requsition = new requisition();
			$requsition->assignFromArray($requisition_info['requisition'],$requisition_props);
			$requsition->created_by_user_id = $user->id;

			//$this->debug('requisitno',$requisition_info);
			//$this->debug('requisitno2',$requsition->toArray());

			if( !$requsition->insert() )
				throw new SystemException('Ocurrio un error por favor intentar mas tarde'.$requsition->getError());

			if( empty( $requisition_info['items'] ) )
				throw new ValidationException('No se especifico ningun item para la requisicion');

			foreach($requisition_info['items'] as $item_info)
			{
				if( empty( $item_info['requisition_item']) )
				{
					throw new ValidationException('La informacion del item requerido no puede estar vacia');
				}

				if( empty($item_info['requisition_item']['qty']) )
				{
					throw new ValidationException('La cantidad de el item no puede estar vacia');
				}

				if( $item_info['item_id'] )
					throw new ValidationException('El id del item no puede estar vacio');

				$requisition_item = new requisition_item();
				$requisition_item->status = 'ACTIVE';
				$requisition_item->item_id = $item_info['requisition_item']['item_id'];
				$requisition_item->qty	= $item_info['requisition_item']['qty'];
				$requisition_item->requisition_id = $requsition->id;

				if( !$requisition_item->insert() )
				{
					throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$requisition_item->getError());
				}
			}

			$result[] = $requsition->toArray();
		}
		return $result;
	}


	function batchUpdate($array)
	{

		$user = app::getUserFromSession();
		if( !$user )
			throw new SessionException('Por favor iniciar sesion');

		$result = array();
		$requisition_props = requisition::getAllPropertiesExcept('created_by_user_id','updated_by_user_id','created','updated');

		foreach($array as $requisition_info)
		{
			if( empty( $requisition_info['requisition']['id']) )
			{
				throw new ValidationException('El id de la requisicion no puede estar vacio en actualizacion');
			}

			$requisition = requisition::get( $requisition_info['requisition']['id'] );

			if( $requisition == null )
				throw new NotFoundException('No se encontro la requisicion con id "'.$requisition_info['requisition']['id'].'"');


			if( $requisition_info['requisition']['status'] == 'CANCELLED' && $requisition->status == 'SHIPPED' )
			{
				throw new ValidationException('La requisition no puede ser cancelada ya fue enviada');
			}

			$requisition->assignFromArray($requisition_info['requisition'],$requisition_props);

			if( ! $requisition->update($requisition_props) )
			{
				throw new SystemException('OCurrio un error al actualizar la requisision');
			}

			if( empty( $requisition_info['items'] ) )
				throw new ValidationException('No se especifico ningun item para la requisicion');

			$requisition_items_ids = array();

			foreach($requisition_info['items'] as $item_info)
			{
				if( empty( $item_info['requisition_item']) )
				{
					throw new ValidationException('La informacion del item requerido no puede estar vacia');
				}

				if( empty( $item_info['requisition_item']['qty'] ) )
				{
					throw new ValidationException('La cantidad de el item no puede estar vacia');
				}

				if( empty( $item_info['requisition_item']['item_id'] ) )
					throw new ValidationException('El id del item no puede estar vacio');

				error_log('is empty '.print_r( $item_info['requisition_item']['id'],true ) );

				$requisition_item = isset( $item_info['requisition_item']['id'] ) ? requisition_item::get( $item_info['requisition_item']['id'] ) : new requisition_item();

				if( $requisition_item == null )
				{
					//Solo puede ocurrir cuando trae id y es una actualizacion
					throw new ValidationException('Ocurrio un error no se encontro el item con id "'.$item_info['requisition_item']['id']);
				}

				$requisition_item->status = 'ACTIVE';
				$requisition_item->item_id = $item_info['requisition_item']['item_id'];
				$requisition_item->qty	= $item_info['requisition_item']['qty'];
				$requisition_item->requisition_id = $requisition->id;

				if( empty( $requisition_item->id ) )
				{
					if( !$requisition_item->insert() )
					{
						throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$requisition_item->getError());
					}
				}
				else if( !$requisition_item->update() )
				{
					throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$requisition_item->getError() );
				}

				$requisition_items_ids[] = $requisition_item->id;
			}

			if( !empty( $requisition_items_ids ) )
			{
				//Nunca deberia estar vacia pero aun asi hacemos la comparacion
				$sql = 'UPDATE requisition_item SET status = "DELETED" WHERE requisition_id = "'.$requisition->id.'" AND id NOT IN ('.DBTable::escapeArrayValues( $requisition_items_ids).')';
				DBTable::query($sql);
			}


			$result[] = $requisition->toArray();
		}

		return $result;
	}
}
$l = new Service();
$l->execute();
