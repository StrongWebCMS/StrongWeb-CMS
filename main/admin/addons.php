<?php

if( !defined('IN_SWCMS') )
{	exit(); }


//Get the sub-section for this section.
if( isset($_GET['addons_sec']) )
{	$section = strtolower(trim($gpc->get('GET', 'addons_sec'))); }
else
{
	$section = strtolower(trim($gpc->get('SEO', 'addons_sec'))); 
	if( empty($section) )
	{	$section = 'list'; }
}

//Define our section.
switch( $section )
{
	case 'list':
		
		$enabledList = $addons->GetEnabledAddonList();
		$disabledList = $addons->GetDisabledAddonList();
		
		$tbl = new HTML_Table('addon_list', 'linklist', 0, 0, 0, array('summary'=>'Enabled-Addons Listing'));
		$tbl->addRow( );
		$tbl->addCell( 'Enabled Addons: ', null, 'header', array('align'=>'left') );
		$tbl->addCell( 'Options: ', null, 'header', array('width'=>'150') );
		
		if( count($enabledList) > 1)
		{
			$i=0;
			foreach($enabledList as $adn)
			{
				if($adn == 'None')
				{	continue; }
				
				$manageLink = '#errorInLinker';
				$disableLink = '#errorInLinker';
				
				if( $config->GetConfig('SEO_LINKS_ENABLED') )
				{	$manageLink = WEB_DOC_ROOT.'admin/addons/'.$adn; }
				else
				{	$manageLink = WEB_DOC_ROOT.'admin.php?admin_panel=addons&addons_sec='.$adn; }
				
				if( $config->GetConfig('SEO_LINKS_ENABLED') )
				{	$disableLink = WEB_DOC_ROOT.'admin/addons/disable/'.$adn; }
				else
				{	$disableLink = WEB_DOC_ROOT.'admin.php?admin_panel=addons&addons_sec=disable&addon_name='.$adn; }
				
				if( $i == 0 )
				{	$class = 'lite'; $i++; }
				else
				{	$class = 'dark'; $i--; }
				
				$tbl->addRow( $class );
				$tbl->addCell('<a href="'.$manageLink.'">'.ucwords(str_replace(array('_','-'), ' ', $adn))."</a>");
				$tbl->addCell('<a href="'.$manageLink.'">[Manage]</a> | <a href="'.$disableLink.'">[Disable]</a>', null, 'data', array('align'=>'center') );
			}
		}
		else
		{
			$tbl->addRow('dark');
			$tbl->addCell(' You have not yet enabled any addons. ', null, 'data', array('colspan'=>2, 'align'=>'center'));
		}
		
		
		
		$tbl2 = new HTML_Table('ready_addon_list', 'linklist', 0, 0, 0, array('summary'=>'Disabled-Addon Listing'));
		$tbl2->addRow( );
		$tbl2->addCell( 'Available Addons: ', null, 'header', array('align'=>'left') );
		$tbl2->addCell( 'Options: ', null, 'header', array('width'=>'150') );
		
		if( count($disabledList) > 0 )
		{
			$i=0;
			foreach($disabledList as $adn)
			{
				$enableLink = '#errorInLinker';
				
				if( $config->GetConfig('SEO_LINKS_ENABLED') )
				{	$enableLink = WEB_DOC_ROOT.'admin/addons/enable/'.$adn; }
				else
				{	$enableLink = WEB_DOC_ROOT.'admin.php?admin_panel=addons&addons_sec=enable&addon_name='.$adn; }
				
				if( $i == 0 )
				{	$class = 'lite'; $i++; }
				else
				{	$class = 'dark'; $i--; }
				
				$tbl2->addRow( $class );
				$tbl2->addCell( ucwords(str_replace(array('_','-'), ' ', $adn)) );
				$tbl2->addCell('<a href="'.$enableLink.'">[Enable]</a>', null, 'data', array('align'=>'center') );
			}
		}
		else
		{
			$tbl2->addRow('dark');
			$tbl2->addCell(' There are no addons available to enable. ', null, 'data', array('colspan'=>2, 'align'=>'center'));
		}
		
		$keys = array(
			'ENABLED_ADDONS' 	=> $tbl->render(),
			'DISABLED_ADDONS'	=> $tbl2->render()
		);	
		
		$page_data = $tpl->compileKeys('addons.html', $keys);
	
	break;
	case 'enable':
		
		$disabledList = $addons->GetDisabledAddonList();
		$addon = $gpc->get('SEO', 'addon_name');
		
		if( in_array($addon, $disabledList) )
		{
			$installResult = $addons->processInstall( $addon );
			
			switch( $installResult )
			{
			case 0x1:
				$result =  'addon was successfully installed';
				$addons->EnableAddon( $addon );
			break;
			
			case 0x8: 
				$result =  'addon file not found!';
			break;
			
			case 0x10:
				$result =  'addon Class did not match file name.';
			break;
			
			case 0x20:
				$result =  'error reading addon XML resource file.';
			break;
			
			case 0x40:
				$result =  'error installing image resources. Resource not found.';
			break;
			
			case 0x41:
				$result =  'error installing image resources. Resource failed to copy.';
			break;
			
			case 0x42:
				$result =  'error installing CSS resources.';
			break;
			
			case 0x44:
				$result =  'error installing JavaScript resources.';
			break;
			
			case 0x48:
				$result =  'error installing HTML resources.';
			break;
			}
		}
		else
		{	$result = 'addon was not in disabled list.'; }
		
		if( $config->GetConfig('SEO_LINKS_ENABLED') )
		{	$redirectURL = WEB_DOC_ROOT.'admin/addons/'; }
		else
		{	$redirectURL = WEB_DOC_ROOT.'admin.php?admin_panel=addons'; }
		
		$page_data = $tpl->compileKeys('addons_status.html', array(
			'STATUS_MSG' 	=> $result,
			'REDIRECT_URL' 	=> $redirectURL
		));
		
	break;
	
	case 'disable':
		$enabledList = $addons->GetEnabledAddonList();
		$addon = $gpc->get('SEO', 'addon_name');
		
		if( in_array($addon, $enabledList) )
		{
			$addons->DisableAddon( $addon );
		}
		
		if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
		{	header('Location: '.WEB_DOC_ROOT.'admin/addons'); }
		else
		{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=addons'); }
	break;
	
	
	default:
		//Here we need to check the enabled addons list. Any input to this case should be used to bring up an add-on configuration panel.
		$enabledList = $addons->GetEnabledAddonList();
		
		if( in_array($section, $enabledList) )
		{
			$page_data = $addons->ManageAddon( $section );
		}
		else
		{
			if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
			{	header('Location: '.WEB_DOC_ROOT.'admin/addons'); }
			else
			{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=addons'); }
		}
	break;
	
}


?>