<?php
namespace App;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');
include_once( __DIR__.'/akou/src/Attachment.php');

use \akou\DBTable;
use \akou\SystemException;

class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();

		//$user = app::getUserFromSession();
		//if( $user == null )
		//	return $this->sendStatus( 401 )->raw('Please Login');

		if( empty( $_GET['id'] ) )
		{
			$this->sendStatus(404)->json( Array('error'=>'Not Found') );
			return;
		}

		if( isset( $_GET['id'] ) && !empty( $_GET['id'] ) )
		{
			$attachment = attachment::get( $_GET['id']  );

			if( $attachment )
			{
				$file_type = file_type::get( $attachment->file_type_id );

				if( !empty($_GET['download']) )
				{
					header('Content-Disposition: attachment; filename="' . $attachment->original_filename . '"');
				}

				header('Content-type: '.$attachment->content_type);
				header('Content-length: '.$attachment->size);
				echo file_get_contents(app::$attachment_directory.'/'.$attachment->filename, true);
				return;
			}
			return $this->sendStatus( 404 )->raw('The element wasn\'t found');
		}

		$constraints = $this->getAllConstraints( attachment::getAllProperties() );
		$constraints_str = count( $constraints ) > 0 ? join(' AND ',$constraints ) : '1';
		$pagination	= $this->getPagination();

		$sql_attachments	= 'SELECT SQL_CALC_FOUND_ROWS attachment.*
			FROM `attachment`
			WHERE '.$constraints_str.'
			LIMIT '.$pagination->limit.'
			OFFSET '.$pagination->offset;
		$info	= DBTable::getArrayFromQuery( $sql_attachments );
		$total	= DBTable::getTotalRows();
		return $this->sendStatus( 200 )->json(array("total"=>$total,"data"=>$info));
	}

	function post()
	{
		if( !isset( $_FILES['file']) )
		{
			$this->sendStatus(400)->json( Array('error'=>'File `file` cant be empty') );
			//$this->sendStatus( 400 )->json(Array('error'=>'bad request '));
		}

		app::connect();

		$user = app::getUserFromSession();

		if( empty( $user ) )
			$this->sendStatus(401)->json( Array('error'=>'Login required') );

		$obj_file_attachment = $_FILES['file'];
		$attachment = new \akou\Attachment();

		$max_height = 0;
		$max_width	= 1350;
		$max_weight = 1024*1024*5;
		$min_width	= 1;
		$min_height = 1;

		$params = $this->getMethodParams();

		try
		{
			$result = $attachment->formAttachmentSaveToPath(
					$obj_file_attachment //obj_FILE
					,app::$attachment_directory // ,$to_save_dirname
			);

			$attachment = new attachment();
			$attachment->assignFromArray( $result );
			$attachment->size	= $result['file_size'];
			$attachment->is_private = isset( $params['is_private'] ) ? $params['is_private'] : 0;
			$attachment->uploader_user_id = $user->id;

			$file_type = file_type::searchFirst(array('content_type'=>$attachment->content_type) );

			if( $file_type )
			{
				$attachment->file_type_id = $file_type->id;
				$attachment->is_image = $file_type->is_image == 'YES';
			}
			else
			{
				$file_type = new file_type();
				$file_type->content_type = $attachment->content_type;
				$file_type->name = 'asdfasldf';
				$file_type->extension = $result['extension'];
				$file_type->is_image = 'NO';
				$file_type->organization_id = $user->organization_id;

				if( $file_type->insertDb() )
				{
					$attachment->file_type_id = $file_type->id;
				}
			}


			if( $attachment->insertDb() )
			{
				$this->sendStatus(200)->json(array('attachment'=> $attachment->toArray(),'file_type' =>$file_type ? $file_type->toArray() : null));
			}
			else
			{
				$this->sendStatus(400)->json( array('error'=>$attachment->_conn->error ));
			}
		}
		catch(SystemException $ex)
		{
			$this->sendStatus(500)->json( array('error'=>'An error occurred please try again later'));
		}
	}
}

$l = new Service();
$l->execute();
