<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');



class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		return $this->genericGet("pallet");
	}

	function getInfo($pallet_array)
	{
		return app::getPalletInfo($pallet_array,false);
	}
}
$l = new Service();
$l->execute();
