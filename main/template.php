<?php
/*
#    File:          /main/template.php
#    Author:     Caleb Brumfield
#    License:    GNU GPLv3
#    Source:        https://github.com/Cheshire121/StrongWeb-CMS
*
#    Description:
|        This file contains the Template Engine.
|    This class, 'template', is used to locate and normalize
|    any resources used by the software at run-time.
*    
#    Intensions:
|        To provide a class for managing HTML templates.
|    Allow for dynamic use of variables/data.
|    Providide a framework through which HTML, CSS, and JavaScript
|    can be normalized to comply fully with W3C standards like XHTML.
*
#    Public Member List:
|     template( $tplName )                     - constructor, requires the name of a template directory.
|     Output( $numPasses=null )                - Finalize object data and output.
|     GetImagesPath()                        - Method to retrieve file path from root to the template 'images' directory. 
|     GetTemplatePath()                        - Method to retrieve file path from root to the template directory. 
|     compileKeys( $fileName, $arrayData )     - Method used to compose HTML data by replacing template-keys(CBKs) with associative data from an array.
|     RegisterAddons( $addonList )            - Method only called on Pages, used to pre-load addon(s) into template keys. Requires a list of names separated by '|'
|     RegisterTemplateVariable( $name, $value="" )    - Adds a template key to the template object. Can take an associative array instead of one name-value pair.
|
*/

if( !defined('IN_SWCMS') )
{	exit(); }


class template
{
    private $templateData        = "";
    private $cssData             = "";
    private $jsData              = "";
    
    private $templatePath        = "";
    private $templateIMGPath     = "";
    private $templateURIPath     = "";
    private $templateIMG_URIPath = "";
    
    private $RegisteredVariables = array();
    private $RegisteredResources = array();
    
    
    public function template( $templateName )
    {
        global $config;
		
		$baseURL = (defined('SOFTWARE_INSTALLED') && SOFTWARE_INSTALLED == true)? $config->GetConfig('SITE_URL') : WEB_DOC_ROOT;
        
        $this->templatePath     = ROOT_PATH.'/templates/'.$templateName.'/';
        $this->templateIMGPath     = ROOT_PATH.'/templates/'.$templateName.'/images/';
        
        $this->templateURIPath         = $baseURL.'/templates/'.$templateName.'/';
        $this->templateIMG_URIPath     = $baseURL.'/templates/'.$templateName.'/images/';
        
        if( is_dir($this->templatePath) && file_exists($this->templatePath.'main.html') )
        {
            $this->templateData = file_get_contents( $this->templatePath . 'main.html' );
            
            if( file_exists($this->templatePath.'main.css') )
            {    $this->cssData = file_get_contents( $this->templatePath . 'main.css' ); }
            
            if( file_exists($this->templatePath.'main.js') )
            {    $this->jsData = file_get_contents( $this->templatePath . 'main.js' ); }    
        }
        else
        {
            trigger_error('The Template-Manager could not locate the "main" template file for the template "'.$templateName.'" and had to stop.', E_USER_ERROR);
        }
        
        //$this->RegisterTemplateVariable('USER_LOGIN_FORM', $this->generateLoginForm() );
		
        if( defined('SOFTWARE_INSTALLED') && SOFTWARE_INSTALLED == TRUE  && get_class($config) == 'ConfigCache' )
        {
            $this->RegisterTemplateVariable('web_doc_root', $baseURL );
			$this->RegisterTemplateVariable('tpl_root', $this->templateURIPath);
            $this->RegisterTemplateVariable('tpl_images', $this->templateIMG_URIPath);
            $this->RegisterTemplateVariable('site_url', $config->GetConfig('site_url'));
            $this->RegisterTemplateVariable('seo_links_enabled', $config->GetConfig('seo_links_enabled'));
        
            //$this->SetOption('site_tracking_code', $this->config->GetConfig('site_tracking_code'));
        }
        
        $this->SetUserSessionOptions();
    }
    
