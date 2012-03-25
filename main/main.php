<?php
/**
* Critical System Runtime Components.
*
* This file is used like a common file, all pages that generate content
*  will use this file.  The global objects used by the system are all 
*  started here.
*
* LICENSE: GNU GPLv3
*
* @package         StrongWeb-CMS
* @file            /main/main.php
* @author          Caleb Brumfield
* @contributors    n/a
* @license         GNU GPLv3 
* @since           File available since version 0.1a
* @source          https://github.com/Cheshire121/StrongWeb-CMS
*/

if( !defined('IN_SWCMS') )
{	exit(); }


//The User class stores data on per-session bassis.  
//This object is stored in the session and is used primarily for permissions and per-user configurations added to the site.
class user
{
    public $uid         = 1;
    public $name        = 'AnonUser';
    public $logged_in   = false;
	public $validated   = false;
    public $timezone    = '';
	public $last_active = 0;
    
    private $role_id         = 1;
    private $permissions     = array();
    private $pmx_last_update = 0;
    
    //Load the user class based on the $id given.
    public function user( $id )
    {
        global $db;
        
        //Get the users' record from the database.
        $rs = $db->Execute( 'SELECT name, pmx_role, timezone, last_active FROM `users` WHERE `uid` = '.$id );
        
        //load that data into the member variables.
        $this->uid         = $id;
        $this->name        = $rs->fields['name'];
        $this->role_id     = $rs->fields['pmx_role'];
		$this->last_active = $rs->fields['last_active'];
		$this->validated   = false; //Always false unless validation is done or a regular log-in is used.
        
        $this->timezone = ( !empty($rs->fields['timezone']) ) ? $rs->fields['timezone'] : date('T');
        
        //Set up the users permissions bassed on their role..
        $this->GetUserPMX( $rs->fields['pmx_role'] );
    }
    
    private function GetUserPMX( $setId )
    {
        $permissions = new Permissions();
        
        foreach( $permissions->getRolePermissions($setId)  as $k => $v )
        {
            $this->permissions[ strtolower($k) ] = ($v == 1) ? true : false;
        }
    
        //record the timestamp when permissions were last loaded from the database.
        $this->pmx_last_update = time();
    }
    
    //This function checks for the permission name and returns true if the user was allowed and false on any other account.
    public function hasPermission( $name )
    {
        //Idealy an automatic update would be best, however we have to think about load here.
        //Check the last update time to make sure its within our timeframe.
        if( $this->pmx_last_update < (time() - 60*3) ) //within 3 minutes
        {
            $this->GetUserPMX( $this->role_id );
        }
        
        $name = strtolower($name);
        
        if( isset($this->permissions[ $name ]) )
        {    return $this->permissions[ $name ]; }
        else
        {    return false; }
    }
	
	public function checkLastActive()
	{
		global $db;
		
		//Test to see if the user was active recently.
		//If the last active time is less that the current time, minus 1.2 hours, then we need to reset the active time.
		if( $this->last_active < (time() - (int)(60*60*24*1.2)) ) //User was last active in the last hour.
        {
			$time = time();
			
			$db->AutoExecute('users', array('last_active'=>$time), 'UPDATE', 'uid='.$this->uid);
			
			$this->last_active = $time;
		}
	}
}

//Function: login  used to process a users log-in information.
function login($username, $pass, $remember)
{
    global $db, $config;
	
	$phpass = new PasswordHash( 14 , FALSE );
    $cookie_name = $config->GetConfig('SITE_COOKIE_NAME');
	$time_now = time();
    
    if( empty($username) )
    {    return 0x8; }

    if( empty($pass) )
    {    return 0x10; }
    
    if($remember == true) //Should add a configuration option to dissable auto-logins
    {
        $login_cookie_time = ($time_now+60*60*24*365); 
        $pcode = base64_encode( $time_now.'_'.$username );
    }
    else
    {    $login_cookie_time = false; }
    
    $username = $db->qstr( strtolower($username) );
    //$pass = $phpass->HashPassword($pass);

    $rs = $db->Execute("SELECT uid, pass FROM users WHERE LOWER(`name`) = " . $username);
    
    if( $rs->RecordCount() > 0 )
    {
        $valid = $phpass->CheckPassword( $pass, $rs->fields['pass'] );
        
        if( $valid )
        {
            $_SESSION['user'] = new User($rs->fields['uid']);
            $_SESSION['user']->logged_in = true;
			$_SESSION['user']->validated = true;
    
            if($login_cookie_time != false)
            {    
                $db->AutoExecute('users', array('session_key'=>$pcode, 'last_active'=>$time_now), 'UPDATE', 'uid='.$rs->fields['uid']);
                set_cookie($cookie_name, $pcode, $login_cookie_time); 
            }
            
            unset($cookie_name);
                
            return 1;
        }
        else
        {    return 2; }
    }
    else
    {    return 3; }
}

