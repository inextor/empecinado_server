<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\DBTable;
use \akou\ArrayUtils;
use \akou\SessionException;

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
				$result = $this->getInfo(array($bill->toArray()));
				return $this->sendStatus( 200 )->json( $result[0] );
			}
			return $this->sendStatus( 404 )->json(array('error'=>'The element wasn\'t found'));
		}

		//$organization = app::getOrganizationFromDomain();

		$constraints = $this->getAllConstraints( bill::getAllPropertiesExcept('organization_id') );

		if( count( $constraints ) == 0 )
		{
			//$constraints[] = 'paid_status = "PENDING"';
		}

		//$constraints[] = 'organization_id = "'.$organization->id.'"';

		$constraints_str = count( $constraints ) > 0 ? join(' AND ',$constraints ) : '1';
		$pagination	= $this->getPagination();

		$sql_bills	= 'SELECT SQL_CALC_FOUND_ROWS bill.*
			FROM `bill`
			WHERE '.$constraints_str.'
			ORDER BY -due_date ASC,total DESC
			LIMIT '.$pagination->limit.'
			OFFSET '.$pagination->offset;


		//error_log( $sql_bills );

		$result	= DBTable::getArrayFromQuery( $sql_bills );
		$total	= DBTable::getTotalRows();

		$info	= $this->getInfo( $result );

		$sql_report = 'SELECT count(*) as total, MAX(total) AS max,MIN( total ) AS min, sum( total ) AS sum ,sum(amount_paid) amount_paid
			FROM `bill`
			WHERE '.$constraints_str;


		$reportData	= DBTable::getArrayFromQuery( $sql_report );
		$report	= $reportData[0];

		return $this->sendStatus( 200 )->json
		(
			array
			(
				'total'	=>$total,
				'min'	=>$report['min'],
				'max'	=>$report['max'],
				'sum'	=>$report['sum'],
				'amount_paid'	=>$report['amount_paid'],
				'data'	=>$info
			)
		);
	}

	function getInfo($array)
	{
		$as_object = FALSE;
		$props					= ArrayUtils::getItemsProperties($array,'id','provider_user_id','paid_by_user_id','approved_by_user_id','invoice_attachment_id','receipt_attachment_id','pdf_attachment_id','organization_id');
		$user					= app::getUserFromSession();

		if( $user == null )
			throw new SessionException('Por favor iniciar sesion');

		$user_ids				= array_merge($props['provider_user_id'],$props['paid_by_user_id'],$props['approved_by_user_id']);
		$user_array			= user::search(array('id'=>$user_ids ),$as_object,'id');

		$organization_array			= organization::search(array('id'=>$props['organization_id']),false,'id');
		$bank_movement_bill_array	= bank_movement_bill::search(array('bill_id'=>$props['id']),false,'id');
		$bank_movement_ids		= ArrayUtils::getItemsProperty($bank_movement_bill_array,'bank_movement_id');
		$bank_movement_array	= bank_movement::search(array('id'=>$bank_movement_ids),false,'id');

		$bank_account_ids		= ArrayUtils::getItemsProperty( $bank_movement_array, 'bank_account_id' );
		$bank_account_array 	= bank_account::search(array('id'=>$bank_account_ids ),false,'id');

		$grouped_bmb_array		= ArrayUtils::groupByProperty($bank_movement_bill_array,'bill_id');
		$receipt_attachment_ids	= ArrayUtils::getItemsProperty( $bank_movement_array, 'receipt_attachment_id' );

		$attach_ids				= array_merge($props['invoice_attachment_id'],$props['receipt_attachment_id'],$props['pdf_attachment_id'],$receipt_attachment_ids );
		$attachment_array		= attachment::search(array('id'=>$attach_ids ), $as_object, 'id');

		$result					= array();


		foreach($array as $bill)
		{
			$bmb_array			= isset( $grouped_bmb_array[ $bill['id'] ] ) ? $grouped_bmb_array[ $bill['id'] ] : array();

			$bank_movement_info_array = array();

			foreach($bmb_array as $bank_movement_bill )
			{
				$bank_movement = $bank_movement_array[ $bank_movement_bill['bank_movement_id'] ];
				$attachment	= null;

				$bank_account	= $bank_movement['bank_account_id'] ? $bank_account_array[ $bank_movement['bank_account_id'] ] : null;

				if( $bank_movement['receipt_attachment_id'] )
				{
					$attachment = $attachment_array[ $bank_movement['receipt_attachment_id'] ];
				}

				$bank_movement_info_array[] = array(
					'bank_movement'			=> $bank_movement,
					'receipt_attachment'	=> $attachment,
					'bank_account'			=> $bank_account
				);
			}

			$invoice_attachment = $bill['invoice_attachment_id'] ? $attachment_array[ $bill['invoice_attachment_id'] ] : null;
			$receipt_attachment = $bill['receipt_attachment_id'] ? $attachment_array[ $bill['receipt_attachment_id'] ] : null;
			$pdf_attachment 	= $bill['pdf_attachment_id'] ? $attachment_array[ $bill['pdf_attachment_id'] ] : null;

			$bill_info = array
			(
				'bill'				=> $bill,
				'organization'		=> $bill['organization_id'] ? $organization_array[ $bill['organization_id'] ] : null,
				'bank_movements_info'	=> $bank_movement_info_array,
				'invoice_attachment'	=> $invoice_attachment,
				'receipt_attachment'	=> $receipt_attachment,
				'pdf_attachment'		=> $pdf_attachment,
			);

			if( $bill['provider_user_id'] )
				$bill_info['provider'] = $user_array[	$bill['provider_user_id'] ];

			if( !empty( $bill['approved_by_user_id']) )
				$bill_info['approved_by_user'] = $user_array[ $bill['approved_by_user_id'] ];

			if( !empty( $bill['paid_by_user_id'] ) )
				$bill_info['paid_by_user'] = $user_array[ $bill['paid_by_user_id'] ];

			$bank_account = !empty( $bill['bank_account_id'] ) ? $bank_account_array[  $bill['bank_account_id']  ] : null;
			$bill_info['bank_account'] =  $bank_account;
			$result[]	= $bill_info;
		}

		return $result;
	}
}
$l = new Service();
$l->execute();