    public function Output( $p=NULL)
    {
		global $startTime;
		
		//mark down the finishing time.
		$time = explode(' ', microtime());
		$finishTime = $time[1] + $time[0];
		
	    $this->RegisterTemplateVariable( array(
            'page_head_data' => $this->generatePageHead(),
            'nav_link_list' => $this->GenerateSiteLinks(),
			'page_load_time' => round(($finishTime - $startTime), 4),  //Set a template variable to return the micro-time taken to serve the page.
        ));
        
        $this->templateData = $this->ParseTemplateKeys( $this->templateData, $p );
        
        //Correction of PFBC Resources path, since the class does not print them correctly.
        //This should take all the relative directories used by the resources and translate them to a static url.
        $this->templateData = str_replace('/main/PFBC/Resources', '/PFBC/Resources', $this->templateData);
        $this->templateData = str_replace('/PFBC/Resources', generateServerURL().'/PFBC/Resources', $this->templateData);
        
        //Replace the Key-comments so they display as keys should.
        $this->templateData = str_replace(array('{ -!-', '-!- }'), array('{', '}'), $this->templateData );

        /** / //Debug code, used to find errors in the conditionals.
        $this->templateData = str_replace(array("<?php", "?>"), array('<!-- [PHP] ', ' [/PHP]-->'), $this->templateData );
        /**/

        if(@eval(' ?>'.$this->templateData.'<?php ') === false)
        {
            trigger_error( '[Critical Output]The template engine encountered a critical error, and was required to stop.'.
                            '<br />Notification of a Webmaster is advised.', E_USER_ERROR ); 
        }
		
		//TODO: Create flag allowing the output from above to be caught and cached completely as HTML. 
		
        //echo $this->templateData;
        exit();
    }
    
	//Returns the system-path to the current template's images folder.
    public function GetImagesPath()
    {    return $this->templateIMGPath; }
    
	//Returns the system-path to the current template folder.
    public function GetTemplatePath()
    {    return $this->templatePath; }
	
	//Returns a relative-path to the current template's images foler.
	public function GetImages_URIPath()
    {    return $this->templateIMG_URIPath; }
    
	//Returns a relative-path to the current template folder.
    public function GetTemplateURIPath()
    {    return $this->templateURIPath; }
    
	//used to quickly load a template resource file and replace any existing keys with data from an associative array.
    public function compileKeys( $fileName, $args = array(), $passes=1 )
    {
        $filePath = $this->linkFile( $fileName );
        
        if( !empty($args) && $filePath != false )
        {
            $old_args = $this->RegisteredVariables;
            
            $this->RegisteredVariables = array_merge($args, $old_args);
            
            $data = file_get_contents($filePath);
            $data = $this->ParseTemplateKeys( $data, $passes);
            
            $this->RegisteredVariables = $old_args;
            
            return $data;
        }
        else
        {
            return false;    
        }
    }
    
