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
use \akou\SystemException;


class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		return $this->genericGet("serial_number_record");
	}

	function getInfo($serial_number_record_array)
	{
		$serial_number_record_ids= ArrayUtils::getItemsProperty($serial_number_record_array,'id');

		if( empty( $serial_number_record_ids) )
			return array();

		$sql = 'SELECT serial_number.serial_number_record_id,
					SUM( IF( serial_number.item_id IS NULL, 1,0 ) ) AS available,
					SUM( IF( serial_number.item_id IS NOT NULL, 1, 0 )) AS asigned,
					MIN( CASE WHEN serial_number.item_id IS NULL THEN serial_number.code END) AS first_available,
					MAX( CASE WHEN serial_number.item_id IS NULL THEN serial_number.code END) AS last_available
			FROM serial_number
			-- LEFT JOIN botella ON botella.serial_number_id = marbete.id
			WHERE serial_number.serial_number_record_id IN ('.DBTable::escapeArrayValues($serial_number_record_ids).')
			GROUP BY serial_number.serial_number_record_id
			';

		$disponibilidad = DBTable::getArrayFromQuery( $sql, 'serial_number_record_id' );

//		$this->debug('Disponibilidad',$disponibilidad);
		$result = array();

		foreach($serial_number_record_array as $serial_number_record)
		{
			$result[] = array
			(
				'serial_number_record'=>$serial_number_record,
				'availability'=> isset( $disponibilidad[ $serial_number_record['id'] ] ) ? $disponibilidad[ $serial_number_record['id'] ] : null
			);
		}
		return $result;
	}
}
$l = new Service();
$l->execute();
