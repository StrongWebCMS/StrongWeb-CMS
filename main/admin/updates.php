<?php

if( !defined('IN_SWCMS') )
{	exit(); }


$keys = array('update_form'=>'');
$latestVersion = checkSoftwareUpdate();
$updateFile = ROOT_PATH.'/temp/update.zip';

if( SOFTWARE_VERSION < $latestVersion[0] ) //if there is a new version and the user wants to update.
{
	//
	$form = new Form("ConfirmUpdate", 400, $_SERVER['REQUEST_URI'] );
			
	$form->addElement( new Element_YesNo('Would you like to update now?', 'confirm'));
	$form->addElement( new Element_Button("Confirm Update"));
	
	if( !empty($_POST) && Form::isValid('ConfirmUpdate') && $_POST['confirm'] == 1 )
	{
		downloadUpdatePackage( (string)$latestVersion[0] );
	
		//if the update package exists, validate the checksum and attempt to extract it to root path.
		if( file_exists( $updateFile ) )
		{
			//Create the local checksum.
			$updateChecksum = md5( file_get_contents($updateFile) );
			
			//Compare.
			if( $updateChecksum == trim($latestVersion[1]) )
			{
				//The file is valid, continue to extraction
				$zip = new ZipArchive;
				$res = $zip->open( $updateFile );
				if ($res === TRUE)
				{
					//echo $zip->getArchiveComment() ."\n\n<br /><br />";//Will likely use archive comments to ensure update zip is valid/ours.
					
					$zip->extractTo(ROOT_PATH.'/'); //Likely extract to root, attempt to make root path chown to 766
					$zip->close();
					
					//*/ After installing we may want to set some other permissions and secure some files.
					@chmod( '/', 0755);
					//Notably the config.php file should be read-only and the /temp dir should be read and write only for root/group.
					@chmod( '/config.php', 0764);
					@chmod( '/temp', 0760);
					/**/
				
					@unlink( $updateFile );
					
					//Update the config file version.
					$cfg = file_get_contents( ROOT_PATH.'/config.php');
					$cfg = preg_replace('#define\(\'SOFTWARE_VERSION\', \'(.*?)\'\);#is', 'define(\'SOFTWARE_VERSION\', \''.$latestVersion[0].'\');', $cfg);
					file_put_contents(ROOT_PATH.'/config.php', $cfg);
					
					$keys['status'] = '<span class="good">Update Successful!</span>';
				}	
				else
				{
					$keys['status'] = '<span class="bad">File Extraction Failed:</span> ' . $res . 
					"\n<br />Please try again.";
				}
			}
			else
			{	$keys['status'] = '<span class="bad">File Corrupt!</span> Please try again.'; }
		}
		else
		{	$keys['status'] = '<span class="bad">File Not Downloaded!</span> Please try again.'; }
	}
	else
	{
		$keys['update_form'] = $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) );
		$keys['status'] = '<strong style="color:green;">New Version Available!</strong><br />Current Version: '. SOFTWARE_VERSION .
						'<br />Update Version: '. $latestVersion[0];
	}
}
else
{
	$keys['status'] = 'No Updates Available.<br />Current Version: '. SOFTWARE_VERSION;
}

$page_data = $tpl->compileKeys('updates.html', array(
	'STATUS' 	  => $keys['status'],
	'UPDATE_FORM' => $keys['update_form']
));


class ftp
{
	private $connection;

	public function __construct( $url )
	{
	  $this->connection = ftp_connect($url);
	}
   
	public function __call( $funcName, $args )
	{
		if( strstr($funcName,'ftp_') !== false && function_exists($funcName) )
		{
			array_unshift($args, $this->connection);
			return call_user_func_array($funcName, $args);
		}
		else
		{
			die( "FTP Error: Call to undifined member function '{$funcName}'" );
		}
	}
}

/*/ Example
$ftp = new ftp('ftp.cbrumfield.x10.bz', true);
$ftp->ftp_login('strongweb@cbrumfield.x10.bz','updatechecker121');
var_dump($ftp->ftp_nlist( '/' )); 
/**/




/*
*   The following code is used to read from a directory containing update-packages.
*   Each package is named by its version number (eg: 0.5.zip)
*	When run, the code will list the latest version found in the directory, as well as the package checksum
*   The output is then used by the "client" software install to check if a new version is ready for download.
* /
$dir = dirname( __FILE__ );
$versionArray = array();

if( ($h = opendir($dir)) )
{
	while (($file = readdir($h)) !== false)
	{
		if( $file != '.' && $file != '..' && $file != 'index.php')
		{	
			$versionArray[] = str_replace('.zip', '', $file);
		}
	}
	closedir($h);
}

$pkgCount = count($versionArray);

natcasesort( $versionArray );
$md5 = md5( file_get_contents($dir.'/'.$versionArray[ ($pkgCount-1) ].'.zip'));

echo $versionArray[ ($pkgCount-1) ].'|'.$md5; //$versionArray[ ($pkgCount-2) ];
/**/


?>