	//The function which handles replacement of inline-conditional comments as well as all curly-bracket-keys.
    private function ParseTemplateKeys( $data, $passes=NULL )
    {
        $addons = new addons();
        
        if( $passes == NULL)
        {    $passes = 3; }  //Default of three passes for extracting all template keys from embeded runtime code.
        
        for($pass = 0; $pass < $passes; $pass++)
        {
            //This first pass allows us to directly grab the HTML comments and search them for conditionals.
            preg_match_all('#<!-- ([^<].*?) (.*?)? ?-->#', $data, $m);
            $cm = count($m[0]);
			for($i=0; $i < $cm; $i++)
            {
                if( isset($m[0][$i]) && !empty($m[0][$i]) )
                {
                    $data = str_ireplace( $m[0][$i], $this->CompileConditions( $m[0][$i] ), $data);
                }
            }
            
            //Here we look foor and replace add-on-keys with their pre-loaded data.
            preg_match_all('#{P_(.*?):(.*?)}#im', $data, $m );
			$cm = count($m[0]);
            for($i=0; $i < $cm; $i++)
            {
                if( isset($m[1][$i]) && !empty($m[1][$i]) )
                {
                    $data = str_replace( $m[0][$i], $this->RegisteredVariables[ strtoupper( $m[1][$i] ) ], $data );
                }
            }
            
            //Not yet implented, this attempts to run an addon "in-line" with the template execution.
            //It could be used for generating ads or such.
            preg_match_all('#\\{PR_(.*?):(.*?)\\}#i', $data, $m );
            $cm = count($m[0]);
			for($i=0; $i < $cm; $i++)
            {
                if( isset($m[1][$i]) && !empty($m[1][$i]) )
                {
                    $args=array();
                    
                    if( isset($m[2][$i]) && !empty($m[2][$i]) )
                    {
                        $nv_pairs = explode(',', $m[2][$i]);
                        foreach($nv_pairs as $nvp)
                        {
                            $nv = explode('=', $nvp);
                            
                            if( isset($nv[1]) )
                            {    $args[ $nv[0] ] = $nv[1]; }
                        }
                    }
                    
                    $data = str_replace( $m[0][$i], $addons->RunAddon( $m[1][$i], $args ), $data );
                }
            }/**/
            
            //Finally look for and remove all other keys found. Keys don't get displayed ever.
            preg_match_all( '#\\{([^\s].*?)\\}#i', $data, $m );
			$cm = count($m[0]);
            for($i=0; $i < $cm; $i++)
            {
                if( isset($m[1][$i]) && !empty($m[1][$i]) )
                {
                    //Variable translations happen here:
					$vType = explode(':', $m[1][$i]);
					switch( strtolower($vType[0]) )
					{
					case 'bool':
						$key = strtoupper(str_replace($vType[0].':', '', $m[1][$i]));
						
						if( isset($this->RegisteredVariables[ $key ]) && !empty($this->RegisteredVariables[ $key ]) )
                    	{    
                    	    $data = str_replace( $m[0][$i], 'TRUE', $data);
                    	}
                    	else
                    	{
                    	    $data = str_replace( $m[0][$i], 'FALSE', $data);
                    	}
						break;
					
					default:
						if( isset($this->RegisteredVariables[ strtoupper($m[1][$i]) ]) )
                    	{    
                    	    $data = str_replace( $m[0][$i], $this->RegisteredVariables[ strtoupper( $m[1][$i] ) ], $data);
                    	}
                    	else
                    	{
                    	    $data = str_replace( $m[0][$i], '', $data);
                    	}
						break;
					}
                }
            }
        }//end main loop.
        
        //We want to find and exchange any remaining html-comment-conditionals with PHP conditionals for evaluation.
        $data = $this->CompileConditions( $data );
        
        return $data;
    }
    
	//Used to generate a standard set of resource links and meta-data tags for use in the template <head> tag.
    public function generatePageHead()
    {
        global $config;
        
        $head="";
        $meta='';
        if( defined('SOFTWARE_INSTALLED') && SOFTWARE_INSTALLED == true )
        {
            $meta = '<meta name="description" content="' . $config->GetConfig('meta_description') . '" />'."\n".
                    '<meta name="keywords" content="' . $config->GetConfig('meta_tags') . '" />'."\n";
        }
        
        $meta .='<meta name="copyright" content="Website programmed by Caleb Brumfield, Copyright 2010-2012'.
                ' Third-party software are copyright to their respective owners." />'."\n";
        
        $css = '<style type="text/css">'."\n".$this->cssData."\n".'</style>'."\n";
        $js  = '<script language="javascript" type="text/javascript">'."\n/*<![CDATA[*//*---->*/\n".$this->jsData."/*--*//*]]>*/\n".'</script>'."\n";
        
        $rsLinks = $this->generateResourceLinks();
        
        $head = $meta . $rsLinks . $css . $js ;
    
        return $head;
    }
    
