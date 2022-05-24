<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\DBTable;
use \akou\ValidationException;
use \akou\LoggableException;


class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		$extra_joins = '';


		//Por alguna razon llega com _ en vez de .
		if( !empty( $_GET['user_permission_is_provider'] ) || !empty( $_GET['user_permission.is_provider'] ) )
		{
			$extra_joins = 'JOIN user_permission ON user_permission.user_id = user.id AND user_permission.is_provider = 1';
		}

		if( !empty( $_GET['con_rfc'] ) )
		{
			$extra_joins .= ' JOIN address ON address.user_id = user.id AND address.rfc IS NOT NULL ';
		}

		$this->is_debug = false;
		return $this->genericGet('user',[],$extra_joins,[]);
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
		$preferences = preferences::get( 1 );
		if( !$preferences || empty( $preferences->default_price_type_id ) )
		{
			$this->debug('preferences',$preferences);
			throw new ValidationException('El precio por default no esta configurado, comunicarse con el administrador');
		}

		$system_values = array('price_type_id'=> $preferences->default_price_type_id );
		return $this->genericInsert($array,"user",array(),$system_values);
	}

	function batchUpdate($array)
	{
		$insert_with_ids = false;
		return $this->genericUpdate($array, "user", $insert_with_ids );
	}
}
$l = new Service();
$l->execute();