//Checks the user-login data without creating cookies or sessions.
//Used as a security, after a cookie-login, or to valid data a user. 
//The username passed can't be different from the username saved in the session.
function valid_login($username, $pass)
{
    global $db, $config;
	
	$phpass = new PasswordHash( 14 , FALSE );
	$time_now = time();
    
    if( empty($username) )
    {    return 0x8; }
	elseif( strtolower($username) !== strtolower($_SESSION['user']->name ) )
	{	 return 0x9; }

    if( empty($pass) )
    {    return 0x10; }
    
	//We can't validate a login if the user doesn't have a session.
	if( !isset($_SESSION['user']) || get_class($_SESSION['user']) !== 'user' )
	{	return false; }
	
    $username = $db->qstr( strtolower($username) );

    $rs = $db->Execute("SELECT uid, pass FROM users WHERE LOWER(`name`) = " . $username);
    
    if( $rs->RecordCount() > 0 )
    {
        $valid = $phpass->CheckPassword( $pass, $rs->fields['pass'] );
        
        if( $valid )
        {
           $_SESSION['user']->validated = true;
		   return true;
        }
        else
        {    
			$_SESSION['user']->validated = false;
			return false; 
		}
    }
    else
    {
		$_SESSION['user']->validated = false;
		return false; 
	}
}

//Processes an auto-login session key.
function auto_login( $sessKey )
{
    global $db, $config;
    
    $code = base64_decode( $sessKey );
    $un = explode('_', $code);
	$time_now = time();
    
    $login_cookie_time = ($time_now+60*60*24*365); 
    $pcode = base64_encode( $time_now.'_'.$un[1] );
    $cookie_name = $config->GetConfig('SITE_COOKIE_NAME');
    
    $rs = $db->Execute( 'SELECT uid, last_active FROM users  WHERE session_key='.$db->qstr($sessKey).' AND LOWER(name)='.$db->qstr(strtolower($un[1])) );
    
    if( $rs->RecordCount() > 0 )
    {
     	if( $rs->fields['last_active'] != $un[0] )
		{	return false; }
        
        $_SESSION['user'] = new user( $rs->fields['uid'] );
        $_SESSION['user']->logged_in = true;
		$_SESSION['user']->validated = false;
        
        $db->AutoExecute('users', array('session_key'=>$pcode, 'last_active'=>$time_now), 'UPDATE', 'uid='.$rs->fields['uid']);
        set_cookie($cookie_name, $pcode, $login_cookie_time);
		
		return true; 
    }
    else
    {
        return false;
    }
}

//helper function to set cookies to this domain.  Using time=false to delete.
function set_cookie($name, $data, $time=false, $path='/', $domain='', $secure=false)
{
    global $config;
	
    //fliter domain and make it clean.
    $domain = ($domain == '') ? $config->GetConfig('SITE_URL') : $domain;
    $domain = preg_replace('#(https?://)?(.*?)(:[0-9]+)?/?#', '$2', $domain);
	
	$secure = ( empty($_SERVER["HTTPS"]) || strtolower($_SERVER["HTTPS"]) == 'off' ) ? false : (!empty($_SERVER['HTTPS'])) ? true : false;
    
    $time = ($time !== false) ? $time : (time() - 10000);
    
	if( version_compare(PHP_VERSION, '5.2.0') >= 0 )
	{
		//This call uses the httpOnly option, to attempt to prevent XSS.
		setcookie( $name, $data, $time, $path, $domain, $secure, true );
	}
	else
	{	setcookie( $name, $data, $time, $path, $domain, $secure ); }
}


/* Class: Permissions
*    Intended to provide a means of handling permissions in the system.
*/
class Permissions
{
    public function Permissions()
    {}
    
