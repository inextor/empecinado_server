<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');
include_once( __DIR__.'/akou/src/LoggableException.php');
include_once( __DIR__.'/schema.php');

use \akou\DBTable;
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

		return $this->genericGet("production_item");
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

		$results = array();

		$user = app::getUserFromSession();

		if( $user == null )
			throw new SessionException("Por favor iniciar sesion");

		foreach($array as $params )
		{
			$except = array('id','created','updated','tiempo_creacion','tiempo_actualizacion','updated_by_user_id','created_by_user_id');
			$properties = production_item::getAllPropertiesExcept( $except );

			$production_item = new production_item();
			$production_item->assignFromArray( $params, $properties );
			$production_item->unsetEmptyValues( DBTable::UNSET_BLANKS );
			$production_item->created_by_user_id = $user->id;
			$production_item->updated_by_user_id = $user->id;

			if( !$production_item->insert() )
			{
				throw new ValidationException('An error Ocurred please try again later',$production_item->_conn->error );
			}

			$production_item->load(true);
			$production = production::get( $production_item->production_id );
			if( !$production )
				throw new SystemException('Ocurrio un error por favor intentar mas tarde','No deveria ocurrir nunca');

			app::addProductionItem( $production_item, $production, $user );
			$pallet_counter = 0;

			if( $production_item->box_type_item_id )
			{
				$boxes_qty = floor( $production_item->qty/$production_item->items_per_box);

				$pallet = null;

				for($i=0;$i<$boxes_qty;$i++)
				{
					$box = new box();
					$box->production_item_id = $production_item->id;
					$box->store_id = $production->store_id;
					$box->type_item_id = $production_item->box_type_item_id;

					if( !$box->insertDb() )
						throw new ValidationException('Ocurrio un error al crear la caja '.$box->getError());

					$box_content = new box_content();
					$box_content->box_id = $box->id;
					$box_content->qty	= $production_item->items_per_box;
					$box_content->item_id = $production_item->item_id;
					$box_content->initial_qty = $production_item->items_per_box;

					if( !$box_content->insertDb()  )
						throw new SystemException('Ocurrio un error al crear las cajas por favor intente mas tarde '.$box_content->getError() );

					if( $production_item->boxes_per_pallet )
					{
						if( $pallet_counter == 0  )
						{
							$pallet = new pallet();
							$pallet->production_item_id = $production_item->id;
							$pallet->created_by_user_id = $user->id;
							$pallet->updated_by_user_id = $user->id;
							$pallet->store_id	= $production->store_id;

							if( !$pallet->insertDb() )
							{
								throw new SystemException('Ocurrio un error por favor intente mas tarde'.$pallet->getError());
							}
						}

						$pallet_content = new pallet_content();
						$pallet_content->pallet_id = $pallet->id;
						$pallet_content->box_id = $box->id;


						if( !$pallet_content->insertDb() )
						{
							throw new SystemException('Ocurrio un error por favor intenter mas tarde. '.$pallet_content->getError());
						}
						$pallet_counter++;

						if( $pallet_counter == $production_item->boxes_per_pallet )
							$pallet_counter = 0;
					}
				}

				$diff = $production_item->qty - ($production_item->items_per_box*floor( $boxes_qty ));

				//Agregando el parcial
				if( $diff > 0 )
				{
					$box = new box();
					$box->production_item_id = $production_item->id;
					$box->type_item_id = $production_item->box_type_item_id;
					$box->store_id	= $production->store_id;

					if( !$box->insertDb() )
						throw new ValidationException('Ocurrio un error al crear la caja '.$box->getError());

					$box_content = new box_content();
					$box_content->box_id = $box->id;
					$box_content->qty	= $diff;
					$box_content->item_id = $production_item->item_id;
					$box_content->initial_qty = $diff;

					if( !$box_content->insertDb() )
						throw new SystemException('Ocurrio un error al crear las cajas por favor intente mas tarde '.$box_content->getError() );

					if( $production_item->boxes_per_pallet )
					{
						if( $pallet_counter == 0  )
						{
							$pallet = new pallet();
							$pallet->created_by_user_id = $user->id;
							$pallet->updated_by_user_id = $user->id;
							$pallet->production_item_id = $production_item->id;
							$pallet->store_id			= $production->store_id;

							if( !$pallet->insertDb() )
							{
								throw new SystemException('Ocurrio un error por favor intente mas tarde'.$pallet->getError());
							}
						}

						$pallet_content = new pallet_content();
						$pallet_content->pallet_id = $pallet->id;
						$pallet_content->box_id = $box->id;

						if( !$pallet_content->insertDb() )
						{
							throw new SystemException('Ocurrio un error por favor intenter mas tarde. '.$pallet_content->getError());
						}

						$pallet_counter++;

						if( $pallet_counter == $production_item->boxes_per_pallet )
							$pallet_counter = 0;
					}
				}
			}

			$results [] = $production_item->toArray();
		}

		return $results;
	}
}
$l = new Service();
$l->execute();
