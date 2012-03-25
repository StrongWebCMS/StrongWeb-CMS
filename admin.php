<?php
/********************************************
*         admin.php
*
*   This page provides the interface for user
* authentication and administration of an
* established website.
**********************************************/

if( !defined('IN_SWCMS') )
{	define('IN_SWCMS', TRUE); }

require_once( './common.php' );

//Take care of installations before allowing admin controls.
if(!SOFTWARE_INSTALLED)
{	header('Location: ' . WEB_DOC_ROOT.'index.php' ); }

/** Set up admin panel variables for runtime. /**/
//Take the motions of registering some permissions.
//These MUST remain in the system to control the most basic of permissions seperations.
//Any of these that are already registered will not register a second time.
$permissions->registerPermission( 'is_admin', 'Can Log-in to the Administration Panel' );
$permissions->registerPermission( 'pmx_manage', 'Can Manage User/Admin Permission Configurations' );

//Load new template manager resources.
$tpl = new template( '_admin' );

//Load add-on class.
$addons = new addons();

//Set array for each panel and its lable. ( panel => label)
//TODO: This should be made configureable, as with all other "hard-coded" language.
$section_arr = array(
	'pages' 		=> 'Page Manager',
	'filemanager' 	=> 'File Manager',
	'links'			=> 'Navigation  Links',
	'users'			=> 'Users &amp; Permissions',
	'settings'	=> 'Settings',
	'updates'   => 'Software Updates',
	'addons'	=> 'Manage Addons'
);

//Set up the SEO variables.
$pageSEO_Vars = array(
	'admin_panel' 	=> 'admin/([a-z0-9-_]+)',
	'pages_sec'		=> 'pages/(edit|new|list|delete)',
	
	'links_sec'		=> 'links/(new|list)',
	
	'users_sec'		=> 'users/(add_role|edit_role|delete_role|add|edit|delete|list)',
	'user_id'		=> 'uid([0-9]+)',
	'starting_with'	=> 'starting_with-([a-z0-9]+)',
	'role_id' 		=> 'rid([0-9]+)',
	
	'addons_sec'	=> 'addons/([a-z0-9_]+)',
	'addon_name'	=> '.*?able/([a-z0-9_]+)'
);

$gpc->RegisterSEOVariable( $pageSEO_Vars );

//Find out what page is being accessed.
if( isset($_GET['admin_panel']) )
{	$panel = trim( strtolower( $gpc->get('GET', 'admin_panel') ) ); }
else
{	$panel = trim( strtolower( $gpc->get('SEO', 'admin_panel') ) ); }

//Find out the section, if any.
if( isset($_GET['addons_sec']) )
{	$addons_sec = trim( $gpc->get('GET', 'addons_sec') ); }
else
{	$addons_sec = trim( $gpc->get('SEO', 'addons_sec') ); }

//Check that the user is logged in and has permissions.
//Enforce the login policy
if( $_SESSION['user']->logged_in != true || $_SESSION['user']->validated != true )
{	$panel = 'login'; }

//Enforce the users Role.
if( $_SESSION['user']->hasPermission( 'is_admin' ) != true )
{	$panel = 'login'; }

//Set a default placeholder for our page data.
$page_data = 'An Error most likely occured.';

switch( $panel )
{
	/*********************************************
	*	Site Pages Controls
	**********************************************/
	case 'pages':
		require_once('./main/admin/pages.php');
	break;

	case 'users':
		require_once('./main/admin/users.php');
	break;
	
	case 'links':
		require_once('./main/admin/links.php');
	break;
	
	case 'filemanager':
		require_once('./main/admin/filesystem.php');
	break;
	
	case 'settings':
		require_once('./main/admin/settings.php');
	break;
	
	case 'updates':
		require_once('./main/admin/updates.php');
	break;
	
	case 'addons':
		include_once('./main/admin/addons.php');
	break;
	
	case 'login':
		$return_uri = str_replace(array('&action=logout', '?action=logout'), '', $_SERVER['REQUEST_URI']);
	
		$form = new Form("site_login_form", 580, '/index.php?action=login&return_uri='.base64_encode($return_uri) );
		$form->configure(array(
			"view" => new View_SideBySide(100),
			"enctype" => "multipart/form-data",
			"method"  => "post"
		));
		
		$form->addElement(new Element_Textbox("Username:", "username"));
		$form->addElement(new Element_Password("Password:", "password"));
		//$form->addElement(new Element_Checkbox('', 'remember', array('&nbsp;Remember my Log-In on this computer.')));
		$form->addElement(new Element_Button("Log In", 'submit', array('name'=>'login')));
		
		
		$form_data = $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) );
		
		$page_data = $tpl->compileKeys('login.html', array(
			'PAGE_TITLE' 		=> 'Administration Panel Log-In',
			'PAGE_HEAD_DATA'	=> $tpl->generatePageHead(),
			'LOGIN_FORM' 		=> $form_data
		), 3);
		
		echo $page_data;
		exit();
		
	break;
	
	default:
		$page_data = $tpl->compileKeys('panel_index.html', array(''));
	break;
}

$section_links = '<li>Error: No Links</li>';
$sl='';
foreach( $section_arr as $sect => $lbl )
{
	if( $config->GetConfig('SEO_LINKS_ENABLED') )
	{	$link = '/admin/'.$sect.'/'; }
	else
	{	$link = '/admin.php?admin_panel='.$sect; }
	
	$class='';
	if( strtolower($panel) == $sect && empty($addons_sec) )
	{	$class = ' class="active"'; }
	
	$sl .='<li'.$class.' onclick="liClick(\''.$sect.'\')"><a id="'.$sect.'" href="'.$link.'">'.$lbl.'</a></li>'."\n";
	
	$class = '';
}
if( !empty($sl) )
{	$section_links = $sl; }

$addon_links = ''; 
$adn_count=0;
foreach( $addons->GetEnabledAddonList() as $sect )
{
	if($sect == 'None'){continue;}
	
	if( $config->GetConfig('SEO_LINKS_ENABLED') )
	{	$link = '/admin/addons/'.$sect; }
	else
	{	$link = '/admin.php?admin_panel=addons&addons_sec='.$sect; }
	
	$lbl = ucwords(str_replace(array('_','-'), ' ', $sect));
	
	$class='';
	if( $addons_sec == $sect )
	{	$class = ' class="active"'; }
	
	$addon_links .= '<li'.$class.' onclick="liClickAddon(\''.$sect.'\')"><a id="'.$sect.'" href="'.$link.'">'.$lbl.'</a></li>'."\n";
	
	$adn_count++;
}

$page_vars = array(
	"page_title" => 'Administrator Control Panel',
	"content"	 => $page_data,
	"panel"		 => strtoupper($panel),
	'section_links'	=> $section_links,
	'addon_links'=> $addon_links,
	'addon_count'=> $adn_count
);

$tpl->RegisterTemplateVariable( $page_vars );
$tpl->Output();

?>