	//Allows the current page to pre-load any required/supporting addons.
    public function RegisterAddons( $addonList )
    {
        $addons = new addons();
        
        $aParts = explode('|', $addonList);
        
        foreach($aParts as $addon )
        {
            //Create an instance of the addon/plugin.  Invoke the run command.
            //get returned data, place into template variable register.
            if( !empty($addon) )
            {
                $args=array();
                $parts = explode(':', $addon);
                
                if( isset($parts[1]) && !empty($parts[1]) )
                {
                    $nv_pairs = explode(',', $parts[1]);
                    foreach($nv_pairs as $nvp)
                    {
                        $nv = explode('=', $nvp);
                        
                        if( isset($nv[1]) )
                        {    $args[ trim($nv[0]) ] = trim($nv[1]); }
                    }
                }
                
                $addonName = strtolower( trim( $parts[0] ));
                
                $this->RegisterTemplateVariable( strtoupper($parts[0]), $addons->RunAddon( $addonName, $args ) );
                
            }
        }
    }
    
	//Add or update a template CBK variable.  The name associates to a key between curly-brackets which is replace by $value at run-time.
    public function RegisterTemplateVariable( $name, $value="" )
    {
        if( is_array($name) )
        {
            foreach($name as $k => $v)
            {
                $this->RegisterTemplateVariable( $k, $v );
            }
        }
        else
        {
            if( !array_key_exists(strtoupper($name), $this->RegisteredVariables) )
            {
                $this->RegisteredVariables[ strtoupper($name) ] = $value;
            }
        }
    }
    
	//Pass raw CSS stylesheet data to the template manager for use in the page-head.
    public function LoadCSSData( $css )
    {
        $this->cssData .= $css."\n";
    }
    
	//Pass raw JavaScript data to the template for use in the page-head.
    public function LoadJSData( $js )
    {
        $js = str_replace('/*<![CDATA[*//*---->*/', '', $js );
		$js = str_replace('/*--*//*]]>*/', '', $js );
		
		$this->jsData .= $js."\n";
    }
    
	//Strips script and css assets from $htmlData and adds said assets to the page-head.
	// The function then returns the asset-less html.
    public function parseExternalHTML( $htmlData )
    {
        //echo htmlentities( $htmlData );
        
        //Look for link, script, and style tags in the html data.
        //Store data from tags for later use in page-head data.  Remove tags from html data and return cleaned data.
        preg_match_all('#<script[^>]+>(.*?)<\\/script>#ism', $htmlData, $m);
        $cm = count($m);
		for($i=0; $i <= $cm; $i++ )
        {
            //This tag has text/javascript in it.
            if( isset($m[0][$i]) && !empty($m[0][$i]) && !empty($m[1][$i]) )
            {
                $this->LoadJSData( str_replace(';j', ";\nj", $m[1][$i]) );
                $htmlData = str_replace($m[0][$i], '', $htmlData);
                
            }
            
            //This tag is just a link.
            if( isset($m[0][$i]) && !empty($m[0][$i]))
            {
                //Get the src url from the tag and register it.
                preg_match('#<script.*?src="(.*?)">.*?#ism', $m[0][$i], $matches );
                
                if( isset($matches[1]) && !empty($matches[1]) )
                {
                    $this->RegisterResource( $matches[1] );
                    $htmlData = str_replace($m[0][$i], '', $htmlData);
                }
            }
        }
        
        preg_match_all('#<style[^>]+>(.*?)<\\/style>#ism', $htmlData, $m);
		$cm = count($m);
        for($i=0; $i <= $cm; $i++ )
        {
            //This tag has text/javascript in it.
            if( isset($m[0][$i]) && !empty($m[0][$i]) && !empty($m[1][$i]) )
            {
                $this->LoadCSSData( str_replace(array('}#', '}.'), array("}\n#", "}\n."), $m[1][$i]) );
                $htmlData = str_replace($m[0][$i], '', $htmlData);
            }
        }
        
        preg_match_all('#<link[^>]*href="(.*?)"[^>]*\\/>#ism', $htmlData, $m);
        $cm = count($m);
		for($i=0; $i <= $cm; $i++ )
        {
            //This tag has text/javascript in it.
            if( isset($m[0][$i]) && !empty($m[0][$i]) && !empty($m[1][$i]) )
            {
                $this->RegisterResource( $m[1][$i] );
                $htmlData = str_replace($m[0][$i], '', $htmlData);
            }
        }
        
        return $htmlData;
    }
    
	
	//Non-implemented local-time function.
    public function PrintUserTime($timestamp, $format='m/d/Y - g:ia')
    {
        // Calculate user timezone and dst offset.
        
        // Get current server time, and set it to UTC 0 
        $now = $timestamp;
        $serv_off = (0 - (intval(date('P')) * 3600)); //server offset, in seconds, and reverse it.
        $time = ($now + $serv_off);

        // Get user offest from UTC
        $time = ($time + ($_SESSION['user']->timezone * 3600));

        // Is it DST?
        if (date('I') == 1 && $_SESSION['user']->time_usedst == true)
        {
            $time = ($time + 3600);
        }
        
        return date($format, $time);
    }
    
