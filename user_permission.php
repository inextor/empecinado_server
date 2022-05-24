<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\DBTable;
use \akou\ValidationException;
use \akou\LoggableException;
use \akou\SessionException;
use AKOU\SystemException;

class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		try
		{
			$this->checkPermission();
			if( !empty( $_GET['id'] ) )
			{
				$user = user::get( $_GET['id'] );

				if( $user )
				{
					$user_permission = user_permission::searchFirst(array('user_id'=>$_GET['id']));
					if( !$user_permission )
					{
						$user_permission = array('user_id'=> $user->id);
					}
					return $this->sendStatus(200)->json($user_permission);
				}
			}

			return $this->genericGet("user_permission");
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

	function checkPermission()
	{
		$user = app::getUserFromSession();
		if( !$user )
		{
			throw new SessionException('Por favor iniciar session');
		}

		$user_permission =  user_permission::searchFirst(array('user_id'=>$user->id));

		if( !$user_permission || !$user_permission->add_user )
		{
			throw new ValidationException('No cuentas con los permisos necesarios, por favor consultar con el administrador');
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
			$this->checkPermission();

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

	function batchUpdate($user_permission_array)
	{
		$result = array();
		$this->checkPermission();
		$props_new = user_permission::getAllPropertiesExcept('created','updated','created_by_user_id');
		$props_update	= user_permission::getAllPropertiesExcept('created','updated');
		$user = app::getUserFromSession();

		foreach( $user_permission_array as $params )
		{
			$user_permission = new user_permission();
			$user_permission->user_id = $params['user_id'];
			$user_permission->setWhereString('false');

			if( !$user_permission->load() )
			{
				$user_permission->assignFromArray( $params, $props_new );
				$user_permission->created_by_user_id = $user->id;
				$user_permission->updated_by_user_id = $user->id;

				if( !$user_permission->insert() )
				{
					throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$user_permission->getError());
				}
			}
			else
			{
				$user_permission->assignFromArray( $params );
				$user_permission->updated_by_user_id = $user->id;

				if( !$user_permission->update( $props_update ) )
				{
					throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$user_permission->getError());
				}
			}

			if( $notification_token )
			{
				$push_notification = new push_notification();
				$push_notification->user_id = $user_permission->user_id;
				$push_notification->title = 'Permisos fueron modificados';
				$push_notification->object_type = 'user_permission';
				$push_notification->object_id	= $user_permission->user_id;

				if( !$push_notification->insert() )
				{
					error_log('Ocurrio un error no grave en los permisos '.$push_notification->getLastQuery());
				}
				else
				{
					app::sendNotification($push_notification);
				}
			}


			$result[] = $user_permission->toArray();
		}

		return $result;
	}
}
$l = new Service();
$l->execute();
