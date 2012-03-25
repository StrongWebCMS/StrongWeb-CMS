<?php

if( !defined('IN_SWCMS') )
{	exit(); }

$tpl->RegisterResource( 'filemanager.js' );

include( ROOT_PATH.'/main/filesystem.php');

$fs = new fileManager();


//Start sorting out the actions here.
if( isset($_GET['ajax']) && !empty($_GET['ajax']) )
{
	switch( strtolower($_GET['ajax']) )
	{
	case 'opendir':
		if( isset($_GET['dir']) && !empty($_GET['dir']) )
		{
			//used to go to the next folder in the current list.
			echo $fs->openDirectory( $_GET['dir'] );
		}
		
		exit();
	break;
	case 'gotodir':
		if( isset($_GET['dir']) && !empty($_GET['dir']) )
		{
			//Used to go to a given path from the root of the manager-system.
			echo $fs->gotoDirectory( $_GET['dir'] );
		}
		
		exit();
	break;
	case 'getcurrentdir':
		//simply returns the current file-path relative to the manager's root.
		echo $fs->getCurrentPath();
		exit();
	break;
	case 'reloadcurrentdir':
		//reloads the current path listing.Used for deleting files, and adding new ones.
		echo $fs->gotoDirectory( $fs->getCurrentPath() );
		exit();
	break;
	case 'getfileicon':
		if( isset($_GET['filename']) && !empty($_GET['filename']) )
		{
			$icon_type = explode('.', $_GET['filename']);
			$icon_type = end($icon_type);
			$icon_type = ( !empty($icon_type) ) ? $icon_type : '_blank';
			
			echo $fs->getFileIconPath( $icon_type );
		}
		exit();
	break;
	case 'rename':
		if( isset($_GET['on']) && !empty($_GET['on']) && isset($_GET['nn']) && !empty($_GET['nn']) )
		{
			//Check again for a bad filename.
			if( preg_match("#[\\\/\:\*\?\"\<\>\|\;]+|(^con$)|(^lpt[0-9]*$)|(^prn$)#i", $_GET['nn']) )
			{
				echo "error:Invalid file name!\nPlease try again.";
			}
			
			$ret = $fs->renameItem( $_GET['on'], $_GET['nn'] );
			
			if( $ret == false )
			{
				echo "error:File rename failed!\nPlease try again.";
			}
		}
		
		exit();
	break;
	
	case 'rm':
		if( isset($_GET['item']) && !empty($_GET['item']) )
		{
			$test = $fs->removeItem( $_GET['item'] );
		
			if( $test == true )
			{	echo 'true'; }
			else
			{	echo 'false'; }
		}
		
		exit();
	break;
	
	case 'upload':
	
		if( isset($_FILES['upload']) )
		{
			
			$f = &$_FILES['upload'];
			$fc = count($f['name']);
			for($i=0; $i < $fc; $i++ )
			{
				$filename = &$f['name'][$i];
				$error = &$f['error'][$i];
				
				//Only the first file should return a not-uploaded error, since the rest are optional uploads.
				if( $i >= 1 && $error == 4 )
				{	continue; }
				
				if( $error == 0 )
				{
					if( !$fs->uploadFile( $filename, $f['tmp_name'][$i]) )
					{	echo 'Error: File upload failed!'; }
				}
				else
				{
					switch($error )
					{
					case UPLOAD_ERR_INI_SIZE:
					case UPLOAD_ERR_FORM_SIZE:
						echo 'Error: File was too large! '.$error ;
					break;
					
					case UPLOAD_ERR_PARTIAL: //Upload cut short.
						echo 'Error: File upload did not finish! '.$error ;
					break;
					
					case UPLOAD_ERR_NO_FILE:
						echo 'Error: No file uploaded! '.$error ;
					break;
					
					case UPLOAD_ERR_NO_TMP_DIR:
					case UPLOAD_ERR_CANT_WRITE:
					case UPLOAD_ERR_EXTENSION:
						echo 'Error: Internal Server Error! '.$error ;
					break;
					
					}
				}
			}
		}
		else
		{	
			echo "Error: Maximum upload size(" . ini_get('post_max_size') . ") exceeded!";
		}
		
		exit();
	break;
	
	case 'addfolder':
		if( isset($_GET['dirname']) && !empty($_GET['dirname']) )
		{
			$ret = $fs->createDirectory( $_GET['dirname'] );
			
			if( $ret == false )
			{	echo 'false'; }
			else
			{
				echo $fs->gotoDirectory( $fs->getCurrentPath() );
			}
		}
		
		exit();
	break;
	
	default:
		echo 'Non-valid action. Request denied.';
		exit();
	break;
	}
}
else
{
	$page_data = $tpl->compileKeys('filemanager.html', array( 
		'FILE_TABLE' => $fs->openDirectory( '/' )
	));
}

?>