	// Encodes all characters in $str to their ordinal HTML entities. 
    public function CharacterEncode($str)
    {
        $chars=array();
        $str = chunk_split($str, 1, '|');
        $str_arr = explode('|', $str);
        
        foreach($str_arr as $char)
        {
            $ord = ord($char);
            $chars[] = str_replace($char, '&#'.$ord.';', $char);
        }
    
        $str = implode("", $chars);
        
        return $str;
    }
    
	//Builds resource-link HTML data, for use in page-head.
    private function generateResourceLinks()
    {
        global $config;
        
        $links = '';
        
        foreach($this->RegisteredResources as $file)
        {
            $ext_arr = explode('.', $file);
            $ext = end( $ext_arr );
            $ext = strtolower( $ext );
            
            if( $this->linkFile($file) == true )
            {
                $link = $this->templateURIPath.$file;
            }
            else
            {    $link = $file; }
            
            if( $ext == 'js' )
            {
                $links .= '<script language="javascript" type="text/javascript" src="'.$link.'"></script>'."\n";
            }
            
            if( $ext == 'css' )
            {
                $links .= '<link type="text/css" rel="stylesheet" href="'.$link.'" />'."\n";
            }
        }
        
        $this->RegisterTemplateVariable( 'RESOURCE_LINKS', $links);
        return $links;
    }
    
	//Allows user to add a resource, javascript, or css-file link to the template head.
    public function RegisterResource( $link )
    {
        if( is_array($link) )
        {
            foreach($link as $l)
            {
                $rt = $this->registerResource( $l );
                
                if( $rt == false )
                {    return false; }
            }
        }
        else
        {
            //Check here for relative resource files.
			// They will be passed without http wrappers.
			if( !preg_match('#https?://(.*?)#is', $link) )
			{
				//make sure the relative resource is valid and exists in the template.
				if( $this->linkFile( $link ) != false )
				{	$link = $this->templateURIPath . $link; }
			}
			
			if( !in_array($link, $this->RegisteredResources) )
            {
                $this->RegisteredResources[] = $link;
            }
            else
            {    return false; }
        }
    }
    