    /* Function: registerPermissions
    *    Description:     Bulk version of registerPermission(), allows registering permissions via associative arrays.
    *    Arguments:      $pmx - associative array of permission name => description.
    *    Returns:        void
    */
    public function registerPermissions( $pmx )
    {
        if( is_array($pmx) )
        {
            foreach($pmx as $n => $v)
            {
                registerPermission( $n, $v );
            }
        }
    }    
    
    /* Function: registerPermission
    *    Description:     Adds a new permission to the system. Checking against existing permissions before registering a new one.
    *    Arguments:      $name - the logical name, containing no spaces, of the permission.
    *                    $desc - a description associated with the logial permission being added.
    *    Returns:        false if permission name already exists.
    */
    public function registerPermission( $name, $desc )
    {
        global $config;
        
        $name = strtolower( trim($name) );
        $desc = trim( $desc );
        
        $sp = trim( $config->GetConfig('site_permissions') );
        if( !empty($sp) )
        {
            $pmx = @unserialize( base64_decode( $sp ) );
        }
        else
        {    $pmx = array(); }    
    
        if( is_array($pmx) && !isset($pmx[ $name ]) )
        {
            $pmx[ $name ] = $desc;
            
            $pmx = base64_encode( serialize( $pmx ) );
        
            $config->SetConfig('site_permissions', $pmx);
        
            //Update the original admin role so that the admins automatically have this permission when its registered.
            $this->setRolePermissions( 2, array_merge(array($name => 1), getRolePermissions( 2 )));
        }
        else
        {    return false; }
    }
    
    /* Function: getRegisterPermissions
    *    Description:     Returns an array of named descriptions.
    *    Arguments:      none
    *    Returns:        array - associative name=>description 
    */
    public function getRegisteredPermissions()
    {
        global $config;
        
        $sp = trim( $config->GetConfig('site_permissions') );
        if( !empty($sp) )
        {
            $pmx_arr = @unserialize( base64_decode( $sp ) );
        }
        else
        {    $pmx_arr = array(); }
        
        return $pmx_arr;
    }
    
    /* Function: getRegisteredRoles
    *    Description:     Returns a list of roles associated to their stored RIDs.
    *    Arguments:      none
    *    Returns:        array
    */
    public function getRolesList()
    {
        global $db, $cache;
        
        if( $cache->checkCache('pmxRoles') )
        {
            $roles_arr = $cache->getCache( 'pmxRoles' );
        }
        else
        {
            $roles_arr = array();
            
            $rs = $db->Execute( 'SELECT pid, name FROM pmx_roles' );
            foreach( $rs as $role )
            {
                $roles_arr[ $role['pid'] ] = $role['name'];
                //$roles_arr[ $role['pid'] ]['pid'] = $role['pid'];
                //$roles_arr[ $role['pid'] ]['name'] = $role['name'];
            }
            
            $cache->setCache( 'pmxRoles', $roles_arr );
        }
        
        return $roles_arr;
    }    
    
    public function getRolePermissions( $setId )
    {
        global $db;
        
        $setId = intval( $setId );
        
        //Get the permissions set from the database and save them in an array.
        $rs = $db->Execute('SELECT pmx  FROM `pmx_roles` WHERE `pid` = '.$setId);
        
        $role_pmx=array();
        if( $rs->RecordCount() > 0 )
        {
            $p = explode(',', $rs->fields['pmx'] );
			foreach($p as $pmx )
            {
                if( !empty($pmx) )
                {    
                    $part = explode(':', $pmx);
                    $part[0] = trim( $part[0] );
                    $part[1] = trim( $part[1] );
                    
                    $role_pmx[ strtolower($part[0]) ] = ($part[1] == 1) ? 1 : 0;
                }
            }
        }
        
        return $role_pmx;
        
    }
    
    public function setRolePermissions($id, $pmx)
    {
        if( is_array($pmx) && is_int($id) )
        {
            $role_str = '';
            foreach($pmx as $k => $v)
            {
                if( !empty($k) && !empty($v) )
                {    $role_str .= $k.':'.$v.','; }
            }
            
            $record=array(
                'pmx' => $role_str
            );
            
            $db->AutoExecute('pmx_roles', $record, 'UPDATE', '`pid`='.$id);
        }
    }
}
    
//Configuration utility used to get, cache, and update the sites running configuration(s).
class ConfigCache
{
    private $config = array();
    private $configFor = array();
    
    private $db;
    private $cache;
    
