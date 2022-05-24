<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use AKOU\ArrayUtils;
use AKOU\DBTable;
use AKOU\LoggableException;
use AKOU\SystemException;
use AKOU\ValidationException;
use Exception;

class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		$user = app::getUserFromSession();
		if( $user == null )
		{
			return $this->sendStatus( 401 )->json(array('error'=>'Please Login'));
		}

		if( isset( $_GET['id'] ) && !empty( $_GET['id'] ) )
		{
			$bank_movement = bank_movement::get( $_GET['id']  );

			if( $bank_movement )
			{
				$result = $this->getInfo( array( $bank_movement->toArray()) );
				return $this->sendStatus( 200 )->json( $result[0] );
			}
			return $this->sendStatus( 404 )->json(array('error'=>'The element wasn\'t found'));
		}

		$constraints = $this->getAllConstraints( bank_movement::getAllProperties() );

		$organization						= app::getOrganizationFromDomain();
		$constraints[] = 'organization_id = "'.DBTable::escape($organization->id).'"';
		$constraints_str = count( $constraints ) > 0 ? join(' AND ',$constraints ) : '1';
		$pagination	= $this->getPagination();

		$sql_bank_movements	= 'SELECT SQL_CALC_FOUND_ROWS bank_movement.*
			FROM `bank_movement`
			WHERE '.$constraints_str.'
			ORDER BY paid_date
			LIMIT '.$pagination->limit.'
			OFFSET '.$pagination->offset;
		$result	= DBTable::getArrayFromQuery( $sql_bank_movements );
		$total	= DBTable::getTotalRows();


		$info = $this->getInfo( $result );
		return $this->sendStatus( 200 )->json(array("total"=>$total,"data"=>$info));
	}

	function post()
	{
		$this->setAllowHeader();
		$params = $this->getMethodParams();
		app::connect();
		$this->saveReplay();

		$user = app::getUserFromSession();

		if( !$user )
			return $this->sendStatus( 401 )->json(array('error'=>'Your session has expire'));


		if( empty( $params['bank_movement']['paid_date'] ) ) return $this->sendStatus( 401 )->json(array('error'=>'Paid date cant be empty'));

		DBTable::autocommit(false );

		try
		{

			$bank_movement = new bank_movement();
			$properties = bank_movement::getAllPropertiesExcept('created','updated','id');
			$bank_movement->assignFromArray( $params['bank_movement'], $properties );
			$bank_movement->organization_id = $user->organization_id;
			$bank_movement->created_by_user_id	= $user->id;

			if(! $bank_movement->insertDb() )
			{
				//error_log( $bank_movement->getLastQuery().print_r( $bank_movement->toArray(), true ).print_r( $user->toArray(), true ) );
				throw new SystemException("An error occurred please try again later");
			}

			//$this->debugArray('bank movement inserted',$bank_movement );

			if( $bank_movement->type == 'expense' )
			{
				$this->addBankMovementExpense( $bank_movement, $params['bank_movement_bills_info'] );
			}
			elseif( $bank_movement->type == 'income' )
			{
				$this->addBankMovementIncome( $bank_movement, $params['bank_movement_invoices_info'] );
			}
			else
			{
				throw new ValidationException('Type must be "income" or "expense"');
			}


			$result = $this->getInfo(array( $bank_movement->toArray() ));

			DBTable::commit();

			if( $bank_movement->bank_account_id )
			{
				$bank_account = bank_account::get( $bank_movement->bank_account_id );
				app::updateBalances( $bank_account );
			}


			return $this->sendStatus( 200 )->json( $result[0] );
		}
		catch(LoggableException $e)
		{
			DBTable::rollback();
			return $this->sendStatus( $e->code )->json(array("error"=>$e->getMessage()));
		}
		catch(Exception $e)
		{
			DBTable::rollback();
			return $this->sendStatus( 500 )->json(array("error"=>$e->getMessage()));
		}
	}

	function addBankMovementExpense($bank_movement, $bank_movement_bills_info)
	{

		$user = app::getUserFromSession();

		$bank_movement->load(true);

		$bill_ids	= array();

		//error_log('Bank movement investment is'.$bank_movement->investment_id.'empty'.(empty($bank_movement->investment_id)?'YES':'NO' ));
		if( $bank_movement->type =='expense' AND empty( $bank_movement_bills_info ) )
		{
			throw new ValidationException('You must specify at least one bill');
		}

		foreach($bank_movement_bills_info as $ebi)
		{
			$bill_ids[] = $ebi['bill']['id'];
		}

		if($bank_movement->type =='expense' AND count($bill_ids) == 0 )
			throw new ValidationException('you must specify at least on bill');

		//Posible query con problemas de compatibilidad hacia el futuro el sort - no esta documentaod
		//https://stackoverflow.com/questions/2051602/mysql-orderby-a-number-nulls-last

		$bill_sql	= 'SELECT * FROM bill WHERE id in ('.DBTable::escapeArrayValues( $bill_ids ).') ORDER BY -due_date ASC FOR UPDATE';

		$bill_array	= count( $bill_ids ) == 0 ? array() : bill::getArrayFromQuery( $bill_sql );
		$remaining_amount = $bank_movement->amount;

		foreach($bill_array as $b)
		{
			if( $b->paid_status == 'PAID' )
				throw new ValidationException('One of the bills is already paid');

			//if( $b->organization_id != $user->organization_id )
			//	throw new SystemException('This action will be reported ','Intrusion alert'.print_r( $params,true));

			$bill_remaining = $b->total - $b->amount_paid;

			$bank_movement_bill = new bank_movement_bill();
			$bank_movement_bill->bank_movement_id = $bank_movement->id;
			$bank_movement_bill->amount	= min( $remaining_amount, $bill_remaining );
			$bank_movement_bill->bill_id	= $b->id;

			$remaining_amount 	-= $bank_movement_bill->amount;


			if( $b->total == ($b->amount_paid+ $bank_movement_bill->amount) )
			{
				$b->paid_status			= 'PAID';
				$b->paid_date			= $bank_movement->paid_date;
				$b->amount_paid			+= $bank_movement_bill->amount;
				$b->paid_by_user_id		= $user->id;
			}
			else
			{
				//$b->paid_status 		= 'PARTIALLY_PAID';
				$b->amount_paid			+= $bank_movement_bill->amount;
			}

			if( !$b->update('amount_paid','paid_date','paid_status') )
				throw new SystemException("An error occurred while updating the bill info");

			if( !$bank_movement_bill->insertDb() )
			{
				throw new SystemException('An error ocurred while sanving the info. Code: beidb');
			}

			//Aaaaa pinches flotantes y sus comparaciones
			if( !( $remaining_amount > 0 ) )
			{
				//No more money
				break;
			}
		}

		if( $remaining_amount > 0.01  && $bank_movement->type == 'expense' )
		{
			throw new ValidationException("The amounts do not corresd,Maybe one of the bill has been paid moments before this transaction, Amount Paid $".$remaining_amount.' <> '.$bank_movement->amount );
		}
	}

	function addBankMovementIncome($bank_movement, $bank_movement_invoices_info )
	{
		$user = app::getUserFromSession();

		if( $user == null || $user->type != 'admin' )
		{
			throw new ValidationException("You dont have permission to execute this action");
		}

		if( $bank_movement->investment_id )
		{
			if( $bank_movement->type !== 'income' )
				throw new ValidationException('Investment must be of the type "income"');

			$investment = investment::get( $bank_movement->investment_id );

			if( $investment->amount_deposited == $investment->amount || $investment->bank_deposit_status == 'DEPOSITED')
			{
				throw new ValidationException('The Investment had alredy been completed');
			}

			$investment->amount_deposited += $bank_movement->amount;


			if( $investment->amount_deposited>=$investment->amount)
			{
				$investment->bank_deposit_status = 'DEPOSITED';
			}

			$investment->update('bank_deposit_status','amount_deposited');
			$remaining_amount = 0;
		}

		$invoices_ids = array();

		foreach( $bank_movement_invoices_info as $bmii )
			$invoices_ids[] = $bmii['invoice']['id'];

		$invoices_array = invoice::search(array('id'=>$invoices_ids),true);

		$remaining_amount = $bank_movement->amount;

		foreach($invoices_array as $invoice)
		{
			//$this->debugArray('invoice',$invoice );

			if( $invoice->paid_status == 'PAID' )
				throw new ValidationException('One of the bills is already paid');

			if( $invoice->organization_id != $user->organization_id )
				throw new SystemException('This action will be reported ','Intrusion alert'.print_r( $params,true));

			$invoice_remaining							= $invoice->total - $invoice->amount_paid;
			$bank_movement_invoice						= new bank_movement_invoice();
			$bank_movement_invoice->invoice_id			= $invoice->id;
			$bank_movement_invoice->bank_movement_id	= $bank_movement->id;
			$bank_movement_invoice->amount				= min( $remaining_amount, $invoice_remaining );

			if( !$bank_movement_invoice->insertDb() )
				throw new SystemException('An error Ocurred please try again later');

			if( $invoice->total == ($invoice->amount_paid+ $bank_movement_invoice->amount) )
			{
				$invoice->paid_status	= 'PAID';
				$invoice->paid_date		= $bank_movement->paid_date;
				$invoice->amount_paid	+= $bank_movement_invoice->amount;

				//if( $invoice->payment_plan_installment_id )
				//{
				//	$payment_plan_installment = payment_plan_installment::get( $invoice->payment_plan_installment );
				//	//$payment_plan_installment->paid_status = 'PAID';
				//	//$payment_plan_installment->update('paid_status');
				//}
			}
			else
			{
				//$b->paid_status 		= 'PARTIALLY_PAID';
				$invoice->amount_paid			+= $bank_movement_invoice->amount;
			}

			if( !$invoice->update('amount_paid','paid_date','paid_status') )
				throw new SystemException("An error occurred while updating the bill info");


			//Aaaaa pinches flotantes y sus comparaciones
			if( !( $remaining_amount > 0 ) )
			{
				//No more money
				break;
			}

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
		catch(Exception $e)
		{
			DBTable::rollback();
			return $this->sendStatus( 500 )->json(array("error"=>$e->getMessage()));
		}

	}

	function batchUpdate($array)
	{
		$results = array();

		foreach($array as $params )
		{
			$bank_movement = bank_movement::createFromArray( $params['bank_movement'] );

			if( !empty( $bank_movement->id ) )
			{
				$bank_movement->setWhereString( true );

				$properties = bank_movement::getAllPropertiesExcept('created','updated');
				$bank_movement->unsetEmptyValues( DBTable::UNSET_ALL );

				if( !$bank_movement->updateDb( $properties ) )
				{
					throw new ValidationException('An error Ocurred please try again later',$bank_movement->_conn->error );
				}

				$bank_movement->load(true);

				$results [] = $bank_movement->toArray();
			}
			else
			{
				if( !$bank_movement->insert() )
				{
					throw new ValidationException('An error Ocurred please try again later',$bank_movement->_conn->error );
				}

				$results [] = $bank_movement->toArray();
			}
		}

		return $results;
	}


	function getInfo($bank_movement_list )
	{
		$props = ArrayUtils::getItemsProperties
		(
			$bank_movement_list,
			'id',
			'bank_account_id',
			'project_id',
			'provider_id',
			'investment_id',
			'provider_user_id',
			'invoice_attachment_id',
			'receipt_attachment_id'
		);

		$user = app::getUserFromSession();
		$attachment_ids		= array_merge( $props['invoice_attachment_id'], $props['receipt_attachment_id'] );

		$provider_user_array		= user::search(array('id'=>$props['provider_user_id']),false,'id');
		$attachment_array		= attachment::search(array('id'=>$attachment_ids),false,'id');
		$file_type_array	= file_type::search(array('organization_id'=>$user->organization_id),false,'id');
		$bank_account_array	= bank_account::search(array('bank_account_id'=>$props['bank_account_id']), false,'id');


		$bank_movement_bill_array   = bank_movement_bill::search(array('bank_movement_id'=>$props['id']),false,'bill_id');
		$bill_ids					= ArrayUtils::getItemsProperty( $bank_movement_bill_array, 'bill_id' );//array_keys($bank_movement_bill_array );
		$bill_array					= bill::search(array('id'=>$bill_ids),false,'id');

		$bmbaGroupBybankMovement 	= ArrayUtils::groupByProperty($bank_movement_bill_array,'bank_movement_id');

		$result = array();

		foreach( $bank_movement_list as $bank_movement )
		{
			$invoice_attachment = null;//empty( $bank_movement['invoice_attachment_id'] ) ? null : $attachment_array[ $bank_movement['invoice_attachment_id'] ];
			$invoice_file_type	= null;//$invoice_attachment == null ? null : $file_type_array[ $invoice_attachment['file_type_id'] ];

			$bills_info			= empty( $bmbaGroupBybankMovement[ $bank_movement['id']] ) ? array() : $bmbaGroupBybankMovement[ $bank_movement['id'] ];

			$bank_movement_bills_info = array();

			foreach( $bills_info as $bank_movement_bill )
			{
				$bill = $bill_array[ $bank_movement_bill['bill_id'] ];
				//$bank_movement_bill = $bank_movement_bill_array[ $bank_movement_bill_info['id'] ];

				$bank_movement_bills_info[] = array(
					'bill'=>$bill,
					'bank_movement_bill'=>$bank_movement_bill,
				);
			}

			$receipt_attachment = empty( $bank_movement['receipt_attachment_id'] ) ? null : $attachment_array[  $bank_movement['receipt_attachment_id'] ];
			$receipt_file_type 	= $receipt_attachment == null ? null : $file_type_array[ $receipt_attachment['file_type_id'] ];

			$bank_account	= empty( $bank_movement['bank_account_id'] )	? null : $bank_account_array[ $bank_movement['bank_account_id'] ];

			$result[] = array(
				'bank_movement'			=> $bank_movement,
				'provider'				=> empty( $bank_movement['provider_id'] )	? null : $provider_user_array[ $bank_movement['provider_user_id'] ],
				'bank_account'			=> $bank_account,
				'invoice_attachment'	=> $invoice_attachment,
				'invoice_file_type'		=> $invoice_file_type,
				'receipt_attachment'	=> $receipt_attachment,
				'receipt_file_type' 	=> $receipt_file_type,
				'bank_movement_bills_info'=> $bank_movement_bills_info
			);
		}

		return $result;
	}
}
$l = new Service();
$l->execute();
