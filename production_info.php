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

		try
		{
			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			//$extra_constraints = array('production.empresa_id'=>$user->empresa_id);
			return $this->genericGet("production");//, $extra_constraints);
		}
		catch(LoggableException $e)
		{
			return $this->sendStatus( $e->code )->json(array("error"=>$e->getMessage()));
		}
		catch(\Exception $e)
		{
			return $this->sendStatus( 500 )->json(array("error"=>$e->getMessage()));
		}
	}

	function getInfo($production_array)
	{
		$production_ids= ArrayUtils::getItemsProperty($production_array,'id');

		if( empty( $production_ids) )
			return array();

		//$sql = 'SELECT botella.production_id,
		//			botella.etiqueta_id,
		//			SUM( IF( botella.marbete_id IS NULL AND estatus = "EN_ALMACEN", 1,0 ) ) AS sin_marbete,
		//			SUM( IF( botella.marbete_id IS NOT NULL AND estatus = "EN_ALMACEN", 1, 0 )) AS con_marbete,
		//			SUM( IF( botella.marbete_id IS NOT NULL AND estatus = "DESTRUIDO", 1, 0 )) AS destruidos,
		//			-- MAX( CASE WHEN botella.marbete_id IS NULL THEN botella.codigo_botella END) AS ultimo_dispoible,
		//			MIN( CASE WHEN botella.marbete_id IS NULL THEN botella.codigo_botella END) AS primer_botella_sin_marbete
		//	FROM botella
		//	WHERE botella.production_id IN ('.DBTable::escapeArrayValues($production_ids).')
		//	GROUP BY botella.production_id';

		//$disponibilidad = DBTable::getArrayFromQuery( $sql, 'production_id' );

		//$this->debug('Disponibilidad',$disponibilidad);
		$result = array();

		$production_item_array = production_item::searchGroupByIndex(array('production_id'=>$production_ids),false,'production_id');

		foreach($production_array as $production)
		{
			$items = empty($production_item_array[ $production['id'] ]) ? array() : $production_item_array[ $production['id'] ];
			$result[] = array
			(
				'production'=>$production,
				'items'=>$items,
				'disponibilidad'=> array('sin_marbete'=>0) //isset( $disponibilidad[ $production['id'] ] ) ? $disponibilidad[ $production['id'] ] : null
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

	function batchInsert($production_info_array)
	{

		$props = production::getAllPropertiesExcept('created','updated','id','created_by_user_id','updated_by_user_id');
		$pitem_props = production_item::getAllPropertiesExcept('created','updated','id','created_by_user_id','updated_by_user_id');
		$result = array();

		foreach($production_info_array as $pi)
		{
			$production	= new production();
			$production->assignFromArray( $pi['production'], $props);
			if( !$production->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intente mas tarde. '. $production->_conn->error);
			}

			foreach($pi['items'] as $p_item)
			{
				$production_item = new production_item();
				$production_item->assignFromArray($p_item,$pitem_props);
				$production_item->production_id = $production->id;
				if( !$production_item->insertDb() )
					throw new SystemException("Ocurrio un error por favor intentar mas tarde. ". $production_item->_conn->error);
			}
		}
		$result[] = $production;
	}
}

$l = new Service();
$l->execute();