    //class constructor.
    public function ConfigCache() 
    {
        global $db_type, $db_host, $db_user, $db_pass, $db_name;
        
        //We set up a second connection here to avoid runtime-conflicts in loop structurs.
        $this->db = ADONewConnection($db_type); 
        $this->db->Connect($db_host,$db_user,$db_pass,$db_name);
        $this->db->SetFetchMode(ADODB_FETCH_ASSOC); 
        
        $this->cache = new Cache();
        
        //check the system for cached runtime variables
        if( $this->cache->checkCache('sys808') )
        {
            $this->config = $this->cache->getCache('sys808');
        }
        else
        {
            //Looad the "system" configuration from the database.
            $rs = $this->db->Execute('SELECT `cfg_name`, `cfg_value` FROM `config` WHERE `cfg_for`=\'system\'');
            
            foreach( $rs as $k => $v )
            {
                $this->config[ strtolower($v['cfg_name']) ] = $v['cfg_value'] ;    
            }
            
            $this->cache->setCache('sys808', $this->config);
        }
        
        //Start php session.
        ini_set('session.cookie_lifetime', '0'); //This will default to 0, but should be configured by an admin.
        session_name( 'localhost_session' ); //This will need to be a randomly generated and configurable value.
        session_start();
    }
    
    //Manually set/update a system configuration.
    public function SetConfig( $name, $value )
    {
        $name = strtolower( $name );
        if( array_key_exists( $name, $this->config ) )
        {    
            $old_value = $this->config[ $name ];
            
            if( $old_value != $value )
            {
                $this->config[ $name ] = $value; 
                $this->UpdateConfig( $name, $value );
            }
        }
        else
        {    return false; }
        
        return true;
    }
    
    //set configuration for an addon.
    public function SetConfigFor( $name, $value, $cfg_for )
    {
        $name = strtolower($name);
         
        if( array_keys($this->config, $name ) )
        {    
            $this->configFor[strtoupper($cfg_for)][ $name ] = $value; 
            $this->UpdateConfig( $name, $value, $cfg_for );
        }
        else
        {    return false; }
        
        return true;
    }
    
    public function GetConfig( $name )
    {
        if( isset($this->config[ strtolower($name) ]) )
        {
            return $this->config[ strtolower($name) ];
        }
        else
        {    return false; }
    }
    
    public function GetConfigFor( $cfg_for )
    {
        if( $this->cache->checkCache( $cfg_for.'_cfg' ) )
        {
            $this->configFor[ strtoupper($cfg_for) ] = $this->cache->getCache($cfg_for.'_cfg');
        }
        else
        {
            $rs = $this->db->Execute('SELECT `cfg_name`, `cfg_value` FROM `config` WHERE `cfg_for`=\''.$this->db->qstr($cfg_for).'\'');
            
            foreach( $rs as $k => $v )
            {
                $this->configFor[ strtoupper($cfg_for) ][ strtolower($v['cfg_name']) ] = $v['cfg_value'];    
            }
            
            $this->cache->setCache( $cfg_for.'_cfg', $this->configFor[strtoupper($cfg_for)] );
            
        }
        
        return $this->configFor[ strtoupper($cfg_for) ];
    }
    
    public function RegisterConfigFor( $config_arr, $cfg_for )
    {
        if( is_array($config_arr) )
        {
            $cfg_sql = array();
            
            foreach($config_arr as $name => $value)
            {
                $name = strtolower($name);
                
                if( !isset($this->configFor[ strtoupper($cfg_for) ][ $name ]) )
                {
                    $this->configFor[ strtoupper($cfg_for) ][ $name ] = $value;    
                    $cfg_sql = array( 'cfg_for'=>$cfg_for, 'cfg_name'=>$name, 'cfg_value'=>$value );
                    
                    $this->db->AutoExecute('config', $cfg_sql, 'INSERT');
                }
            }
        }
        else
        {    return false; }
    }
    
    private function UpdateConfig( $name, $value, $cfg_for='system' )
    {
        $record = array(
            'cfg_value'    => $value
        );
        
        $this->db->AutoExecute('config', $record, 'UPDATE', '`cfg_name`=\''.$name.'\' AND `cfg_for`=\''.$cfg_for.'\'' );
        
        if( $cfg_for == 'system' )
        {    $this->cache->unsetCacheFile('sys808'); }
    }
    
}


