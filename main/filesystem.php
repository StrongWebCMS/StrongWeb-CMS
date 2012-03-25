<?php

/**
*  fileManager allows use of the local host-filesystem for creating dynamic local web storage.
*   
*/

class fileManager
{
	private $currentPath = '/'; //The current directory, from the root of our virtual filesystem.
	private $fsRootPath = '/';  //The host-filesystem path to the software 'files' directory.
	private $relativePath;
	
	private $isPathError = false;
	
	//Locate the initial "system-root" for this object.
	public function fileManager()
	{
		global $config;
		
		//We define the local path to the storage folder.
		$this->fsRootPath = ROOT_PATH . '/files/'; 
		
		//Define a web-relative uri for use in linking to files.
		$baseURL = (SOFTWARE_INSTALLED == true)? $config->GetConfig('SITE_URL') : WEB_DOC_ROOT;
		$this->relativePath = $baseURL . '/files/';
		
		//define internal refernce variables.
		$this->currentPath = &$_SESSION['fs']['current_path'];
		$this->isPathError = &$_SESSION['fs']['cp_has_error'];
		
		if( empty($this->currentPath) )
		{	$this->currentPath = '/'; }
		
		if( !isset( $this->isPathError ) )
		{	$this->isPathError = false; }
	}
	
	public function getCurrentPath()
	{	
		$tmpPath = $this->currentPath;
		
		if( $this->isPathError == true )
		{	
			$this->currentPath = '/'; 
			$this->isPathError = false;
		}
		
		return $tmpPath;
	}
	
	public function getFileIconPath( $icon_type )
	{
		global $tpl;
		
		$icon_path = $tpl->GetImagesPath().'file_icons/'.$icon_type.'.png';
		if( !@file_exists( $icon_path ) )
		{
			$icon_path = $tpl->GetImages_URIPath().'file_icons/_blank.png';
		}
		else
		{	$icon_path = $tpl->GetImages_URIPath().'file_icons/'.$icon_type.'.png'; }
		
		return $icon_path;
	}
	