	//Generates a log-in form or user-links-panel depending on the log-in status of a given user.
    private function generateUserBox()
    {
        $formFile = $this->linkFile('login_panel.php');
        if( !empty($formFile) )
        {
            $keys = array( 'FORM_ACTION_URL' => '/index.php?action=login&return_path='.base64_encode($_SERVER['REQUEST_URI']) );
            
            $formHTML = $this->compileKeys($formFile, $keys);
        }
        else
        {
            if( $_SESSION['user']->logged_in == 1 )
            {
                $formHTML = 'user_panel.html';
            }
            else
            {
                $form = new Form("site_login_form", null, '/index.php?action=login&return_path='.base64_encode($_SERVER['REQUEST_URI']) );
                $form->configure(array(
                    "view" => new View_Horizontal
                ));
                $form->addElement(new Element_Textbox("Username:", "username"));
                $form->addElement(new Element_Password("Password:", "password"));
                $form->addElement(new Element_Checkbox('Remember Me:', 'remember', array(1)));
                $form->addElement(new Element_Button("Login"));
                
                $formHTML = $this->parseExternalHTML( $form->render(true) );
            }
        }
        
        return $formHTML;
    }
    
	//checks the current template directory for a given file-name and return the path to that file if it is found. 
    private function linkFile( $name )
    {
        $filePath = $this->templatePath . $name;
        if( file_exists($filePath) )
        {
            return $filePath;
        }
        else
        {    return false; }
    }
    
	//Adds variables to the template corresponding to the current user.
    private function SetUserSessionOptions()
    {
        if( isset($_SESSION['user']) )
        {
            $this->RegisterTemplateVariable('S_LOGGED_IN', ($_SESSION['user']->logged_in == 1) ? 'TRUE' : 'FALSE' );
            $this->RegisterTemplateVariable('s_username', $_SESSION['user']->name);
            $this->RegisterTemplateVariable('s_user_id', $_SESSION['user']->uid);
        }
        else
        {
            $this->RegisterTemplateVariable('s_logged_in', 'FALSE' );
            $this->RegisterTemplateVariable('s_username', 'Anon');
            $this->RegisterTemplateVariable('s_user_id', '1');
        }
    }
    
	//Generates the site navigation panel. 
    private function GenerateSiteLinks()
    {
        global $db, $cache, $config, $gpc;
        
        $in_admin = $gpc->get('SEO', 'in_admin');
        if( !empty($in_admin) || SOFTWARE_INSTALLED != TRUE)
        {    return false; }
        
        //Check for cached links, 
        if( $cache->checkCache('_nav_') )
        {
            $links = $cache->getCache('_nav_');
        }
        else
        {
            //Make links...
            $links='';
            
            //Get database records, load into record-set and iterate each 'row' or set of records.
            $rs = $db->Execute('SELECT `link_text`, `link_url`, `link_enabled` FROM `links` WHERE `link_enabled` > 0 ORDER BY `link_enabled` ASC');
            foreach($rs as $row )
            {
                //Process internal site links.
                $link='#errorInLinker';
                if( preg_match('#site://([0-9]+)#i', $row['link_url'], $m ))
                {
                    if( isset($m[1]) && $m[1] != 0 )
                    {
                        if($config->GetConfig('SEO_LINKS_ENABLED'))
                        {
                            $link = '{SITE_URL}/pg'.$m[1].'/'.urlencode(str_replace(' ', '_', $row['link_text']));
                        }
                        else
                        {
                            $link = '{SITE_URL}/index.php?page_id='.$m[1];
                        }
                    }
                }
                elseif( preg_match('#https?://#i', $row['link_url']) )  //Process the external links. Just making sure they're really links. :)
                {
                    $link = $row['link_url'];
                }
                
                //Use Processed link from above with the 'link_text' field to compile each link.
                $keys = array(
                    'LINK_TEXT'    => $row['link_text'],
                    'LINK_URL'    => $link
                );
                $links .= $this->compileKeys( 'nav_links.html', $keys);
            }
            
        }
        $cache->setCache('_nav_', $links);
        
        return $links;
    }

