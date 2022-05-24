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


$__user		= 'root';
$__password	= 'asdf';
$__db		= 'empecinado';
$__host		= '127.0.0.1';
$__port		= '3306';
Utils::$DEBUG_VIA_ERROR_LOG	= FALSE;
Utils::$LOG_LEVEL			= Utils::LOG_LEVEL_ERROR;
Utils::$DEBUG				= FALSE;
Utils::$DB_MAX_LOG_LEVEL	= Utils::LOG_LEVEL_ERROR;
app::$is_debug	= false;

$mysqli = new \mysqli($__host, $__user, $__password, $__db, $__port );
if( $mysqli->connect_errno )
{
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
	exit();
}


DBTable::$connection							= $mysqli;
DBTable::$connection				= $mysqli;
$str = DBTable::importDbSchema('APP');

echo '<?php'.PHP_EOL.$str;
//$myfile = fopeon("schema.php","w") or die("unable to open file");
//fwrite( $myfile, $str );
//fclose( $myfile );