	//Gets the files from the currentPath directory and builds and HTML table for displaying the objects.
	public function displayCurrentPath()
	{
		global $tpl;
		
		$filePath = normalizeFilePath( $this->fsRootPath.$this->currentPath.'/', true); 
		
		//Check to see if the directory being opened is valid.
		//If not update the table to list the filesystem root path.
		$pathNotValid = false;
		if( !is_dir($filePath) )
		{	
			$pathNotValid = true;
			$filePath = $this->fsRootPath;
			
			$this->isPathError = true;
		}
		
		$tbl = new HTML_Table('file_listing', 'filelist', 0, 0, 0, array('summary'=>'Local Files'));
		$tbl->addRow( );
		$tbl->addCell('', 'iconspace', 'header' );
		$tbl->addCell( 'File Name:', 'filename', 'header' );
		$tbl->addCell( 'Options', 'options', 'header' );
		
		//provide a "back" button if the path isn't root and is also valid.
		if( $this->currentPath != '/' && !$pathNotValid)
		{
			$tbl->addRow( 'dark' );
			$tbl->addCell('<img src="'.$tpl->GetImages_URIPath().'file_icons/_folder.png" alt="DIR" title="Directory" width="22" height="22" />', 
						  null, 'data', array('align'=>'center', 'ondblclick'=>'fs_openDirectory(\'../\')'));
			
			$tbl->addCell('../ (Back one Directory)', null, 'data', array('align'=>'left', 'ondblclick'=>'fs_openDirectory(\'../\')') );
			$tbl->addCell( '', null, 'data' );
			
			$bg_type = 'dark';
		}
		else
		{	$bg_type = 'lite'; }
		
		$jsonArray = array(); //storage for the current file-list.
		$dirs = array();
		$files = array();
		
		//Read the files from the current directory path.
		//We will build a few arrays here to store file-data.
		if ($h = opendir( $filePath ))
        {
            while (($file = readdir($h)) !== false)
            {
                if( $file != '.' && $file != '..' && $file != '.htaccess')
                {
                	if( is_dir($filePath.$file) )
					{
						$dirs[] = $file;
					}
					else
					{
						$files[] = $file;
					}
                }
            }
            closedir($h);
        }
		
		//Generate the listings here.
		$lblNum = 0;
		
		//Build each directory-type listing first.
		foreach( $dirs as $file )
		{
			//Determine the row-coloration.
			if( $bg_type == 'lite' )
			{	
				$bg_type = 'dark'; 
				$tbl->addRow( $bg_type );
			}
			elseif( $bg_type == 'dark' )
			{
				$bg_type = 'lite';
				$tbl->addRow( $bg_type ); 
			}
			
			//Get directory edit options and build drictory entry.
			$icon_path = $tpl->GetImages_URIPath().'file_icons/_folder.png';
			$tbl->addCell('<img src="'.$icon_path.'" id="fs_icon_'.$lblNum.'" alt="DIR" title="Directory" width="22" height="22" />', 
							null, 'data', array('align'=>'center', 'ondblclick'=>'fs_openDirectory(\'/'.$file.'\')'));
							
			$tbl->addCell('<input type="text" value="'.$file.'/" readonly="readonly" class="fs_label" id="fs_label_'.$lblNum.'" />', 
							null, 'data', array('align'=>'left', 'ondblclick'=>'fs_openDirectory(\'/'.$file.'\')') );
			
			$optData = 
			'<a onclick="fs_renameItem( '.$lblNum.' )">'.
				'<img src="'.$tpl->GetImages_URIPath().'rename_dir.png" alt="[Rename]" title="Rename Directory" width="26" height="26" /></a>'.
			'<a onclick="fs_removeItem( '.$lblNum.' )"><img src="'.$tpl->GetImages_URIPath().'rem_dir.png" alt="[Remove]" title="Delete" width="26" height="26" /></a>';
			
			$tbl->addCell($optData, null, 'data', array('align'=>'right') );
			
			//Add this file to the json index used by the client-side system
			$jsonArray[ $lblNum ] = $file.'/';
			
			$lblNum++;
		}
		
		//Then each file-type listing.
		foreach( $files as $file )
		{
			//Determine the row-coloration.
			if( $bg_type == 'lite' )
			{	
				$bg_type = 'dark'; 
				$tbl->addRow( $bg_type );
			}
			elseif( $bg_type == 'dark' )
			{
				$bg_type = 'lite';
				$tbl->addRow( $bg_type ); 
			}
			
			//here we simply use the file's extension to pick out a file-icon for the list.
			$icon_type = explode('.', $file);
			$icon_type = end($icon_type);
			$icon_type = ( !empty($icon_type) ) ? $icon_type : '_blank';
			
			$icon_path = $this->getFileIconPath( $icon_type );
			
			$tbl->addCell('<img src="'.$icon_path.'" alt="FILE" title="File" width="22" height="22" id="fs_icon_'.$lblNum.'"  />',
							 null, 'data', array('align'=>'center'));
			$tbl->addCell('<input type="text" value="'.$file.'" readonly="readonly" class="fs_label" id="fs_label_'.$lblNum.'" />', null, 'data', array( 'align'=>'left') );
			
			$dl_link = normalizeURLPath($this->relativePath.$this->currentPath.'/'.$file);
			
			$optData = 
			'<a onclick="fs_renameItem( '.$lblNum.' )">'.
				'<img src="'.$tpl->GetImages_URIPath().'rename_file.png" alt="[Rename]" title="Rename File" width="26" height="26" /></a>'.
			'<a onclick="fs_removeItem( '.$lblNum.' )">'.
				'<img src="'.$tpl->GetImages_URIPath().'rem_file.png" alt="[Remove]" title="Delete" width="26" height="26" /></a>'.
		    '<a href="'.$dl_link.'">'.
				'<img src="'.$tpl->GetImages_URIPath().'download_file.png" alt="[Download]" title="Download File" width="26" height="26" /></a>';
			
			$tbl->addCell($optData, null, 'data', array('align'=>'right') );
			
			//Add this file to the json index used by the client-side system
			$jsonArray[ $lblNum ] = $file;
			
			$lblNum++; 
		}
		
		//Build the JSON array for client-side processing.
		$output = '<input id="fs_json_data" type="hidden" value="'. htmlentities( json_encode( $jsonArray ), ENT_QUOTES).'" />';
		
		//handle errors and output.
		if( $pathNotValid )
		{
			$output .= "<div class=\"error_msg\">The File-Manager could not find the specified directory! &nbsp; Listing root directory instead.<br />\n".
					  "Please make sure the file-path is valid and try again.</div>".$tbl->render();
		}
		else
		{	$output .= $tbl->render(); }
		
		//This will return the HTML table results holding the dir-listing.
		return $output;
	}
	
