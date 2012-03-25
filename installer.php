<?php
/********************************************
*         installer.php
*
*   This page checks requirements and performs
* the software setup.
**********************************************/

define('IN_SWCMS', TRUE);
	
function generateConfigFile( $cfg )
{
$cfgFilePath = normalizeFilePath(ROOT_PATH.'/config.php');
$cfgFileData = '<?php
if( !defined(\'IN_SWCMS\') )
{	exit(); }

/**************************************************
*             CMS Configurations File.
* 
*	The options below are required by the software
* in order to function as intended. Changing any 
* of these could render your all of your pages 
* unviewable. 
*
* Proceed if you understand these configurations.
**************************************************/

$db_host = \''.$cfg['dbHost'].'\';
$db_user = \''.$cfg['dbUser'].'\';
$db_pass = \''.$cfg['dbPass'].'\';
$db_name = \''.$cfg['dbName'].'\'; 
$db_type = \''.$cfg['dbType'].'\'; 

define(\'SOFTWARE_VERSION\', \'0.5\');

?>';

	if(@file_put_contents($cfgFilePath, $cfgFileData))
	{	return true; }
	else
	{	return false; }

}

//Make sure a session hasn't already been created before starting this new one.
if( !isset($_COOKIE[ini_get('session.name')])) {
	session_start();
}

require_once( './common.php' );

//Do some fail-safe checking to keep people from using this file once installation is finished.
if( defined('SOFTWARE_INSTALLED') && SOFTWARE_INSTALLED == TRUE )
{	header('Location: /'); }


//Start a new form and template session.
$cache  = new Cache();
$gpc 	= new GetPostCookie();
$tpl 	= new template( '_admin' );
$phpass = new PasswordHash( 14 , FALSE );

//System path to the config file.
$configFile = normalizeFilePath(ROOT_PATH.'/config.php');

//Set of sections and labels for each.
$section_arr = array(
	'check'		=> 'Check Requirements',
	'connect'	=> 'Connect to Database',
	'create' 	=> 'Creat an Admin Account',
	'finalize'	=> 'Complete the Setup'
);

if( isset($_GET['step']) )
{	$panel = trim( strtolower( $gpc->get('GET', 'step') ) ); }
else
{	$panel = 'check'; }

$page_data = 'There was definitely an error.';

switch($panel)
{
	case 'check':
		
		//print_r(get_loaded_extensions());
		
		if(isset($_POST['agreement']) && $_POST['agreement'] == 1 )
		{	header('Location: ' . normalizeURLPath( WEB_DOC_ROOT.'/installer.php?step=connect' )); }
		
		$cfgStatus='';
		if( file_exists($configFile) )
		{
			$cfgStatus = 'config.php ';
			if(is_readable($configFile))
			{	$cfgStatus .= '<span class="good">is readable</span>';	}
			else
			{	$cfgStatus .= '<span class="bad">is not readable</span>'; }
			
			$cfgStatus .= ' and ';
			
			if(is_writeable($configFile))
			{	$cfgStatus .= '<span class="good">is writable</span>';	}
			else
			{	$cfgStatus .= '<span class="bad">is not writable</span>'; }
		}
		else
		{
			$cfgStatus = '<span class="bad">config.php was not found!</span>';
		}
		
		$phpVersionStatus = '';
		if (version_compare(PHP_VERSION, '5.3.0') >= 0) 
		{	$phpVersionStatus = '<span class="good">PHP: '.PHP_VERSION.' &nbsp; <strong>OK</strong></span>'; }
		else
		{	$phpVersionStatus = '<span class="bad">PHP: '.PHP_VERSION.'</span>'; }
		
		if( extension_loaded('zip') )
		{	$zipSupport = '<span class="good">Available!</span>'; }
		else
		{	$zipSupport = '<span class="bad">Not Enabled!</span>'; }
		
		//There will need to be more checking here, for databases and other services.
		
		$page_data = $tpl->compileKeys('install_requirements.html', array(
			'CONFIG_FILE_STATUS' 	=> $cfgStatus,
			'PHP_VERSION_STATUS' 	=> $phpVersionStatus,
			'ZIP_SUPPORT' 			=> $zipSupport,
		));
		
	break;	
	
	case 'connect':
		if( isset($_SESSION['SW_Install']['db_conn_data']) )
		{	$dcd = $_SESSION['SW_Install']['db_conn_data']; }
		
		unset($_SESSION['SW_Install']['db_conn_data']);
	
		$form = new Form("DatabaseConnect", 400, $_SERVER['REQUEST_URI'] );
		
		$form->addElement(new Element_Textbox('Host Name or Address:', 'db_host', array(
			'required' => 1,
			'value' => (isset($dcd['dbHost']) && !empty($dcd['dbHost'])) ? $dcd['dbHost'] : '',
		)));
		$form->addElement(new Element_Textbox('Database Name:', 'db_name', array(
			'required' => 1,
			'value' => (isset($dcd['dbName']) && !empty($dcd['dbName'])) ? $dcd['dbName'] : '',
		)));
		$form->addElement(new Element_Textbox('User Name:', 'db_user', array(
			'required' => 1,
			'value' => (isset($dcd['dbUser']) && !empty($dcd['dbUser'])) ? $dcd['dbUser'] : '',
		)));
		$form->addElement(new Element_Password('User Password:', 'db_pass', array(
			'required' => 1,
		)));
		
		$form->addElement(new Element_Button('Check Settings'));
		
		if( !empty($_POST) && Form::isValid('DatabaseConnect') )
		{
			$dbHost = (isset($_POST['db_host']) && !empty($_POST['db_host'])) ? $_POST['db_host'] : 'localhost';
			$dbUser = (isset($_POST['db_user']) && !empty($_POST['db_user'])) ? $_POST['db_user'] : '';
			$dbPass = (isset($_POST['db_pass']) && !empty($_POST['db_pass'])) ? $_POST['db_pass'] : '';
			$dbName = (isset($_POST['db_name']) && !empty($_POST['db_name'])) ? $_POST['db_name'] : '';
			$dbType = 'mysql';
			
			$_SESSION['SW_Install']['db_conn_data'] = array( 'dbHost'=>$dbHost, 'dbUser'=>$dbUser, 'dbPass'=>$dbPass, 'dbName'=>$dbName, 'dbType'=>$dbType );
			
			//Try a new connection here.
			$db = ADONewConnection($dbType);
			$db->Connect($dbHost,$dbUser,$dbPass,$dbName);
			
			if( !$db->IsConnected() )
			{	$dbStatus = $db->ErrorMsg(); }
			else
			{	
				$_SESSION['SW_Install']['db_conn_data'] = array( 'dbHost'=>$dbHost, 'dbUser'=>$dbUser, 'dbPass'=>$dbPass, 'dbName'=>$dbName, 'dbType'=>$dbType );
				header('Location: ' . normalizeURLPath( WEB_DOC_ROOT.'/installer.php?step=create' )); 
			}	
		}	
		
		$page_data = $tpl->compileKeys('install_database.html', array(
			'DATABASE_ACCESS_FORM' 	=> $form->render(true),
			'DB_CONNECTION_STATUS' 	=> (isset($dbStatus) && !empty($dbStatus)) ? $dbStatus : '' 
		));
		
	break;	
	
	case 'create':
		
		$form = new Form("AdminAddNew", 400, $_SERVER['REQUEST_URI'] );
		$form->configure(array(
    		"view" => new View_SideBySide(110)
		));
		
		$form->addElement( new Element_Textbox('User Name: ', 'user_name', array(
			"validation" => new Validation_RegExp("/[a-z0-9]/i", "Error: The User Name can only contain letters or numbers."),
			'required' => 1,
			//'description'=>'Letters A-Z and 0-9 only.'
		)));
		$form->addElement( new Element_Password('Password: ', 'user_password', array(
			"validation" => new Validation_RegExp("/.{8,32}/i", "Error: The password must be between 8 and 32 characters long."),
			'required' => 1,
			//'description' => 'Should be between 8-32 characters.'
		)));
		$form->addElement( new Element_Textbox('Email: ', 'user_email', array(
			'validation' => new Validation_Email,
			'required' => 1
		)));

		$form->addElement( new Element_Button() );
			
		if( !empty($_POST) && Form::isValid('AdminAddNew') )
		{
			$uName='';
			$uPass='';
			$uEmail='';
			$uRole=2;
			$er=0;
			
			if( isset($_POST['user_name']) && !empty($_POST['user_name']) )
			{	$uName = $_POST['user_name']; }
			else
			{	$er++; }
			
			if( isset($_POST['user_password']) && !empty($_POST['user_password']) )
			{	$uPass =  $phpass->HashPassword( $_POST['user_password'] ); }
			else
			{	$er++; }
			
			if( isset($_POST['user_email']) && !empty($_POST['user_email']) )
			{	$uEmail = $_POST['user_email']; }
			else
			{	$er++; }
			
			if( $er != 0)
			{	trigger_error('There was an error attempting to save the form.<br />Please retry again.', E_USER_ERROR); }
			else
			{
				$record = array(
					'name'  	=> $uName,
					'pass' 		=> $uPass,
					'email' 	=> $uEmail,
					'pmx_role'	=> 2,  //set to the administrative role id.
					'time_joined' => time(),
					'last_active' => time()
				);
				
				$_SESSION['SW_Install']['admin_record'] = $record;
				
				header('Location: ' . normalizeURLPath( WEB_DOC_ROOT.'/installer.php?step=finalize' ));
			}
		}	
		else
		{
			$keys = array(
				'ADD_USER_FORM' => str_replace('><', ">\n<", $form->render(true))
			);
			
			$page_data = $tpl->compileKeys('install_admin_account.html', $keys );
		}
	break;	
	
	case 'finalize':
		
		$form = new Form("FinalizeInstall", 530, $_SERVER['REQUEST_URI'] );
		
		$form->addElement(new Element_Hidden('finalize', 'true') );
		$form->addElement(new Element_Textbox('Site Index URL', 'site_url', array(
			'required' 		=> 1,
			'description' 	=> 'The address, with protocol, used to access the index or home-page of this site.',
			'value' 		=> generateServerURL()
		)));
		$form->addElement(new Element_Button( "Save Configurations to Database" ));
		
		if( !empty($_POST) && Form::isValid('FinalizeInstall') )
		{
			$dbc = $_SESSION['SW_Install']['db_conn_data'];
			$admin = $_SESSION['SW_Install']['admin_record'];
			
			//Get and sanitize our site_URL config
			if( isset($_POST['site_url']) && !empty($_POST['site_url']))
			{
				if(substr($_POST['site_url'], -1, 1) == '/' )
				{	$_POST['site_url'] = substr($_POST['site_url'], 0, -1); }
				
				$site_url = $db->qstr( $_POST['site_url'] ); 
			}
			else
			{	$site_url = generateServerURL(); }
			
			$cookie_name = uniqid('SWCMS_', true);
			
			
			$db = ADONewConnection($dbc['dbType']); 
			$db->Connect($dbc['dbHost'],$dbc['dbUser'],$dbc['dbPass'],$dbc['dbName']);
			
			//Then create a data dictionary object, using this connection
			$dict = NewDataDictionary($db);
			
			//Begin building the SQL statements for the databse.
			$cfg_flds = "`cfg_id` I(128) NOTNULL AUTO PRIMARY,
			  `cfg_for` C(64) NOTNULL,
			  `cfg_name` C(64) NOTNULL,
			  `cfg_value` X DEFAULT NULL NOQUOTE";
			$idxflds = 'cfg_for';
			$sqlarray = $dict->CreateTableSQL('config', $cfg_flds);
			$sqlarray = array_merge( $sqlarray, $dict->CreateIndexSQL('config_for', 'config', $idxflds) );
			
			$link_flds = "
			  `link_id` I(128) NOTNULL AUTO PRIMARY,
			  `link_text` C(64) NOTNULL,
			  `link_url` C(128) DEFAULT NULL,
			  `link_enabled` I(32) NOTNULL DEFAULT '0'";
			$sqlarray = array_merge( $sqlarray, $dict->CreateTableSQL('links', $link_flds));
			
			$page_flds = "
			  `page_id` I(11) NOTNULL AUTO PRIMARY,
			  `page_name` C(128) NOTNULL,
			  `content` X NOTNULL,
			  `plugin_list` C(128) NOTNULL";
			$sqlarray = array_merge( $sqlarray, $dict->CreateTableSQL('pages', $page_flds));
			
			$pmx_flds = "
			  `pid` I(11) NOTNULL AUTO PRIMARY,
			  `pmx` C(225) NOTNULL,
			  `name` C(128) NOTNULL";
			$sqlarray = array_merge( $sqlarray, $dict->CreateTableSQL('pmx_roles', $pmx_flds));
			
			$user_flds = "
			  `uid` I(128) NOTNULL AUTO PRIMARY,
			  `name` C(64) NOTNULL,
			  `email` C(128) NOTNULL,
			  `pass` C(255) NOTNULL,
			  `time_joined` I(64) NOTNULL,
			  `last_active` I(64) NOTNULL,
			  `session_key` C(64) NOTNULL,
			  `pmx_role` I(32) NOTNULL DEFAULT '1',
			  `timezone` C(16) NOTNULL";
			$sqlarray = array_merge( $sqlarray, $dict->CreateTableSQL('users', $user_flds));
			
			$insertSQL = array( 
"INSERT INTO `config` (`cfg_id`, `cfg_for`, `cfg_name`, `cfg_value`) VALUES (1, 'system', 'site_url', ".$site_url.")",
"INSERT INTO `config` (`cfg_id`, `cfg_for`, `cfg_name`, `cfg_value`) VALUES (2, 'system', 'seo_links_enabled', '1')",
"INSERT INTO `config` (`cfg_id`, `cfg_for`, `cfg_name`, `cfg_value`) VALUES (3, 'system', 'cache_on', '1')",
"INSERT INTO `config` (`cfg_id`, `cfg_for`, `cfg_name`, `cfg_value`) VALUES (4, 'system', 'debug_output_on', 'false')",
"INSERT INTO `config` (`cfg_id`, `cfg_for`, `cfg_name`, `cfg_value`) VALUES (5, 'system', 'site_cookie_name', ".$cookie_name.")",
"INSERT INTO `config` (`cfg_id`, `cfg_for`, `cfg_name`, `cfg_value`) VALUES (6, 'system', 'site_permissions', 'YTozOntzOjg6ImlzX2FkbWluIjtzOjM4OiJDYW4gTG9nLWluIHRvIHRoZSBBZG1pbmlzdHJhdGlvbiBQYW5lbCI7czoxMDoicG14X21hbmFnZSI7czo0NzoiQ2FuIE1hbmFnZSBVc2VyL0FkbWluIFBlcm1pc3Npb24gQ29uZmlndXJhdGlvbnMiO3M6MTU6InNldHRpbmdzX21hbmFnZSI7czoyNjoiQ2FuIG1hbmFnZSBzeXN0ZW0gc2V0dGluZ3MiO30=')",
"INSERT INTO `config` (`cfg_id`, `cfg_for`, `cfg_name`, `cfg_value`) VALUES (7, 'system', 'default_template', 'main')",
"INSERT INTO `config` (`cfg_id`, `cfg_for`, `cfg_name`, `cfg_value`) VALUES (8, 'system', 'enabled_addons', '')",

"INSERT INTO `users` (`uid`,`name`,`email`,`pass`,`time_joined`,`last_active`,`session_key`,`pmx_role`,`timezone`) VALUES (1,'Anonymous','','',0,0,'',1,'')",
"INSERT INTO `users` (`uid`,`name`,`email`,`pass`,`time_joined`,`last_active`,`session_key`,`pmx_role`,`timezone`) VALUES (2,'".$admin['name']."','".$admin['email']."','".$admin['pass']."',".$admin['time_joined'].",".$admin['last_active'].",'',2,'')",

"INSERT INTO `pmx_roles` (`pid`, `pmx`, `name`) VALUES (1, 'is_admin:0,pmx_manage:0,', 'Guest User')",
"INSERT INTO `pmx_roles` (`pid`, `pmx`, `name`) VALUES (2, 'is_admin:1,pmx_manage:1,settings_manage:0,', 'Administrator')",

"INSERT INTO `pages` (`page_id`, `page_name`, `content`, `plugin_list`) VALUES (1, 'Home', 'Welcome to StrongWeb!<br /><br />This page is only temporary. You may log into the <a href=\"/admin\">StrongWeb Control Panel</a> and fine-tune your Website.', '')",

"INSERT INTO `links` (`link_id`, `link_text`, `link_url`, `link_enabled`) VALUES (1, 'Home Page', 'site://1', 1)"
);
			
			$sqlarray = array_merge( $sqlarray, $insertSQL);
			
			//Execute the SQL.
			$dict->ExecuteSQLArray($sqlarray);
			
			//Create the configurations file.
			generateConfigFile( $dbc );
			
			//Relocate the user to the admin panel.
			header('Location: ' . normalizeURLPath( WEB_DOC_ROOT.'/admin.php' ));
			
		}	
		else
		{
			$page_data = $tpl->compileKeys('install_finalize.html', array(
				'FINALIZE_FORM' 	=> $form->render(true) 
			));
		}
	break;	
}


$section_links = '<li>Error: No Links</li>';
$sl='';
foreach( $section_arr as $sect => $lbl )
{
	$link = '/installer.php?step='.$sect; 
	
	$class='';
	if( strtolower($panel) == $sect && empty($addons_sec) )
	{	$class = ' class="active"'; }
	
	$sl .='<li'.$class.' onclick="liClick(\''.$sect.'\')"><a id="'.$sect.'" href="#">'.$lbl.'</a></li>'."\n";
	
	$class = '';
}
if( !empty($sl) )
{	$section_links = $sl; }

$page_vars = array(
	"page_title" => 'StrongWeb CMS Installer',
	"content"	 => $page_data,
	'section_links'	=> $section_links,
	'addon_links'=> '',
	'addon_count'=> '0'
);

$tpl->RegisterTemplateVariable( $page_vars );
$tpl->Output();

exit();
?>