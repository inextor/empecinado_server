<?php

namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\DBTable;
use \akou\ValidationException;
use \akou\LoggableException;
use AKOU\SystemException;

class Service extends SuperRest
{

	function post()
	{
		$this->setAllowHeader();
		$params = $this->getMethodParams();
		app::connect();
		DBTable::autocommit(false );


		try
		{
			$user = new user();
			$user_props	= user::getAllPropertiesExcept('id','created','updated','type','credit_limit');
			$user->assignFromArray($params['user'],$user_props);
			$user->username = $user->email;
			$user->type = 'USER';

	//		$this->debug( 'Username', $params );

			if( empty( $user->password ) )
			{
				throw new ValidationException('El passoword no puede estar vacio');
			}

			if( !$user->insert())
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde'.$user->getError());
			}

			$user_permission = new user_permission();
			$user_permission->is_provider = 1;
			$user_permission->user_id = $user->id;

			if( !$user_permission->insert())
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde'.$user_permission->getError());
			}

			//$address = new address();
			//$address->assignFromArray($params['address']);
			//$address->user_id = $user->id;

			//if( !$address->insert())
			//{
			//	throw new SystemException('Ocurrio un error por favor intentar mas tarde'.$address->getError());
			//}


			$bank_account = new bank_account();
			$bank_account->assignFromArray($params['bank_account']);
			$bank_account->user_id = $user->id;

			if( !$bank_account->insert())
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde'.$bank_account->getError());
			}

			DBTable::commit();
			return $this->sendStatus( 200 )->json( true );
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
}

$l = new Service();
$l->execute();


