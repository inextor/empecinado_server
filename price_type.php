<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\Utils;
use \akou\DBTable;
use \akou\RestController;
use \akou\ArrayUtils;
use \akou\ValidationException;
use \akou\LoggableException;


class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		$extra_constraints=array();
		$extra_joins = '';
		$extra_sort = array();
		$this->is_debug = false;
		return $this->genericGet('price_type',$extra_constraints,$extra_joins,$extra_sort);
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
		$results = array();

		foreach($array as $params )
		{
			$properties = price_type::getAllPropertiesExcept('created','updated','id','tiempo_actualizacion','tiempo_creacion');

			$price_type = new price_type();
			$price_type->assignFromArray( $params, $properties );
			$price_type->unsetEmptyValues( DBTable::UNSET_BLANKS );

			if( !$price_type->insert() )
			{
					throw new ValidationException('An error Ocurred please try again later',$price_type->_conn->error );
			}

			$results [] = $price_type->toArray();
		}

		return $results;
	}

	function batchUpdate($array)
	{
		$results = array();
		$insert_with_ids = false;

		foreach($array as $index=>$params )
		{
			$properties = price_type::getAllPropertiesExcept('created','updated','tiempo_actualizacion','tiempo_creacion');

			$price_type = price_type::createFromArray( $params );

			if( $insert_with_ids )
			{
				if( !empty( $price_type->id ) )
				{
					if( $price_type->load(true) )
					{
						$price_type->assignFromArray( $params, $properties );
						$price_type->unsetEmptyValues( DBTable::UNSET_BLANKS );

						if( !$price_type->update($properties) )
						{
							throw new ValidationException('It fails to update element #'.$price_type->id);
						}
					}
					else
					{
						if( !$price_type->insertDb() )
						{
							throw new ValidationException('It fails to update element at index #'.$index);
						}
					}
				}
			}
			else
			{
				if( !empty( $price_type->id ) )
				{
					$price_type->setWhereString( true );

					$properties = price_type::getAllPropertiesExcept('id','created','updated','tiempo_creacion','tiempo_actualizacion');
					$price_type->unsetEmptyValues( DBTable::UNSET_BLANKS );

					if( !$price_type->updateDb( $properties ) )
					{
						throw new ValidationException('An error Ocurred please try again later',$price_type->_conn->error );
					}

					$price_type->load(true);

					$results [] = $price_type->toArray();
				}
				else
				{
					$price_type->unsetEmptyValues( DBTable::UNSET_BLANKS );
					if( !$price_type->insert() )
					{
						throw new ValidationException('An error Ocurred please try again later',$price_type->_conn->error );
					}

					$results [] = $price_type->toArray();
				}
			}
		}

		return $results;
	}

	/*
	function delete()
	{
		try
		{
			app::connect();
			DBTable::autocommit( false );

			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			if( empty( $_GET['id'] ) )
			{
				$price_type = new price_type();
				$price_type->id = $_GET['id'];

				if( !$price_type->load(true) )
				{
					throw new NotFoundException('The element was not found');
				}

				if( !$price_type->deleteDb() )
				{
					throw new SystemException('An error occourred, please try again later');
				}

			}
			DBTable::commit();
			return $this->sendStatus( 200 )->json( $price_type->toArray() );
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
	*/
}
$l = new Service();
$l->execute();
