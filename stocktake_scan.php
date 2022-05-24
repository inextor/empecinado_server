<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\DBTable;
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

		return $this->genericGet("stocktake_scan");
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
		return $this->batchUpdate($array);
	}

	function batchUpdate($array)
	{
		$result = array();
		$user = app::getUserFromSession();

		foreach($array as $params)
		{
			if( empty( $params['stocktake_id'] ) )
				throw new ValidationException('El id de la toma de inventario no puede estar vacio');

			if( !empty( $params['item_id'] ) )
			{
				if( empty( $params['qty'] ) )
					throw new ValidationException('La cantidad no puede estar vacia');

				$stocktake_scan =  new stocktake_scan();
				$stocktake_scan->assignFromArray($params,'item_id','stocktake_id','qty');
				$stocktake_scan->created_by_user_id = $user->id;
				$stocktake_scan->updated_by_user_id = $user->id;
				$stocktake_scan->insertDb();
				throw new ValidationException('Revisar implementacion');
				return;
			}
			else if( !empty( $params['pallet_id'] ) )
			{
				$stocktake_scan = stocktake_scan::searchFirst(array('pallet_id'=>$params['pallet_id'],'stocktake_id'=>$params['stocktake_id']));

				if( $stocktake_scan !== null )
				{
					throw new ValidationException('La tarima con id "'.$params['pallet_id'].'" ya ha sido inventariada' );
				}

				$stocktake_scan =  new stocktake_scan();
				$stocktake_scan->assignFromArray($params,'stocktake_id','pallet_id');
				$stocktake_scan->created_by_user_id = $user->id;
				$stocktake_scan->updated_by_user_id = $user->id;
				$stocktake_scan->qty = 1;

				$this->debug('stocktake_id',$stocktake_scan->toArray());
				if( !$stocktake_scan->insertDb() )
				{
					throw new SystemException('Ocurrio un error al guardar la informacion por favor intente de nuevo.'.$stocktake_scan->getError());
				}

				$result[] = $stocktake_scan->toArray();
			}
			else if( !empty( $params['box_id'] ) )
			{
				$pallet_content = pallet_content::searchFirst(array('box_id'=>array($params['box_id']),'status'=>'ACTIVE'));

				if( $pallet_content !== null )
				{
					$this->debug('pallet_content',$pallet_content);
					throw new ValidationException('La caja esta dentro de la tarima "'.$pallet_content->pallet_id.'"');
				}

				$stocktake_scan = stocktake_scan::searchFirst(array('box_id'=>$params['box_id'],'stocktake_id'=>$params['stocktake_id']));

				if( $stocktake_scan !== null )
				{
					throw new ValidationException('La caja con id "'.$params['box_id'].'" ya ha sido inventariada' );
				}

				$stocktake_scan =  new stocktake_scan();
				$stocktake_scan->assignFromArray($params,'stocktake_id','box_id');
				$stocktake_scan->created_by_user_id = $user->id;
				$stocktake_scan->updated_by_user_id = $user->id;
				$stocktake_scan->qty = 1;

				if( !$stocktake_scan->insertDb() )
				{
					throw new SystemException('Ocurrio un error al guardar la informacion por favor intente de nuevo.'.$stocktake_scan->getError());
				}

				$result[] = $stocktake_scan->toArray();
			}
		}
	}
}
$l = new Service();
$l->execute();