	public function openDirectory( $rel_dir )
	{
		$this->moveInternalPath( $rel_dir );
		return $this->displayCurrentPath();
	}
	
	public function gotoDirectory( $rel_dir )
	{
		//Reset internal pointer to the "root" before going to $rel_dir.
		$this->moveInternalPath( '/' ); 
		$this->moveInternalPath( $rel_dir );
		
		return $this->displayCurrentPath();
	}
	
	public function uploadFile( $filename, $tmp_file )
	{
		if( is_uploaded_file( $tmp_file ) )
		{
			$toDir = $this->fsRootPath.$this->currentPath.'/'.$filename;
			
			return @copy( $tmp_file, $toDir );
		}
		
		return false;
	}
	
	//Takes local file and moves it to a new local location. 
	public function copyFileTo($origin, $newLocation)
	{
		return @copy( $origin, $newLocation );
	}
	
	//Renames an existing file, in the current directory.
	public function renameItem( $oldName, $newName )
	{
		//check if the rename is called on a protected file.
		if(	preg_match('#^\.ht((access)|(passwd))$#ism', $oldName."\n".$newName) )
		{ return; }
		
		$oldPath = normalizeFilePath( $this->fsRootPath.$this->currentPath .'/'. $oldName, true );
		$newPath = normalizeFilePath( $this->fsRootPath.$this->currentPath .'/'. $newName, true );
		
		if( file_exists( $oldPath ) )
		{
			return rename( $oldPath, $newPath );
		}
		else
		{
			return false;
		}
	}
	
	//Creates a new directory node in the files directory.
	public function createDirectory( $dirname )
	{
		//Along with making the directory, we should place an index file.
		//The index.php files can serve as blank pages or redirections.
		//The files should also be chmod'd to allow running.
		
		$dirname = normalizeFilePath( $this->fsRootPath.$this->currentPath.'/'.$dirname );
		
		return @mkdir( $dirname );
	}
	
	//Used to create raw text/html documents.
	public function createFile()
	{}
	
	//Gets any files from Posted form data then sanitizes and moves them to the local files directory.
	public function uploadFiles()
	{
			//Gets the files mime-type by magic bytes.
			$file_info = new finfo(FILEINFO_MIME);	
			$mime_type = $file_info->buffer(file_get_contents($file)); 
	}
	
	//Takes a local zip archive and extracts it to a specified folder.
	private function extractFiles($archivePath, $extractTo)
	{}
	
	//Takes a local image, jpeg, gif, or PNG and resizes it to the new dimensions.
	private function resizeImage($imgPath, $newX, $newY)
	{}
	
	//Handles deletion of file(s) and directories.
	public function removeItem( $itemName )
	{
		//Some files just shouldn't be removed. :)
		if( in_array($itemName, array('..', '.', '.htaccess', '.htpasswd')) )
		{	return false; }
		
		$path = normalizeFilePath( $this->fsRootPath.$this->currentPath.$itemName, true );
		
		if( is_dir($path) )
		{
			return $this->rrmdir( $path );
		}
		elseif( file_exists($path) )
		{
			return @unlink( $path );
		}
		else
		{	return false; }
		
	}
	
	//Removes a given directory and its files with recursion.
	private function rrmdir( $dir )
	{
		if( substr($dir, 0, -1) != '/' )
		{	$dir = $dir.'/'; }
		
		$dh = opendir($dir);
		while(($file = readdir($dh)) !== false)
		{
			if ( $file == '.' or $file == '..' )
			{	continue; }
			
			if( is_dir($dir.$file) )
			{
				$this->rrmdir( $dir.$file );
			}
			else
			{	@unlink( $dir.$file ); }
		}
		closedir($dh);
		
		return @rmdir( $dir );
	}
	
	
	//Updates the object's internal filepath. 
	private function moveInternalPath( $newPath )
	{	
		if( $newPath == '/' )
		{	$this->currentPath = '/'; }
		
		if( $newPath == '../')
		{
			$p = explode('/', $this->currentPath);
			$cp = count( $p );
			
			if( $cp == 2 )
			{	$this->currentPath = '/'; }
			else
			{
				unset( $p[ ($cp-1) ] );
				
				$this->currentPath = str_replace( array('///','//'), '/', implode('/', $p) );
			}
		}
		else
		{
			$this->currentPath = str_replace( array('///','//'), '/', $this->currentPath.'/'.$newPath );
		}
		
	}

}

?>