    private function CompileConditions($contents)
    {
        //in-template conditional statements.
        preg_match_all('#<!-- ([^<].*?) (.*?)? ?-->#', $contents, $matches);
        $condits = $matches[1]; 
        
        //preg_match_all('#<!-- IF ([^<].*?) -->#', $contents, $ifs);
        
        for($i=0, $ccount=count($condits); $i < $ccount; $i++)
        {
            switch($condits[$i])
            {
                case 'IF':
                    $contents = preg_replace('#<!-- IF '.preg_quote($matches[2][$i], '#').' ?-->#', $this->CC_if($matches[2][$i], 'if'), $contents);
                break;
                case 'ELSE':
                    $contents = preg_replace('#<!-- ELSE -->#', '<?php } else { ?>', $contents);
                break;
                case 'ELSEIF':
                    $contents = preg_replace('#<!-- ELSEIF '.preg_quote($matches[2][$i], '#').' -->#', $this->CC_if($matches[2][$i], 'elseif'), $contents);
                break;
                case 'ENDIF':
                    $contents = preg_replace('#<!-- ENDIF -->#', '<?php } ?>', $contents);
                break;
            }
        }
        
        //Clean the PHP to make it easier on the parser.
        return str_replace('?><?php', ' ', $contents);
        
    }
    
    private function CC_if($vars, $type)
    {
        preg_match_all('/(?:
            "[^"\\\\]*(?:\\\\.[^"\\\\]*)*"         |
            \'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'     |
            [(),]                                 |
            [^\s(),]+)/x', $vars, $match);
            
        $parts = $match[0];
		$cp = count($parts);
        for($i=0; $i < $cp; $i++)
        {
            $token = &$parts[$i];

            switch ($token)
            {
                case '!==': case '===': case '<<': case '>>': case '|':
                case '^': case '&': case '~': case ')': case '(':
                case ',': case '+': case '-': case '*': case '/':
                case '@':
                    $token = '';
                break;

                case '==': case 'eq':
                    $token = '==';
                break;

                case '!=': case '<>': case 'ne': case 'neq':
                    $token = '!=';
                break;

                case '<': case 'lt':
                    $token = '<';
                break;

                case '<=': case 'le': case 'lte':
                    $token = '<=';
                break;

                case '>': case 'gt':
                    $token = '>';
                break;

                case '>=': case 'ge': case 'gte':
                    $token = '>=';
                break;

                case '&&': case 'and':
                    $token = '&&';
                break;

                case '||': case 'or':
                    $token = '||';
                break;

                case '!': case 'not':
                    $token = '!';
                break;
                
                default:
                    if (!empty($token))
                    {
                        $token = '(' . $token . ')'; 
                    }
                break;
            }
        }
        
        $vars_clean = implode('', $parts);
        
        /*foreach($this->template_opts as $key => $val)
        {
            $vars_clean = str_replace(strtoupper($key), $val, $vars_clean);
            $vars_clean = str_replace('{'.strtoupper($key).'}', $val, $vars_clean);
        }/**/
        
        unset($vars);
        unset($parts);
        
        if($type == 'elseif')
        {    return '<?php } elseif('.$vars_clean.') { ?>'; }
        else
        {    return '<?php if('.$vars_clean.') { ?>'; }
            
    }
}

/**
*  @name         HTML_Table
*  @access       public 
*  @returns      void
*  @arg1         (string) - (Optional) An ID to use in the 'id' attribute of this tables' HTML tag. 
*  @arg2         (string) - (Optional) A class-name attribute to add to the cell
*  @arg3         (int)    - (Optional) The border-size attribute.
*  @arg3         (int)    - (Optional) The cellspacing attribute.
*  @arg3         (int)    - (Optional) The cellpadding attribute.
*  @arg4         (array)  - (Optional) An array of html-tag attributes for use in the HMLT Table Tag.
*  @description   An object allowing for easy construction of HTML tables.
*/
class HTML_Table {
    
    private $rows = array();
    private $tableStr = '';
    
    public function HTML_Table($id=NULL, $class=NULL, $border=0, $cellspacing=2, $cellpadding=0, $attr=array() ) 
	{
        $id    = ( !empty($id)? " id=\"$id\"": '' );
		$class = ( !empty($class)? " class=\"$class\"": '' );
		 
		$this->tableStr = "\n<table" . $id . $class . $this->buildAttributes( $attr ) . 
						  " border=\"$border\" cellspacing=\"$cellspacing\" cellpadding=\"$cellpadding\">\n";
	}
    
