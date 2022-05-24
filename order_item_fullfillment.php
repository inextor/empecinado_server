<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/SuperRest.php');

class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		return $this->genericGet("order_item_fullfillment");
	}
}
$l = new Service();
$l->execute();
