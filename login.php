<?php

namespace APP;

include_once( __DIR__.'/app.php' );

use \akou\Utils;
use \akou\DBTable;

class LoginController extends SuperRest
{
	function get()
	{
		//App::connect();

		//if( $_GET['type'] === 'facebook' )
		//{
		//	header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
		//	$fb = new Facebook\Facebook([
		//		'app_id' => '{app-id}', // Replace {app-id} with your app id
		//		'app_secret' => '{app-secret}',
		//		'default_graph_version' => 'v3.2',
		//	]);

		//	$loginUrl = $helper->getLoginUrl('https://example.com/fb-callback.php', $permissions);
		//	return $this->sendStatus(200)->json( array('login_url'=>$login_url) );
		//}

		//return $team->load()
		//	? $this->sendStatus(200)->json( $team->toArray() )
		//	: $this->sendStatus(404)->json( Array('error'=>'Not Found') );
	}

	function post()
	{
		$this->setAllowHeader();
		app::connect();
		session_start();

		$user = new user();
		$params = $this->getMethodParams();
		//$organization = app::getOrganizationFromDomain();
		//$user->assignFromArray($params,'username','password');
		$user->username = strtolower( $params['username'] );
		$user->password = $params['password'];
		//$user->organization_id = $organization->id;


		if( !$user->load(false) )
		{
			return $this->sendStatus(404)->json( Array('error'=>'The user doesn\'t exists or the password is incorrect','query'=>$user->getLastQuery()) );
		}

		$session				= new session();
		$session->id			= app::getRandomString(16);
		$session->user_id		= $user->id;
		$session->status		= 'ACTIVE';
		$session->created		= date('Y-m-d h:s:i');

		if( !$session->insertDb() )
		{
			return $this->sendStatus(400)->json(array("error"=>"An error occurred please try again later",'debug'=>$session->getLastQuery()));
		}

		$user_permission = user_permission::searchFirst(array('user_id'=>$user->id),false);
		if( !$user_permission )
		{
			$user_permission = array('user_id'=>$user->id);
		}

		$response = array( "user"=> $user->toArrayExclude('password'),"session"=>$session->toArray(),'user_permission'=>$user_permission);

		return $this->sendStatus(200)->json( $response );
	}
}

$l = new LoginController();
$l->execute();