//Class aimed at normalizing data given from the client to the server, via the URL or POST/COOKIEs.
class GetPostCookie
{
    private $SEO_register  = array();
    private $PageVariables = array();
    
    public function GetPostCookie()
    {
        // Is magic quotes on? 
        if (get_magic_quotes_gpc()) 
        { // Yes? Strip the added slashes 
            $_REQUEST     = $this->stripSlashes( $_REQUEST ); 
            $_GET         = $this->stripSlashes( $_GET ); 
            $_POST         = $this->stripSlashes( $_POST ); 
            $_COOKIE     = $this->stripSlashes( $_COOKIE ); 
        }
        
        $this->RegisterSEOVariable('page_id', 'pg([0-9]+)');
        $this->RegisterSEOVariable('in_admin', '/(admin)');
        
        $this->parseSEOLink();
    }
    
    /*
        Function: $gpc->get()
        Arguments:  $type - Used to specify GET, POST, or COOKIE, data input methods.
                    $name - Used to specify the name of the desired value.
                    $cleanTo - Used to sanitize and convert an input into a type.
        Description:   Method used to selectively get and clean/format data input from the client.
    */
    public function get($type, $name, $cleanTo=NULL)
    {    
        global $db;
        
        switch( strtoupper($type) )
        {
            case "GET":
                $data = $_GET[ $name ];
            break;
            
            case "POST":
                $data = $_POST[ $name ];
            break;
            
            case "COOKIE":
                $data = $_COOKIE[ $name ];
            break;
            
            case "SEO":
                if( isset( $this->PageVariables[$name]) )
                {    
                    $data = $this->PageVariables[ $name ];
                }
                else
                {
                    $data = NULL;    
                }
            break;
            
            default:
                return false;
            break;
        }
        
        switch( strtolower($cleanTo) )
        {
            case 'int':
            case 'i':
                if( empty($data) )
                {    $data = 0; }
                else
                {    $data = intval( $data ); }
            break;
            
            case 'bool':
            case 'boolean':
                if( $data === true )
                {
                    $data = true;    
                }
                else
                {
                    $data = false;    
                }
            break;
            case 'qstr':
                $data = $db->qstr( $data );
            break;
        }
        
        return $data;
    }
    
    
    /*
        Function: RegisterSEO Variable()
        Arguments:  $varName - logical name used in an array index to denote data extracted using the $varReg expression.
                    $varReg  - a regular expression used to extract a single variable from a URI/URL.
        Description:  Function used to add regular expressions to the SEO variable list.  The list is later used to extract 
                     data from the url and uri of the current requested page.
                     Will return false if $varName already exists.
    */
    public function RegisterSEOVariable( $varName, $varReg=NULL)
    {
        if( is_array($varName))
        {
            foreach( $varName as $k => $v )
            {
                $this->RegisterSEOVariable( $k, $v );
            }
            
            $this->reparseSEORegister();
        }
        else
        {
            if( !isset($this->SEO_register[ $varName ]) )
            {
                $this->SEO_register[ $varName ] = $varReg ;
            }
            else { return false; }
        }
    }
    
    /*
        Function:  reparse SEO Register
        Arguments: none
        Description:  meta-function used to re-parse the seo register afer initial page-loads.
        returns:   void
    */
    public function reparseSEORegister()
    {
        $this->parseSEOLink();
    }
    
    public function dumpSEOvars()
    {
        print_r( $this->SEO_register );
        print_r( $this->PageVariables );    
    }
    
    /*
        Function: parseSEOLink
        Arguments: none
        Description:   Function used to extract variables from the current page's URI/URL using regular expression pairs.
                    
        Returns:  mixed array of named variables.
    */
    private function parseSEOLink()
    {
        foreach( $this->SEO_register as $name => $reg )
        {
            preg_match('#'. str_replace('#', '\#', $reg) .'#i', $_SERVER['REQUEST_URI'], $m);
            
            if( isset($m[1]) && !empty($m[1]) )
            {
                $this->PageVariables[ $name ] = $m[1];
            }
        }
        
        return $this->PageVariables;
    }
    
    private function stripSlashes( $arr )
    {
        if( is_array($arr) )
        {
            $newArr=array();
            
            
			foreach($arr as $k => $v )
            {
                $newArr[ $k ] = $this->stripSlashes( $v );
            }
            
            return $newArr;
        }
        else
        {
            return stripslashes($arr);
        }
    }
}

