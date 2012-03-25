<?php

if( !defined('IN_SWCMS') )
{	exit(); }

		
		//Get the sub-section for this section.
		if( isset($_GET['pages_sec']) )
		{	$section = strtolower(trim($gpc->get('GET', 'pages_sec'))); }
		else
		{
			$section = strtolower(trim($gpc->get('SEO', 'pages_sec'))); 
			if( empty($section) )
			{	$section = 'list'; }
		}
		
		//Set the page id from the url.
		if( isset($_GET['page_id']) )
		{	$pg_id = $gpc->get('GET', 'page_id', 'i'); }
		else
		{	$pg_id = $gpc->get('SEO', 'page_id', 'i'); }
		
		//Define our section.
		switch( $section )
		{
			case 'new': /* Creating a New page. */
			
				$form = new Form("PageConfigNew", 650, $_SERVER['REQUEST_URI'] );

				$form->addElement( new Element_Textbox('Page Name: ', 'page_name', array(
					'required'		=> 1,
					'description'	=> "Text used in the title-bar of browsers to identify the page."
				)));
				$form->addElement( new Element_TinyMCE('Page Body: ', 'content', array(
					'description'	=> "The content of the page. HTML/Text, or an 'addon' from the system can be used to create a page's content."
				)));
				$form->addElement( new Element_Select('Embed an Addon', 'addon', $addons->GetEnabledAddonList(), array(
					'description'	=> 'Select an Addon here to create a page using an addon instead of manually entered HTML/Text from above.'
				)));
				
				$form->addElement( new Element_Button() );
				
				if( !empty($_POST) && Form::isValid('PageConfigNew') )
				{
					$content='';
					$addon_list='';
					$page_name='';
					$er=0;
					
					//Even if the form checks for "valid" submissions, we need to do that again here.
					
					//check page name.
					if( isset($_POST['page_name']) && !empty($_POST['page_name']) )
					{	$page_name = $gpc->get('POST', 'page_name'); }
					else{ $er++; }
					
					//check for page content.
					if( isset($_POST['content']) && !empty($_POST['content']) )
					{	$content = $gpc->get('POST', 'content'); }
					else{ $er_no_content=true; }
					
					//check for an addon.
					if( isset($_POST['addon']) && strtolower($_POST['addon']) != 'none' )
					{
						$addon = $gpc->get('POST', 'addon');
						$enabledAddons = $addons->GetEnabledAddonList();
						if( in_array($addon, $enabledAddons) )
						{
							$addon_list =  strtolower($addon).'|'; 
							$content =  $_POST['content'] ."\n\n". '{P_'.strtoupper($addon).'}';
						}
						else
						{	$content = $_POST['content']; }
					}
					else{ $er_no_addon=true;  }
					
					//if there is no addon or any content then output an error.
					if( isset($er_no_addon) && isset($er_no_content) )
					{	$er++; }
					
					if( $er != 0)
					{	trigger_error('There was an error attempting to save the field.<br />Please ensure all fields are valid before submitting.', E_USER_ERROR); }
					else
					{
						$record = array(
							'page_name' 	=> $page_name,
							'content' 		=> $content,
							'plugin_list' 	=> $addon_list
						);
						
						$db->AutoExecute('pages', $record, 'INSERT');
						
						if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
						{	header('Location: '.WEB_DOC_ROOT.'admin/pages/list'); }
						else
						{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=pages&pages_sec=list'); }
					}
				}
				else
				{
					$page_data = $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) );
				}
				
			break;
			case 'edit': /* Editing an existing/old page.  */
				if( $pg_id == 0 || empty($pg_id))
				{	
					if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
					{	header('Location: '.WEB_DOC_ROOT.'admin/pages/list'); }
					else
					{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=pages&pages_sec=list'); }
				}
				
				$rs = $db->Execute('SELECT * FROM `pages` WHERE `page_id`='.$pg_id );
			
				$form = new Form("PageConfigEdit", 650, $_SERVER['REQUEST_URI']);
				//$form->configure(array());
				$form->setValues(array(
					"page_name" => $rs->fields['page_name'],
					"content" 	=> str_replace( array('{','}'), array('{ -!-', '-!- }'), $rs->fields['content'] ),
					'addon' 	=> trim( str_replace("|", '', $rs->fields['plugin_list']) )
				));
				
				$form->addElement( new Element_Textbox('Page Name: ', 'page_name', array(
					'required'		=> 1,
					'description'	=> "Text used in the title-bar of browsers to identify the page."
				)));
				$form->addElement( new Element_TinyMCE('Page Body: ', 'content', array(
					'description'	=> "The content of the page. HTML/Text, or an 'addon' from the system can be used to create a page's content."
				)));
				$form->addElement( new Element_Select('Embed an Addon', 'addon', $addons->GetEnabledAddonList(), array(
					'description'	=> 'Select an Addon here to create a page using an addon instead of manually entered HTML/Text from above.'
				)));
				
				$form->addElement( new Element_Button() );
				
				if( !empty($_POST) && Form::isValid('PageConfigEdit') )
				{
					$content='';
					$addon_list='';
					$page_name='';
					$er=0;
					
					//Even if the form checks for "valid" submissions, we need to do that again here.
					
					//check page name.
					if( isset($_POST['page_name']) && !empty($_POST['page_name']) )
					{	$page_name = $gpc->get('POST', 'page_name'); }
					else{ $er++; }
					
					//check for page content.
					if( isset($_POST['content']) && !empty($_POST['content']) )
					{
						$content = $gpc->get('POST', 'content'); 
					}
					else{ $content=''; }
					
					//check for an addon.
					if( isset($_POST['addon']) && strtolower($_POST['addon']) != 'none' )
					{
						$addon = $gpc->get('POST', 'addon');
						$enabledAddons = $addons->GetEnabledAddonList();
						if( in_array($addon, $enabledAddons) )
						{
							$addon_list =  strtolower($addon).'|';
							
							if( !preg_match('#\\{P_(.*?)\\}#ism', $_POST['content'] ) )
							{	$content =  $_POST['content']."\n\n". '{P_'.strtoupper($addon).'}'; }
						}
					}
					elseif( isset($_POST['addon']) && strtolower($_POST['addon']) == 'none' )
					{  
						preg_match_all('#\\{P_(.*?)\\}#i', $content, $m );
						for($i=0; $i < count($m[0]); $i++)
						{
							if( isset($m[1][$i]) && !empty($m[1][$i]) )
							{
								$content = str_replace( $m[0][$i], '', $content );
							}
						}
					}
					
					//if there is no addon or any content then output an error.
					if( isset($er_no_addon) && isset($er_no_content) )
					{	$er++; }
					
					if( $er != 0)
					{	trigger_error('There was an error attempting to save the field.<br />Please ensure all fields are valid before submitting.', E_USER_ERROR); }
					else
					{
						$record = array(
							'page_name' 	=> $page_name,
							'content' 		=> $content,
							'plugin_list' 	=> $addon_list
						);
						
						//$db->debug = true;
						$db->AutoExecute('pages', $record, 'UPDATE', 'page_id ='.$rs->fields['page_id']);
						
						if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
						{	header('Location: '.WEB_DOC_ROOT.'admin/pages/list'); }
						else
						{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=pages&pages_sec=list'); }
					}
				}
				else
				{
					$page_data = $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) );
				}
			break;
			
			case 'delete': /*  Delete an existing Page.  */
				if( $pg_id == 0 || empty($pg_id))
				{	
					if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
					{	header('Location: '.WEB_DOC_ROOT.'admin/pages/list'); }
					else
					{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=pages&pages_sec=list'); }
				}
				
				$rs = $db->Execute('SELECT page_name FROM `pages` WHERE `page_id`='.$pg_id );
			
				$form = new Form("PageConfigDelete", 650, $_SERVER['REQUEST_URI']);
				
				$form->addElement( new Element_YesNo('Are you sure?', 'certain') );
				
				$form->addElement( new Element_Button() );
				
				if( !empty($_POST) && Form::isValid('PageConfigDelete') )
				{
					
					if( $_POST['certain'] != 1)
					{
						if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
						{	header('Location: '.WEB_DOC_ROOT.'admin/pages/list'); }
						else
						{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=pages&pages_sec=list'); }
					}
					else
					{
						$sql = 'DELETE FROM `pages` WHERE `page_id`='.$pg_id;
						
						//$db->debug = true;
						$db->Execute( $sql );
						
						if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
						{	header('Location: '.WEB_DOC_ROOT.'admin/pages/list'); }
						else
						{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=pages&pages_sec=list'); }
					} /**/
				}
				else
				{
					$keys = array(
						'CONFIRM_FORM' 	=> $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) ),
						'page_name'		=> $rs->fields['page_name']
					);	
					
					$page_data = $tpl->compileKeys('pages_delete.html', $keys);
				}
			break;
			
			case 'list': /* List existing pages, allow for the addition of new pages. */
			default:
				$rs = $db->Execute('SELECT page_id, page_name FROM `pages`');
				
				$addLink='#errorInLinker';
				if( $config->GetConfig('SEO_LINKS_ENABLED') )
				{	$addLink = WEB_DOC_ROOT.'admin/pages/new'; }
				else
				{	$addLink = WEB_DOC_ROOT.'admin.php?admin_panel=pages&pages_sec=new'; }
					
			
				$tbl = new HTML_Table('page_list', 'linklist', 0, 0, 0, array('summary'=>'Page Listing'));
				$tbl->addRow( );
				$tbl->addCell( 'Page Name', null, 'header', array('align'=>'left') );
				$tbl->addCell( 'Options', null, 'header', array('width'=>'150') );
				$tbl->addRow( 'dark' );
				$tbl->addCell('<a href="'.$addLink.'">Add a New Page</a>', null, 'data', array('colspan'=>2, 'align'=>'center') );
				
				$i=0;
				foreach($rs as $page)
				{
					$editLink = '#errorInLinker';
					$deleteLink = '#errorInLinker';
					
					if( $config->GetConfig('SEO_LINKS_ENABLED') )
					{	$editLink = WEB_DOC_ROOT.'admin/pages/edit/pg'.$page['page_id']; }
					else
					{	$editLink = WEB_DOC_ROOT.'admin.php?admin_panel=pages&pages_sec=edit&page_id='.$page['page_id']; }
					
					if( $config->GetConfig('SEO_LINKS_ENABLED') )
					{	$deleteLink = WEB_DOC_ROOT.'admin/pages/delete/pg'.$page['page_id']; }
					else
					{	$deleteLink = WEB_DOC_ROOT.'admin.php?admin_panel=pages&pages_sec=delete&page_id='.$page['page_id']; }
					
					if( $i == 0 )
					{	$class = 'lite'; $i++; }
					else
					{	$class = 'dark'; $i--; }
					
					$tbl->addRow( $class );
					$tbl->addCell('<a href="'.$editLink.'">'.$page['page_name']."</a>");
					$tbl->addCell('<a href="'.$editLink.'">[Edit]</a> | <a href="'.$deleteLink.'">[Delete]</a>', null, 'data', array('align'=>'center') );
				}
				
				if( $class == 'dark' )
				{	$class = 'lite'; }
				elseif( $class == 'lite' )
				{	$class = 'dark'; }
				
				$tbl->addRow( $class );
				$tbl->addCell('<a href="'.$addLink.'">Add a New Page</a>', null, 'data', array('colspan'=>2, 'align'=>'center') );
				
				$page_data = $tbl->render();
			break;
		}

?>