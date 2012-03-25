<?php

if( !defined('IN_SWCMS') )
{	exit(); }


//Must be able to:
//List Users, Edit Users, Delete Users.
//List permission roles, edit role, add role, delete role.

//Get the sub-section for this section.
if( isset($_GET['users_sec']) )
{	$section = strtolower(trim($gpc->get('GET', 'users_sec'))); }
else
{
	$section = strtolower(trim($gpc->get('SEO', 'users_sec'))); 
}

if( empty($section) )
{	$section = 'list'; }

//Define our section.
switch( $section )
{
	case 'list': /* List all users.  */
		$addLink='#errorInLinker';
		if( $config->GetConfig('SEO_LINKS_ENABLED') )
		{	$addLink = WEB_DOC_ROOT.'admin/users/add'; }
		else
		{	$addLink = WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=add'; }
		
		$tbl = new HTML_Table('user_list', 'linklist', 0, 0, 0, array('summary'=>'Registered User Listing'));
		$tbl->addRow( );
		$tbl->addCell( 'User Name', null, 'header', array('align'=>'left') );
		$tbl->addCell( 'Options', null, 'header', array('width'=>'150') );
		$tbl->addRow( 'dark' );
		$tbl->addCell('<a href="'.$addLink.'">Add a New User</a>', null, 'data', array('colspan'=>2, 'align'=>'center') );
		
		if( isset($_GET['starting_with']) )
		{	$startingWith = strtolower(trim($gpc->get('GET', 'starting_with'))); }
		else
		{
			$startingWith = strtolower(trim($gpc->get('SEO', 'starting_with'))); 
		}
		
		if( !empty($startingWith) )
		{	
			$startingWith = $db->qstr( $startingWith.'%' );
			$where = 'WHERE name LIKE '.$startingWith. ' OR name LIKE '. strtoupper($startingWith);
			
			$rs = $db->Execute('SELECT uid, name FROM users '. $where);
			
			if( $rs->RecordCount() >= 1)
			{
				$i=0;
				foreach($rs as $user)
				{
					$editLink = '#errorInLinker';
					$deleteLink = '#errorInLinker';
					
					if( $config->GetConfig('SEO_LINKS_ENABLED') )
					{	$editLink = WEB_DOC_ROOT.'admin/users/edit/uid'.$user['uid']; }
					else
					{	$editLink = WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=edit&user_id='.$user['uid']; }
						
					if( $config->GetConfig('SEO_LINKS_ENABLED') )
					{	$deleteLink = WEB_DOC_ROOT.'admin/users/delete/uid'.$user['uid']; }
					else
					{	$deleteLink = WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=delete&user_id='.$user['uid']; }
					
					if( $i == 0 )
					{	$class = 'lite'; $i++; }
					else
					{	$class = 'dark'; $i--; }
					
					$tbl->addRow( $class );
					$tbl->addCell('<a href="'.$editLink.'">'.$user['name']."</a>");
					
					if( $user['uid'] <= 2 )
					{
						$tbl->addCell('<a href="'.$editLink.'">[Edit]</a>', null, 'data', array('align'=>'center') );
					}
					else
					{
						$tbl->addCell('<a href="'.$editLink.'">[Edit]</a> | <a href="'.$deleteLink.'">[Delete]</a>', null, 'data', array('align'=>'center') );
					}
				}
			}
			else
			{
				$tbl->addRow( 'dark' );
				$tbl->addCell( 'Could not find any Users who\'s name starts with \''. str_replace(array("'",'%'), array('',''), $startingWith) .'\'.', null, 'data', array(
					'align'=>'center', 
					'colspan' => 2
				));
			}
		}
		else
		{
			$tbl->addRow( 'dark' );
			$tbl->addCell( 'Use the sort form above to list available users.', null, 'data', array('align'=>'center', 'colspan' => 2));
		}
			
		//if( $class == 'dark' )
		//{	$class = 'lite'; }
		//elseif( $class == 'lite' )
		//{	$class = 'dark'; }
		
		//$tbl->addRow( $class );
		//$tbl->addCell('<a href="'.$addLink.'">Add a New Page</a>', null, 'data', array('colspan'=>2, 'align'=>'center') );
		
		/*****************************
		*  Listing for User Roles.
		*****************************/
		$addLink='#errorInLinker';
		if( $config->GetConfig('SEO_LINKS_ENABLED') )
		{	$addLink = WEB_DOC_ROOT.'admin/users/add_role'; }
		else
		{	$addLink = WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=add_role'; }
		
		$rs = $permissions->getRolesList();
		
		$tbl2 = new HTML_Table('role_list', 'linklist', 0, 0, 0, array('summary'=>'User-Role Listing'));
		$tbl2->addRow( );
		$tbl2->addCell( 'Permission Role Name', null, 'header', array('align'=>'left') );
		$tbl2->addCell( 'Options', null, 'header', array('width'=>'150') );
		$tbl2->addRow( 'dark' );
		$tbl2->addCell('<a href="'.$addLink.'">Add a New User Role</a>', null, 'data', array('colspan'=>2, 'align'=>'center') );
		
		if( count($rs) > 0 )
		{
			$i=0;
			foreach($rs as $pid => $roleName )
			{
				$editLink = '#errorInLinker';
				$deleteLink = '#errorInLinker';
				
				if( $config->GetConfig('SEO_LINKS_ENABLED') )
				{	$editLink = WEB_DOC_ROOT.'admin/users/edit_role/rid'.$pid; }
				else
				{	$editLink = WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=edit_role&role_id='.$pid; }
					
				if( $config->GetConfig('SEO_LINKS_ENABLED') )
				{	$deleteLink = WEB_DOC_ROOT.'admin/users/delete_role/rid'.$pid; }
				else
				{	$deleteLink = WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=delete_role&role_id='.$pid; }
				
				if( $i == 0 )
				{	$class = 'lite'; $i++; }
				else
				{	$class = 'dark'; $i--; }
				
				$tbl2->addRow( $class );
				$tbl2->addCell('<a href="'.$editLink.'">'.$roleName."</a>");
				
				if( intval($pid) <= 2 )
				{
					$tbl2->addCell('<a href="'.$editLink.'">[Edit]</a>', null, 'data', array('align'=>'center') ); 
				}
				else
				{
					$tbl2->addCell('<a href="'.$editLink.'">[Edit]</a> | <a href="'.$deleteLink.'">[Delete]</a>', null, 'data', array('align'=>'center') ); 
				}
			}
			
			if( $class == 'dark' )
			{	$class = 'lite'; }
			elseif( $class == 'lite' )
			{	$class = 'dark'; }
			
			//$tbl2->addRow( $class );
			//$tbl2->addCell('<a href="'.$addLink.'">Add a New User Role.</a>', null, 'data', array('colspan'=>2, 'align'=>'center') );
		}
		
		$keys = array(
			'USER_TABLE' => $tbl->render(),
			'ROLE_TABLE' => ($_SESSION['user']->HasPermission('pmx_manage') == true) ? $tbl2->render() : 'You are not allowed to manage User-Roles.'
		);
		
		$page_data = $tpl->compileKeys('users_list.html', $keys);
	break;
	
	case 'add':
		$activeRoles = $permissions->getRolesList();
		
		$form = new Form("UsersAddNew", 400, $_SERVER['REQUEST_URI'] );
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
		
		if($_SESSION['user']->HasPermission('pmx_manage') == true)
		{
			$form->addElement( new Element_Select('User Role: ', 'user_role', $activeRoles));
		}
		
		$form->addElement( new Element_Button() );
			
		if( !empty($_POST) && Form::isValid('UsersAddNew') )
		{
			$uName='';
			$uPass='';
			$uEmail='';
			$uRole=1;
			$er=0;
			
			//Start the PHP password hasher.
			$phpass = new PasswordHash( 14 , FALSE );
			
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
			
			if( isset($_POST['user_role']) && !empty($_POST['user_role']) )
			{	
				if( isset($activeRoles[ $_POST['user_role'] ]) )
				{
					$uRole = $_POST['user_role'];
				}
				else
				{	$uRole = 1; $er++; } //default role just in case.
			}
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
					'pmx_role'	=> $uRole,
					'time_joined' => time(),
					'last_active' => time()
				);
				
				$db->AutoExecute('users', $record, 'INSERT');
				
					
				if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
				{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
				else
				{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
			}
		}	
		else
		{
			$keys = array(
				'ADD_USER_FORM' => $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) )
			);
			
			$page_data = $tpl->compileKeys('users_add.html', $keys );
		}
	break;
	
	case 'edit':
	
		if( isset($_GET['user_id']) )
		{	$user_id = $gpc->get('GET', 'user_id', 'i'); }
		else
		{
			$user_id = $gpc->get('SEO', 'user_id', 'i'); 
		}
		
		if( $user_id <= 0)
		{
			if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
			{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
			else
			{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
		}
		
		$activeRoles = $permissions->getRolesList();
		
		$rs = $db->Execute( 'SELECT name, pass, email, time_joined, last_active, pmx_role FROM users WHERE uid='.$user_id );
		
		$form = new Form("UsersEdit", 400, $_SERVER['REQUEST_URI'] );
		$form->configure(array(
    		"view" => new View_SideBySide(110)
		));
		$form->setValues(array(
			"user_name" 	=> $rs->fields['name'],
			"user_pass" 	=> '',
			'user_email' 	=> $rs->fields['email'],
			'user_role'		=> $rs->fields['pmx_role']
		));
		
		//$form->addElement( new Element_HTMLExternal(''));
		
		$form->addElement( new Element_Textbox('User Name: ', 'user_name', array(
			"validation" => new Validation_RegExp("/[a-z0-9]/i", "Error: The User Name can only contain letters or numbers."),
		)));
		$form->addElement( new Element_Textbox('Email: ', 'user_email', array(
			'validation' => new Validation_Email,
		)));
		$form->addElement( new Element_Password('Password: ', 'user_password', array(
			"validation" => new Validation_RegExp("/(.{8,32}|.{0})/i", "Error: The password must be between 8 and 32 characters long."),
		)));
		
		if($_SESSION['user']->HasPermission('pmx_manage') == true)
		{
			$form->addElement( new Element_Select('User Role: ', 'user_role', $activeRoles));
		}
		
		$form->addElement( new Element_Button() );
			
		if( !empty($_POST) && Form::isValid('UsersEdit') )
		{
			$uName='';
			$uPass='';
			$uEmail='';
			$uRole=1;
			$er=0;
			
			if( isset($_POST['user_name']) && !empty($_POST['user_name']) )
			{	$uName = $_POST['user_name']; }
			else
			{	$uName = $rs->fields['name']; }
			
			if( isset($_POST['user_password']) && !empty($_POST['user_password']) )
			{	$uPass =  $phpass->HashPassword( $_POST['user_password'] ); }
			else
			{	$uPass =  $rs->fields['pass']; }
			
			if( isset($_POST['user_email']) && !empty($_POST['user_email']) )
			{	$uEmail = $_POST['user_email']; }
			else
			{	$uEmail = $rs->fields['email']; }
			
			if( isset($_POST['user_role']) && !empty($_POST['user_role']) )
			{	
				if( isset($activeRoles[ $_POST['user_role'] ]) )
				{
					$uRole = $_POST['user_role'];
				}
				else
				{	$uRole = $rs->fields['pmx_role']; } //update to the original value, just in case.
			}
			
			if( $er != 0)
			{	trigger_error('There was an error attempting to save the form.<br />Please retry again.', E_USER_ERROR); }
			else
			{
				$record = array(
					'name'  	=> $uName,
					'pass' 		=> $uPass,
					'email' 	=> $uEmail,
					'pmx_role'	=> $uRole,
				);
				
				$db->AutoExecute('users', $record, 'UPDATE', 'uid='.$user_id);
					
				if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
				{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
				else
				{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
			}
		}	
		else
		{
			$keys = array(
				'EDIT_USER_FORM' => $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) ),
				'USER_JOINED_TIME' => date('g:ia - F j, Y', $rs->fields['time_joined'] ),  //TODO: Replace standard date functions with template->printTime().
				'USER_LAST_ACTIVE' => date('g:ia - F j, Y', $rs->fields['last_active'] )
			);
			
			$page_data = $tpl->compileKeys('users_edit.html', $keys );
		}
	break;
	
	case 'delete':
		if( isset($_GET['user_id']) )
		{	$user_id = $gpc->get('GET', 'user_id', 'i'); }
		else
		{
			$user_id = $gpc->get('SEO', 'user_id', 'i'); 
		}
		
		$rs = $db->Execute( 'SELECT name FROM users WHERE uid='.$user_id );
		
		if( $user_id > 2 && $rs->RecordCount() > 0 )//Can't delete our Admin or Guest users...
		{
			$form = new Form("UsersDelete", 400, $_SERVER['REQUEST_URI'] );
			
			$form->addElement( new Element_YesNo('Are you sure?', 'confirm'));
			$form->addElement( new Element_Button());
			
				
			if( !empty($_POST) && Form::isValid('UsersDelete') )
			{
				if( isset($_POST['confirm']) && $_POST['confirm'] == 1 )
				{
					$sql = 'DELETE FROM `users` WHERE `uid`='.$user_id;
						
					//$db->debug = true;
					$db->Execute( $sql );
				}
				
				if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
				{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
				else
				{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
			}	
			else
			{
				$keys = array(
					'CONFIRM_FORM' => $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) ),
					'USER_NAME' => $rs->fields['name']
				);
				
				$page_data = $tpl->compileKeys('users_delete.html', $keys );
			}
			
		}
		else
		{
			if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
			{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
			else
			{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
		}
		
	break;
	
	case 'edit_role':
		
		if($_SESSION['user']->HasPermission('pmx_manage') == false)
		{
			if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
			{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
			else
			{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
		}
		
		if( isset($_GET['role_id']) )
		{	$role_id = $gpc->get('GET', 'role_id', 'i'); }
		else
		{
			$role_id = $gpc->get('SEO', 'role_id', 'i'); 
		}
		
		$rs = $db->Execute( 'SELECT pmx, name FROM pmx_roles WHERE pid='.$role_id );
		
		if( $role_id > 0 && $rs->RecordCount() > 0 )//Can't Edit our Admin or Guest roles...
		{
			$defaultPMX = array();
			foreach( explode(',', $rs->fields['pmx']) as $pr )
			{
				$pr = trim($pr);
				if( !empty($pr) )
				{
					$pmx = explode(':', $pr);
					$defaultPMX[ 'pmx['.$pmx[0].']' ] = $pmx[1];
				}
			}
			
			$form = new Form("URoleEdit", 400, $_SERVER['REQUEST_URI'] );
			$form->setValues( array_merge( array("role_name" => $rs->fields['name']), $defaultPMX ));
			
			$form->addElement( new Element_Textbox('User-Role Name:', 'role_name', array(
				'description' => 'The name used to identify the set of permissions defined in the list below.',
				'required'	=> 1
			)));
			
			//Gets all registered permissions and listes them with their descriptions.
			$p = getRegisteredPermissions();
			foreach($p as $n => $v) {	
				$form->addElement( new Element_YesNo($v.':', 'pmx['.$n.']')); 
			}
			
			$form->addElement( new Element_Button());
			
			if( !empty($_POST) && Form::isValid('URoleEdit') )
			{
				$er=0;
				$rName = '';
				$rPMX  = '';
				
				if( isset($_POST['role_name']) && !empty($_POST['role_name']) )
				{
					$rName = $_POST['role_name'];
				}
				else { $er++; }
				
				if( isset($_POST['pmx']) && !empty($_POST['pmx']) && is_array($_POST['pmx']) )
				{
					$rPMX = '';
					foreach( $_POST['pmx'] as $n => $v )
					{
						$rPMX .= $n.':'.$v.',';
					}
				}
				else { $er++; }
				
				
				//No errors found, take care of the database queries.
				if( $er == 0 )
				{
					$record = array(
						'name' => $rName,
						'pmx' => $rPMX
					);
					
					$db->AutoExecute('pmx_roles', $record, 'UPDATE', 'pid='.$role_id);
					
					$cache->clearCache( 'pmxRoles' );
				}
				
				
				if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
				{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
				else
				{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
			}	
			else
				{
				$keys = array(
					'PERMISSION_LIST_FORM' => $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) )
				);
					
				$page_data = $tpl->compileKeys('user_roles_edit.html', $keys );
			}
		}
		else
		{
			if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
			{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
			else
			{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }	
		}
	break;
	
	case 'add_role':
		if($_SESSION['user']->HasPermission('pmx_manage') == false)
		{
			if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
			{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
			else
			{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
		}
		
		$form = new Form("URoleAdd", 400, $_SERVER['REQUEST_URI'] );
		
		$form->addElement( new Element_Textbox('User-Role Name:', 'role_name', array(
			'description' => 'The name used to identify the set of permissions defined in the list below.',
			'required'	=> 1
		)));
		
		//Gets all registered permissions and listes them with their descriptions.
		$p = $permissions->getRegisteredPermissions();
		foreach($p as $n => $v) {	
			$form->addElement( new Element_YesNo($v.':', 'pmx['.$n.']')); 
		}
		
		$form->addElement( new Element_Button());
		
		if( !empty($_POST) && Form::isValid('URoleAdd') )
		{
			$er=0;
			$rName = '';
			$rPMX  = '';
			
			if( isset($_POST['role_name']) && !empty($_POST['role_name']) )
			{
				$rName = $_POST['role_name'];
			}
			else { $er++; }
			
			if( isset($_POST['pmx']) && !empty($_POST['pmx']) && is_array($_POST['pmx']) )
			{
				$rPMX = '';
				foreach( $_POST['pmx'] as $n => $v )
				{
					$rPMX .= $n.':'.$v.',';
				}
			}
			else { $er++; }
			
			
			//No errors found, take care of the database queries.
			if( $er == 0 )
			{
				$record = array(
					'name' => $rName,
					'pmx' => $rPMX
				);
				
				$db->AutoExecute('pmx_roles', $record, 'INSERT');
				
				$cache->clearCache( 'pmxRoles' );
			}
			
			
			if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
			{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
			else
			{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
		}	
		else
		{
			$keys = array(
				'PERMISSION_LIST_FORM' => $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) )
			);
			
			$page_data = $tpl->compileKeys('user_roles_add.html', $keys );
		}
		
	break;
	
	case 'delete_role':
		if($_SESSION['user']->HasPermission('pmx_manage') == false)
		{
			if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
			{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
			else
			{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
		}
		
		if( isset($_GET['role_id']) )
		{	$role_id = $gpc->get('GET', 'role_id', 'i'); }
		else
		{
			$role_id = $gpc->get('SEO', 'role_id', 'i'); 
		}
		
		$rs = $db->Execute( 'SELECT name FROM pmx_roles WHERE pid='.$role_id );
		
		if( $role_id > 2 && $rs->RecordCount() > 0 )//Can't delete our Admin or Guest roles...
		{
			$form = new Form("URoleDelete", 400, $_SERVER['REQUEST_URI'] );
			
			$form->addElement( new Element_YesNo('Are you sure?', 'confirm'));
			$form->addElement( new Element_Button());
				
			if( !empty($_POST) && Form::isValid('URoleDelete') )
			{
				if( isset($_POST['confirm']) && $_POST['confirm'] == 1 )
				{
					$sql = 'DELETE FROM `pmx_roles` WHERE `pid`='.$role_id;
						
					//$db->debug = true;
					$db->Execute( $sql );
					
					$cache->clearCache( 'pmxRoles' );
				}
				
				if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
				{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
				else
				{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
			}	
			else
			{
				$keys = array(
					'CONFIRM_FORM' => $tpl->parseExternalHTML( str_replace('><', ">\n<", $form->render(true)) ),
					'ROLE_NAME' => $rs->fields['name']
				);
				
				$page_data = $tpl->compileKeys('user_roles_delete.html', $keys );
			}
			
		}
		else
		{
			if( $config->GetConfig('SEO_LINKS_ENABLED') == true )
			{	header('Location: '.WEB_DOC_ROOT.'admin/users/list'); }
			else
			{	header('Location: '.WEB_DOC_ROOT.'admin.php?admin_panel=users&users_sec=list'); }
		}
	break;
}

?>