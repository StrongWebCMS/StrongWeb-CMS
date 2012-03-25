<?php
/********************************************
*         index.php
*
*   This page allows the system to logically 
* sort out what page a user is requesting.
**********************************************/

define('IN_SWCMS', TRUE);

require_once( './common.php' );

//Check to see if the user needs to install the software.
if( file_exists(ROOT_PATH.'/installer.php') && !SOFTWARE_INSTALLED && !preg_match('#installer#i', $_SERVER['REQUEST_URI']) )
{
	header('Location: ' . normalizeURLPath( WEB_DOC_ROOT.'/installer.php' ));
	exit();
}
elseif( !SOFTWARE_INSTALLED )
{
	trigger_error('Critical Error: The system could not find the configurations file (\'config.php\') in the base directory.<br />'.
	'Additionally, the installer file could not be located to generate the config file.<br /><br />'.
	'Please upload a copy of the configurations file or restore the \'installer.php\' file to the \'main\' directory and re-install the software.',
	E_USER_ERROR);	
}

//Processing for log-in/out and validation.
if( isset($_GET['action']) )
{
switch( strtolower($_GET['action']) )
{
	//User logging in.
	case 'login': 
		if( isset($_POST['username']) && isset($_POST['password']))
		{
			$username = $_POST['username'];
			$password = $_POST['password'];
			
			$remember = (isset($_POST['remember']) && !empty($_POST['remember']) ) ? true : false;
			
			login($username, $password, $remember); 
		}
	
		//if there was a return path encoded to the login, we need to send the user back to that page.
		if( isset($_GET['return_uri']) && !empty($_GET['return_uri']) )
		{
			$uri = base64_decode($_GET['return_uri']);
			
			//This should only accept URIs relative to the site. 
			if( !preg_match('#://#is', $uri) )
			{
				header('Location: '.$uri); 
				exit();
			}
		}
		
		header('Location: ' . WEB_DOC_ROOT);
		exit();
	break;
	
	//User logging out.
	case 'logout':
		//A log-out is simply removing that session and initializing a new session for the user.
		unset( $_SESSION['user'] );
		$_SESSION['user'] = new User(1);
		
		//Users who use "remember me" or auto-login will have to have that cookie removed.
		if(isset($_COOKIE[$config->GetConfig('SITE_COOKIE_NAME')])) 
		{
			$login_cookie_time =  time() - (60*60*24*365);
			set_cookie($config->GetConfig('SITE_COOKIE_NAME'), '', $login_cookie_time);
		}
		
		header('Location: ' . WEB_DOC_ROOT);
		exit();
	break;
	
	//User validating login credintials.
	//only accessed via ajax modal-box in admin-panel currently.
	case 'validate_login':
		//User MUST be logged in already to validate their credintials.
		if( $_SESSION['user']->logged_in == true && $_SESSION['user']->validated != true )
		{
			if( isset($_POST['name']) && isset($_POST['pass']) )
			{
				$ret = valid_login( $_POST['name'], $_POST['pass'] );
				
				if( $ret === false)
				{	echo 'false'; }
				elseif( $ret === true)
				{	echo 'true'; }
				else
				{	echo 'false '.$ret;}
			}
		}
		exit();
	break;
}
}

//Here we find out what page is being navigated to.
//It can only be a page-id or the admin panel from this point, so check for both.
if( isset($_GET['page_id']) )
{	$page_id = $gpc->get('GET', 'page_id', 'i'); }
else
{	
	$page_id = $gpc->get('SEO', 'page_id', 'i'); 
	
	if( $page_id <= 0 || empty($page_id) )
	{	$page_id = 1; }
}

//Send users to the admin panel if they requested it.
$in_admin = $gpc->get('SEO', 'in_admin');
if( !empty($in_admin) )
{
	include_once(ROOT_PATH.'/admin.php');
	exit();	//Close the script once admin.php is finished.
}
//End admin panel conditional.

/* Benchmark code.
$time = explode(' ', microtime());
$finishTime = $time[1] + $time[0];
echo round(($finishTime - $startTime), 4)."<br />\n";
/**/

//Here we do the checking for the page.
//Attempt to get the page requested from the database.
$rs = $db->Execute("SELECT * FROM `pages` WHERE `page_id`={$page_id}");

//Check if the page data was found.
if( $rs->RecordCount() > 0 )
{
	//check the page-type.
	if( $rs->fields['page_type'] == 2 )//A post-page
	{
		//Get the posts info from the database, cache them for later use.
		$rs = $db->Execute("SELECT * FROM `posts` WHERE `page_id`={$page_id}");
		
		//With the extracted data, make the posts into formated HTML for insertion to the page.
	}
	else
	{
		//load template-variables.
		$page_vars = array(
			"page_title" => 'Title: '.$rs->fields['page_name'],
			"content"	 => $rs->fields['content']
		);
	}
	
	//tell the template manager to pre-load some plug-ins for the page.
	$tpl->RegisterAddons( $rs->fields['plugin_list'] );
	$tpl->RegisterTemplateVariable( $page_vars ); //give tpl manager the page variables.
	$tpl->Output();//Output the page.
}
else//There was no data found. 
{
	//This should be an actual 404 page, with the proper headers.
	echo '404 File Not Found!';
}



?>