function generateServerURL()
{
    $server_name = (!empty($_SERVER['HTTP_HOST'])) ? strtolower($_SERVER['HTTP_HOST']) : ((!empty($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : getenv('SERVER_NAME'));
    $server_port = (!empty($_SERVER['SERVER_PORT'])) ? (int) $_SERVER['SERVER_PORT'] : (int) getenv('SERVER_PORT');
    $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 1 : 0;
    
    $script_name = (!empty($_SERVER['PHP_SELF'])) ? $_SERVER['PHP_SELF'] : getenv('PHP_SELF');
    if (!$script_name){
        $script_name = (!empty($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : getenv('REQUEST_URI');
    }

    $url = (($secure) ? 'https://' : 'http://') . $server_name;
    
    if ($server_port && (($secure && $server_port <> 443) || (!$secure && $server_port <> 80)))    {
        if (strpos($server_name, ':') === false) {
            $url .= ':' . $server_port;
        }
    }
    
    return $url;
}

/** 
* @Name         bytes2shorthand
* @Arg1         int - Raw Bytes to be converted.
* @Arg2         int - (Optional) Number of decimal places to display. Default is 2.
* @Returns      string - Argument 1 as KB, MB, GB, or TB shorthand with decimal places, specified by argument 2.
* @Description  
*    Converts a data-size in bytes to a shorthand notation.  
*  Example usage:
*    byte2shorthand(6546516,4); returns 6.2432 GB
*/
function bytes2shorthand($bytesIn, $roundTo=2)
{
    $size = '0 Bytes';
    if($bytesIn >= 1099511627776)
    {    $size = round(($bytesIn / 1099511627776 ), $rt).' TB'; }
	elseif($bytesIn >= 1073741824)
    {    $size = round(($bytesIn / 1073741824 ), $rt).' GB'; }
    elseif($bytesIn >= 1048576)
    {    $size = round(($bytesIn / 1048576 ), $rt).' MB'; }
    elseif($bytesIn >= 1024)
    {    $size = round( ($bytesIn / 1024), $rt).' KB'; }
    else
    {    $size = ($bytesIn).' Bytes'; }
    
    return $size;
    
}

/** 
* @Name         normalizeFilePath
* @Arg1         string  - A filesystem path, or URL/URI path to normalize.
* @Arg2         boolean - (Optional) Remove redundant directory separators. This can break URL wrappers. 
                           Default is false.
* @Arg3         string  - (Optional) Allows forcing what character(s) is(are) used as the Directory Separator. 
                           Default is DIRECTORY_SEPARATOR constant. 
* @Returns      string  - Argument 1 with matchting slash characters throughout the string or file-path.
* @Description  
*    Intended for file-paths, this function will convert all foward and back-slashes to the system-specified character.
*    Optionally you can remove redundant separators, and force replacement with your own character.
*  Example usage:
*    normalizeFilePath(ROOT_PATH . '\my\relative\file.ext'); 
*/
function normalizeFilePath($path, $patch_dir=false, $ds='')
{
    if( empty($ds) )
    {    $ds = DIRECTORY_SEPARATOR; }
    
    $path = str_replace(array('/', '\\'), array($ds, $ds), $path);
    
    if($patch_dir == true)
    {
        $path = str_replace('\\\\', '\\', $path);
        $path = str_replace('//', '/', $path);
    }
    
    return $path;
}

function normalizeURLPath($path, $patch_dir=true)
{
    if( preg_match('#([a-z]{3,})://#is', $path, $m) )
	{
		$wrapper = $m[1].'://'; 
		$path = str_replace($wrapper, '', $path);
	}
	else
	{	$wrapper = ''; }
	
	$path = str_replace(array('/', '\\'), array('/', '/'), $path);
	
    if($patch_dir == true)
    {
        for($i=0; $i < 3; $i++)
		{	$path = str_replace('//', '/', $path); }
    }

    return $wrapper . $path;
}

function checkDefine($name, $value)
{
    if(!defined($name))
    {
        define(strtoupper($name), $value, true);
        return true;
    }
    else
    {
        return false;
    }
}

function checkSoftwareUpdate()
{
    $conn = fsockopen("tcp://cbrumfield.x10.bz", 80, $errno, $errstr, 30);
    if (!$conn)
    {
        echo "$errstr ($errno)<br />\n";
    } 
    else 
    {
        $crlf = "\r\n";
        
        $out =  'GET /software/strongweb/index.php HTTP/1.1' . $crlf;
        $out .= 'Host: cbrumfield.x10.bz' . $crlf;
        $out .= 'User-Agent: Mozilla/5.0 Firefox/3.6.12' . $crlf;
        $out .= 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' . $crlf;
        $out .= 'Accept-Language: en-us,en;q=0.5' . $crlf;
        $out .= 'Accept-Encoding: deflate' . $crlf;
        $out .= 'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7' . $crlf;
        $out .= "Connection: Close". $crlf.$crlf;
        
           fwrite($conn, $out);
        
        $get='';
        while (!feof($conn)) 
        {
            $get .= fgets($conn, 128);
        }
        fclose($conn);
        
        $v = explode( '|', substr($get, strpos($get, "\r\n\r\n") + 4) );
        
        return $v;
    }
}

/*
*
*/
function downloadUpdatePackage($updateTo)
{
    //File used to save the update package to the local system.
    $fp = fopen(ROOT_PATH.'/temp/update.zip', 'w');
    
    //Connect to the update server and attempt to get the package.
    $conn = fsockopen("tcp://cbrumfield.x10.bz", 80, $errno, $errstr, 30);
    if (!$conn)
    {
        echo "$errstr ($errno)<br />\n";
    } 
    else 
    {
        $crlf = "\r\n";
        
        $req =  'GET /software/strongweb/'.$updateTo.'.zip HTTP/1.0' . $crlf;
        $req .= 'Host: cbrumfield.x10.bz' . $crlf;
        $req .= 'User-Agent: Mozilla/5.0 Firefox/3.6.12' . $crlf;
        $req .= 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' . $crlf;
        $req .= 'Accept-Language: en-us,en;q=0.5' . $crlf;
        $req .= 'Accept-Encoding: deflate' . $crlf;
        $req .= 'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7' . $crlf;
        $req .= "Connection: Close". $crlf.$crlf;
        
        //Send above request.
           fwrite($conn, $req);
        
        $out='';
        $headerendfound = false;
        $buffer='';
        
        //Listen for a responce and remove the HTTP header from the binary data.
        while (!feof($conn)) 
        {
            //Get responce data chunk for testing.
            $out = fgets($conn, 16384);
            
            if($headerendfound)
            {
                fwrite($fp, $out); //Save responce data to local file.
            }
            
            //Find the header in the responce chunk-data and remove it.
            if(!$headerendfound)
            {
                $buffer .= $out;
                $headerend = strpos($buffer, "\r\n\r\n");
                
                //The header was located. Remove only the header, and save the data.
                if($headerend !== false) 
                {
                    $headerendfound = true;
                    fwrite($fp, substr($buffer, $headerend+4));
                    $buffer = '';
                }
            }
        }
        
        fclose($fp);
                
    }
    
    fclose($conn);
}


function backtrace($provideObject=false)
{
    $trigger=false;
    $btrace='';
    foreach(@debug_backtrace($provideObject) as $row)
     {
        if( $row['function'] != 'SW_MsgHandler' && $row['function'] != 'backtrace')
        {
            $row['file'] = (empty($row['file'])) ? 'From callback, file unknown' : $row['file'];
            $row['line'] = (empty($row['line'])) ? 'Unknown' : $row['line'];
            
            $btrace.= "<span style=\"color:#990000;font-size:12px;\">File:</span> {$row['file']}<br />\n";
            $btrace.= "&nbsp;&nbsp;&nbsp;<span style=\"color:#990000;font-size:12px;\">Line:</span> {$row['line']}: \n";
                    
            $btrace.= "<span style=\"color:#990000;font-size:12px;\">Operation:</span> \n";
            if( isset($row['class']) && !empty($row['class']) )
            {    $btrace.= "{$row['class']}{$row['type']}{$row['function']}"; }
            else
            {    $btrace.= "{$row['function']}"; }
                
            $arg_str = '';
            foreach( $row['args'] as $arg )
            {
                $arg_str = $arg_str . "'".$arg."', ";
            }
            $arg_str = substr($arg_str, 0, -2);
            
            $btrace.= "(".@htmlentities($arg_str, ENT_QUOTES, "UTF-8").")<br /><br />\n";
        }
    }
    
    return $btrace;
    
}

function SW_MsgHandler($err_no, $err_msg, $err_file, $err_line) 
{
    $tpl = new template('main');
    
    $ERR_LOG_FILE = ROOT_PATH.'/temp/error.log';
	
	$timeStamp = '['.date("F j, Y, g:ia").']';
    
    if(@file_exists($ERR_LOG_FILE))
    {
        chmod($ERR_LOG_FILE, 0775);
        
        $fs = filesize($ERR_LOG_FILE);
        if($fs >= 110485760)
        {
            @unlink($ERR_LOG_FILE);
    
            $date = date('n/j/y - g:ia', time());
            @file_put_contents($ERR_LOG_FILE, "New Error log file started on: {$date}\n\r\n\r");
        }
    }
    else
    {
        $date = date('n/j/y - g:ia', time());
        @file_put_contents($ERR_LOG_FILE, "New Error log file started on: {$date}\n\r\n\r");
        chmod($ERR_LOG_FILE, 0775);
    }
    
	if (defined('DEBUG_OUTPUT_ON'))
	{	$debug_on = DEBUG_OUTPUT_ON; }
	else
	{	$debug_on = false; }
	
    switch($err_no)
    {
        case E_NOTICE:
        case E_WARNING:
        case E_USER_NOTICE:
        case E_USER_WARNING:
            if($err_no == E_NOTICE || $err_no == E_USER_NOTICE)
            {    $type = 'Notice'; }
            else
            {    $type = 'Warning'; }
            
            $err_str = "<strong>[System Debug] PHP {$type}</strong>: {$err_msg} in file <strong>{$err_file}</strong> on line <strong>{$err_line}</strong><br />\n";
            
            file_put_contents($ERR_LOG_FILE, $timeStamp.strip_tags($err_str), FILE_APPEND);
            
			if ( $debug_on == true )
            {    echo $err_str ; }
            
			
			return;

        break;
        case E_ERROR:
        case E_USER_ERROR:
        case 4096: //E_RECOVERABLE_ERROR
            
            //Set headers to prevent caching... 
            header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
            header("Pragma: no-cache"); // HTTP/1.1
            header("Expires:".gmdate("D, d M Y H:i:s", time())." GMT"); //Expire the content asap.
            
            if( preg_match('#\[([^\]]*)\]#ism', $err_msg, $m) )
			{	
				$err_msg = str_replace($m[0], '', $err_msg);
				$errKind = $m[1];
			}
			elseif( preg_match('#database#is', $err_msg) )
            {    $errKind = 'Database'; }
            elseif( preg_match('#Critical Error:#i', $err_msg) )
            {
                $errKind = 'Critical'; 
                $err_msg = preg_replace('#Critical Error:#is', '', $err_msg);
            }
			else
            {    $errKind = 'General'; }
            
			
			
            $args = array(
                'ERROR_TITLE'    => $errKind.' Error',
                'ERROR'         => $err_msg, 
                'BACKTRACE'        => ( $errKind == 'Database' || $errKind == 'Critical') ? 'Backtrace Dissabled.' : backtrace(),
                'BT_ENABLED'    => ( $errKind == 'Database' || $errKind == 'Critical') ? 'false' : 'true',
                'LASTPAGE'        => ''
            );
            
            $error = $tpl->compileKeys('error.html', $args);
            
            file_put_contents($ERR_LOG_FILE, strip_tags("PHP-{$err_no}:  {$err_msg} in file {$err_file} on line {$err_line}\n\r"), FILE_APPEND);
            echo @eval(' ?>'.$error.'<?php ');
            exit();
        break;
        
        //Error Supression Control (@)
        case 0:
            return;
        break;
        
        default:
            if( defined('DEBUG_OUTPUT_ON') && DEBUG_OUTPUT_ON == TRUE )
            {
                echo "<strong>[System Debug] PHP Error - {$err_no}</strong>:
                 {$err_msg} in file <strong>{$err_file}</strong> on line <strong>{$err_line}</strong><br />\n";
                
                //error_log("PHP-{$err_no}:  {$err_msg} in file {$err_file} on line {$err_line}", 3, $ERR_LOG_FILE);
                @file_put_contents($ERR_LOG_FILE, strip_tags("PHP-{$err_no}:  {$err_msg} in file {$err_file} on line {$err_line}\n\r"), FILE_APPEND);
            }
            
            return;
        break;
    }
}

?>