<?php

if( !defined('IN_SWCMS') )
{	exit(); }

//Get the sub-section for this section.
if( isset($_GET['settings_sec']) )
{	$section = strtolower(trim($gpc->get('GET', 'settings_sec'))); }
else
{
	$section = strtolower(trim($gpc->get('SEO', 'settings_sec'))); 
	if( empty($section) )
	{	$section = 'list'; }
}

//Define our section.
switch( $section )
{
	case 'list':
		
		$defaults = array(
			'cfg[site_url]' 			=> $config->GetConfig( 'SITE_URL' ),
			'cfg[site_cookie_name]' 	=> $config->GetConfig( 'SITE_COOKIE_NAME' ),
			'cfg[cache_on]' 			=> ($config->GetConfig( 'CACHE_ON' ) == true) ? 1 : 0,
			'cfg[seo_links_enabled]' => ($config->GetConfig( 'SEO_LINKS_ENABLED' ) == true) ? 1 : 0
		);
		
		//print_r( $defaults );
		
		$form = new Form("SettingsConfig", 720, $_SERVER['REQUEST_URI'] );
		$form->setValues($defaults);
		
		$form->addElement( new Element_Textbox('Website URL:', 'cfg[site_url]', array(
			'description' => 'The domain name URL, with protocol, used to access the index page of this website (e.g.: http://www.mydomain.com )'
		)));
		
		$form->addElement( new Element_YesNo('Enable System Cache:', 'cfg[cache_on]', array(
			'description' => 'Allows the system to create temporary data in order to enhance page-load times and over-all preformance.'
		)));
		
		$form->addElement( new Element_YesNo('Enable SEO Links:', 'cfg[seo_links_enabled]', array(
			'description' => 'Allows the system to use Search-Engine-Optimized links/URLs.'
		)));
		
		//*
		$form->addElement( new Element_Textbox('Auto-login Cookie Name:', 'cfg[site_cookie_name]', array(
			'description' => 'The name given to the Web-Cookie used to facilitate automatic logins.'
		)));/**/
		
		$form->addElement( new Element_Button());
		
		if( !empty($_POST) && Form::isValid('SettingsConfig') )
		{
			if(isset($_POST['cfg']) && !empty($_POST['cfg']) && is_array($_POST['cfg']))
			{
				foreach( $_POST['cfg'] as $k => $v )
				{
					$db->AutoExecute('config', array('cfg_value'=>$v), 'UPDATE', "cfg_name=".$db->qstr($k)." AND cfg_for='system'");
				}
				
				$cache->clearCache( 'sys808' );
			}
				
			if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
			{	header('Location: '.WEB_DOC_ROOT.'admin/settings'); }
			else
			{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=settings'); }
		}
		else
		{
			if($_SESSION['user']->HasPermission('settings_manage') == false)
			{
				$keys = array(
					'SETTINGS_FORM' 	=> 'You do not have access to manage the system settings.',
				);	
			}
			else
			{
				$keys = array(
					'SETTINGS_FORM' 	=> $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) )
				);	
			}
			
			$page_data = $tpl->compileKeys('settings.html', $keys);
		}
	
	break;
	
}

?>