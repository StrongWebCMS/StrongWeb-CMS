<?php

if( !defined('IN_SWCMS') )
{	exit(); }
	
		//Get the sub-section for this section.
		if( isset($_GET['links_sec']) )
		{	$section = strtolower(trim($gpc->get('GET', 'links_sec'))); }
		else
		{
			$section = strtolower(trim($gpc->get('SEO', 'links_sec'))); 
		}
		
		if( empty($section) )
		{	$section = 'list'; }
		
		//Define our sections.
		switch( $section )
		{
			case 'new': /* Create a new Link. */
			
				$pageListArray = array(0=>'none');
				$pageName2Id = array();
				$rs = $db->Execute('SELECT `page_id`, `page_name` FROM `pages`');
				foreach($rs as $row )
				{
					$pageListArray[ $row['page_id'] ] = $row['page_name'];
					$pageName2Id[ $row['page_name'] ] = $row['page_id'];
				}
			
				$form = new Form("LinkConfigNew", 650, $_SERVER['REQUEST_URI'] );

				//Add an Ajax option for adding links to internal addons.
				$form->addElement( new Element_Radio('What kind of Link do you want to add?', 'linkType', array('Link to a local page.', 'Link to an external page.'), array(
					'onclick'	=> 'toggleLinkType( this.value )'
				)));
				
				$form->addElement(new Element_HTMLExternal('<div id="externalLink" style="display: none;">'));
				$form->addElement( new Element_Textbox('Link Name: ', 'link_name', array(
					'description'	=> "Text name used for or to describe the link."
				)));
				$form->addElement( new Element_Textbox('URL: ', 'link_url', array(
					'description'=>'A full URL, with protocol. E.g.: http://www.somesite.com/file.html',
					'validation'=> new Validation_Url
				)));
				$form->addElement(new Element_HTMLExternal('</div>'));
				
				$form->addElement(new Element_HTMLExternal('<div id="localLink" style="display: none;">'));
				$form->addElement( new Element_Textbox('Link Name: ', 'link_name2', array(
					'description'	=> "Text name used for or to describe the link."
				)));
				
				$form->addElement( new Element_Select('Local Page: ', 'page_id', $pageListArray, array(
					'description'=>'Select an existing local page from the list.',
				)));
				$form->addElement(new Element_HTMLExternal('</div>'));
				
				$form->addElement( new Element_Button() );
				
				if( !empty($_POST) && Form::isValid('LinkConfigNew') )
				{
					$lName='';
					$lURL ='';
					$er=0;
					
					if( isset($_POST['linkType']) && $_POST['linkType'] == 'Link to a local page.' && isset($pageName2Id[ $gpc->get('POST', 'page_id') ]) )
					{
						$lName	= $gpc->get('POST', 'link_name2');
						$lURL	= 'site://'. $pageName2Id[$gpc->get('POST', 'page_id')];
					}
					elseif( isset($_POST['linkType']) && $_POST['linkType'] == 'Link to an external page.' )
					{
						$lName	= $gpc->get('POST', 'link_name');
						$lURL	= $gpc->get('POST', 'link_url');
					}
					else
					{	$er++; }
					
					
					if( $er != 0)
					{	trigger_error('There was an error attempting to save the form.<br />Please retry again.', E_USER_ERROR); }
					else
					{
						$record = array(
							'link_text' => $lName,
							'link_url' 	=> $lURL
						);
						
						$db->AutoExecute('links', $record, 'INSERT');
						
						//Update any cached data.
						$cache->unsetCacheFile('_nav_');
						
						if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
						{	header('Location: '.WEB_DOC_ROOT.'admin/links/list'); }
						else
						{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=links&links_sec=list'); }
					}
				}
				else
				{
					$keys = array(
						'ADD_LINK_FORM' => $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) )
					);
					
					$page_data = $tpl->compileKeys('links_add.html', $keys );
				}
				
			break;			
			case 'list': /* List existing pages, allow for the addition of new pages. */
			default: 
			
				$addLink='#errorInLinker';
				if( $config->GetConfig('SEO_LINKS_ENABLED') )
				{	$addLink = WEB_DOC_ROOT.'admin/links/new'; }
				else
				{	$addLink = WEB_DOC_ROOT.'admin.php?admin_panel=links&pages_sec=new'; }
					
				//Get pages and set there data into arrays for user-selection.
				$options = array();
				$options_end = array();
				$defaults= array();
				$link_id_array=array();
				$rs = $db->Execute('SELECT `link_id`, `link_text`, `link_enabled` FROM `links` ORDER BY `link_enabled` ASC');
				if( $rs->RecordCount() > 0 )
				{
					foreach( $rs as $r )
					{
						$link_id_array[ $r['link_text'] ] = $r['link_id'];
						
						if( $r['link_enabled'] >= 1)
						{	
							$options[] = $r['link_text']; 
							$defaults[] = $r['link_text'];
						}
						else
						{	$options_end[] = $r['link_text']; }
					}			
					$options = array_merge($options, $options_end);
					
					$form = new Form("LinkConfig", 650, $_SERVER['REQUEST_URI']);
					$form->setValues(array(
						'cs_list'	=>	$defaults
					));
					
					$form->addElement( new Element_Hidden('formName', 'LinkConfig'));
					$form->addElement( new Element_Checksort('Select and Sort: ', 'cs_list', $options, array(
						'description' => 'Use this list to enable/dissable and re-arrange the list of links shown in the main Navigation.'
					)));
					$form->addElement( new Element_YesNo('Delete Checked Links?', 'deleteUpdate', array(
						'description'=>'Select this option if you no longer wish to use a selected link.'
					)));
					
					$form->addElement( new Element_Button('Submit','submit', array('name'=>'submit')) );
					
					if( !empty($_POST) && Form::isValid('LinkConfig') )
					{
						if( isset($_POST['deleteUpdate']) && $_POST['deleteUpdate'] == 1 )
						{
							foreach( $gpc->get('POST', 'cs_list') as $pos => $lText )
							{
								$sql = 'DELETE FROM `links` WHERE `link_id` = '.$link_id_array[ $lText ] ;
								
								$db->Execute( $sql );
								
							}
						}
						else
						{
							$record = array( 'link_enabled'=> 0 );
							
							$db->AutoExecute('links', $record, 'UPDATE', 'link_enabled > 0' );
							
							foreach( $gpc->get('POST', 'cs_list') as $pos => $lText )
							{
								$db->AutoExecute('links', array('link_enabled'=>($pos + 1)), 'UPDATE', 'link_text = '.$db->qstr($lText) );
							}
						}
						
						//Update any cached data.
						$cache->unsetCacheFile('_nav_');
						
						if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
						{	header('Location: '.WEB_DOC_ROOT.'admin/links/list'); }
						else
						{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=links&links_sec=list'); }
						
					}
					else
					{
						$keys = array( 
							'ADD_LINK_URL'	=> $addLink,
							'LIST_FORM' 	=> $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) )
						);
						
						$page_data = $tpl->compileKeys('links_list.html', $keys);
					}
				}
				else
				{
					header( 'Location: '.$addLink );
				}
			break;
	
		}

?>