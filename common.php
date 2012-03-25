<?php

//Restrict this file so only certain files can run it.
if( !defined('IN_SWCMS') )
{	exit(); }

//Debugging tool.
define('DEBUG_OUTPUT_ON', false ); 

//Set the ROOT_PATH constant if it has not already been set.
define('ROOT_PATH', dirname(__FILE__));  
//Define the server's relative document root. 
//In most cases the "SITE_URL" is used after the installer is finished.
$wdr = str_replace('\\', '/', dirname( getenv("SCRIPT_NAME") ));
if( strlen($wdr) <= 0 )
{	$wdr = '/'; }

define('WEB_DOC_ROOT', $wdr);
unset($wdr);

//Starting the script-timer.
$time = explode(' ', microtime());
$startTime = $time[1] + $time[0];


//Import main library.
require_once( ROOT_PATH .'/main/main.php' );

//Change the error-handler to the custom one defined in main.
set_error_handler('SW_MsgHandler');
//Set reporting.
error_reporting(E_ALL);
ini_set("display_errors", 0);

//Import system function libraries.
require_once( ROOT_PATH.'/main/adodb/adodb-exceptions.inc.php' );
require_once( ROOT_PATH.'/main/adodb/adodb-errorhandler.inc.php' );
require_once( ROOT_PATH.'/main/adodb/adodb.inc.php' );
require_once( ROOT_PATH.'/main/PFBC/Form.php');
require_once( ROOT_PATH.'/main/cache.php' );
require_once( ROOT_PATH.'/main/addons.php' );
require_once( ROOT_PATH.'/main/template.php' );
require_once( ROOT_PATH.'/main/PasswordHash.php' );
//require_once( ROOT_PATH.'/main/libmail.php' );

//Here we set the status of the software as installed or not installed by looking for our config file and checking for any data.
if( file_exists(ROOT_PATH.'/config.php') && filesize(ROOT_PATH.'/config.php') > 0 )
{    
    require_once(ROOT_PATH.'/config.php'); 
    
	//Try a new connection with the given config from above.
	//The software shouldn't ever throw an exception here after installation.
	try
	{
		$db = ADONewConnection($db_type); 
    	$db->Connect($db_host, $db_user, $db_pass);
		define('SOFTWARE_INSTALLED', true);
	}
	catch( Exception $e)
	{	
		//SHUT DOWN EVERYTHING!  
		define('SOFTWARE_INSTALLED', false);
		
		$msg = '[Critical] Database a'.substr( $e->msg, 1, strlen($e->msg) ).
		       '<br /><br />Please verify that your database configurations are correct.';
		trigger_error($msg, E_USER_ERROR); 
	}
	
	unset( $db );
}
else {   
    define('SOFTWARE_INSTALLED', false); 
}

//If the software is installed then we can start up all the systems.
if( SOFTWARE_INSTALLED )
{
	$db = ADONewConnection($db_type); 
    $db->Connect($db_host, $db_user, $db_pass, $db_name);
	
	if( !$db->IsConnected() )
	{
		trigger_error($db->ErrorMsg(), E_USER_ERROR);
	}
	
    $db->SetFetchMode(ADODB_FETCH_ASSOC); // Modes:  ADODB_FETCH_NUM, ADODB_FETCH_ASSOC
    //$db->debug = true;
	
    $config = new ConfigCache();
    $cache  = new Cache();
    $gpc    = new GetPostCookie();
	$permissions = new Permissions();

    //Engage the user Session.
    if( !isset($_SESSION['user']) ) //No session set yet.
    {
		if( isset($_COOKIE[ $config->getConfig('SITE_COOKIE_NAME') ]) )
        {
			if( !auto_login( $_COOKIE[ $config->getConfig('SITE_COOKIE_NAME') ] ) )
            {    $_SESSION['user'] = new user( 1 ); }
        }
        else
        {    $_SESSION['user'] = new user(1); }
    }
    elseif( isset($_SESSION['user']) && get_class($_SESSION['user']) !== 'user') //Session set but somehow its not a user...
    {
		if( isset($_COOKIE[ $config->getConfig('SITE_COOKIE_NAME') ]) )
        {
			if( !auto_login( $_COOKIE[ $config->getConfig('SITE_COOKIE_NAME') ] ) )
            {    $_SESSION['user'] = new user( 1 ); }
        }
        else
        {    $_SESSION['user'] = new user(1); }
    }
    
	//Start the template engine with the default template directory resources.
    $tpl    = new template( 'main' );
}

?>