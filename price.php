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

		return $this->genericGet("price");
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


		foreach($array as $params )
		{
			$properties = price::getAllPropertiesExcept('created','updated','id','updated','created_by_user_id','updated_by_user_id');

			$price = new price();
			$price->store_id = $params['store_id'];
			$price->item_id = $params['item_id'];
			$price->price_type_id = $params['price_type_id'];
			$price->setWhereString(false);

			if( !$price->load() )
			{
				$price->assignFromArray($params, $properties );
				$price->created_by_user_id = $user->id;

				if( !$price->insert() )
				{
					throw new ValidationException('An error Ocurred please try again later',$price->_conn->error );
				}
			}
			else
			{
				$price->setWhereString(true);
				$price->assignFromArray($params, $properties );
				$price->updated_by_user_id = $user->id;

				if( !$price->update( $properties ) )
				{
					throw new ValidationException('An error Ocurred please try again later',$price->_conn->error );
				}
			}

			$results [] = $price->toArray();
		}

		return $results;
	}
}
$l = new Service();
$l->execute();