	/**
	*  @name         addRow
	*  @access       public 
	*  @returns      void
	*  @arg1         (string) - (Optional) A class-name attribute to add to the cell
	*  @arg2         (array)  - (Optional) An array of html-tag attributes for use in the cell td/th tag.
	*  @description   Adds a Table-Row to the table object.
	*/
    public function addRow($class=NULL, $attributes=array() ) 
	{
        //Initial data for the row. 
		$row = array(
			'class' => $class,
			'attr'  => $attributes,
			'cells' => array(),      //Sub-array contianing arrays for each cell in this row.
		);
	   
	    //Add the row to the table-objects row list.
        array_push( $this->rows, $row );
    }
    
	/**
	*  @name         addCell
	*  @access       public 
	*  @returns      void
	*  @arg1         (string) - (Optional) Data or text to insert into cell.
	*  @arg2         (string) - (Optional) A class-name attribute to add to the cell
	*  @arg3         (string) - (Optional) A string describing if this cell is a "header" cell or "data" cell.
	*  @arg4         (array)  - (Optional) An array of html-tag attributes for use in the cell td/th tag.
	*  @description   Adds a Table-Cell to the last added row of this table object.
	*/
    public function addCell($data = '', $class=NULL, $type='data', $attributes=array() ) 
	{
        //Build the initial data array.
		$cell = array(
			'data'  => $data,
			'class' => $class,
			'type'  => $type,
			'attr'  => $attributes,
			'cells' => array(),
		);
		
        // add new cell to current row's list of cells by reference-loading the current row's cells.
        $curRow = &$this->rows[ count( $this->rows ) - 1 ];
        array_push( $curRow['cells'], $cell );
    }
    
	/**
	*  @name         render
	*  @access       public 
	*  @returns      (string) - A formatted HTML Table, complete with rows, cells, and table-header cells.
	*  @arg1         void
	*  @description  Builds the table object into an HTML string for use in output/display.
	*/
    public function render()
	{
        foreach( $this->rows as $row )
		{
            $this->tableStr .= !empty($row['class']) ? '  <tr class="'.$row['class'].'"': '  <tr';
            $this->tableStr .= $this->buildAttributes( $row['attr'] ) . ">\n";
            $this->tableStr .= $this->buildCells( $row['cells'] );
            $this->tableStr .= "  </tr>\n";
        }
        $this->tableStr .= "</table>\n";
        return $this->tableStr;
    }
   	
	/**
	*  @name         buildAttributes
	*  @access       private
	*  @returns      (string) - A formatted list of HTML attributes and their values.
	*  @arg1         (array)  - An array describing HTML-tag attributes and their associated values.
	*  @description   Used to create a list of HTML-tag attributes based on an array.
	*/
	private function buildAttributes( $attr )
	{
        $str = '';
        foreach( $attr as $key=>$val ) 
		{   $str .= " $key=\"$val\""; }
        
		return $str;
    }
	
	/**
	*  @name         buildCells
	*  @access       private
	*  @returns      (string) - A formated 'th' or 'td' tag.
	*  @arg1         (array)  - An array containing data describing a set of cells.
	*  @description   Used to create a list of HTML table-cells.
	*/
    private function buildCells($cells)
	{
        $str = '';
        foreach( $cells as $cell )
		{
            $tag = ($cell['type'] == 'data')? 'td': 'th';
            $str .= !empty($cell['class']) ? '    <'.$tag.' class="'.$cell['class'].'"': "    <{$tag}";
            $str .= $this->buildAttributes( $cell['attr'] ) . ">";
            $str .= $cell['data'];
            $str .= "</{$tag}>\n";
        }
        return $str;
    }
    
}


?>