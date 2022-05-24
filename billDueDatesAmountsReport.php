<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\DBTable;


class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		if( isset( $_GET['id'] ) && !empty( $_GET['id'] ) )
		{
			$bill = bill::get( $_GET['id']  );

			if( $bill )
			{
				return $this->sendStatus( 200 )->json( $bill->toArray() );
			}
			return $this->sendStatus( 404 )->json(array('error'=>'The element wasn\'t found'));
		}


		$constraints = $this->getAllConstraints( bill::getAllPropertiesExcept('organization_id') );
		//$constraints[] = 'bill.organization_id = "'.$organization->id.'"';

		$constraints_str = count( $constraints ) > 0 ? join(' AND ',$constraints ) : '1';

		$time = time()-(7*60*60);
		$now		= date('Y-m-d',$time);
		$next_15	= date('Y-m-d',time()+(15*24*60*60));

		$sql_report = 'SELECT SUM( IF(due_date IS NOT NULL AND due_date <= "'.$now.'" AND paid_status = "PENDING", total-amount_paid, 0 )) AS expired,
			SUM( IF( paid_status = "PENDING" AND due_date > "'.$now.'" AND due_date < "'.$next_15.'", total-amount_paid, 0 )) AS next_15
			FROM `bill`
			WHERE '.$constraints_str;

		//error_log( $sql_report );
		$reportData	= DBTable::getArrayFromQuery( $sql_report );

		return $this->sendStatus( 200 )->json(array('total'=>1, 'data'=>array( $reportData[0] )));
	}
}
$l = new Service();
$l->execute();
