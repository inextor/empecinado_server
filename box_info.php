<?php

namespace APP;

use AKOU\DBTable;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

//use \akou\Utils;
//use \akou\DBTable;
//use \akou\RestController;
//use \akou\ArrayUtils;
//use \akou\ValidationException;
//use \akou\LoggableException;
//use \akou\SystemException;
//use \akou\SessionException;


class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		$extra_joins = '';

		if( !empty( $_GET['item_id']) )
			$extra_joins = ' JOIN box_content ON box.id = box_content.box_id AND item_id  = "'.DBTable::escape($_GET['item_id']).'" AND box_content.qty > 0';

		return $this->genericGet("box",array(),$extra_joins);
	}

	function getInfo($box_array)
	{
		return app::getBoxInfo( $box_array );
	}
}
$l = new Service();
$l->execute();
