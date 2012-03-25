<?php

class system_output_test extends addon
{
	public function Run( $args )
	{
		
		return '';
	}
	
	public function Register()
	{
		return array();
		
		//Bellow is a labled structure of the data register for adding system resources along-side addons.
		$x= array(
				//Provide a name to reference when programming, and a description of the permission
				// controls that it will provide when a user is given access.
				// Names cannot be registered twice, and attempting to register a name already registered will fail.
				// it is advised to make them specific to the addon if possible.
			'permissions' => array(
				'logical_name' => 'Description of this permission field.',
				'permission_2' => 'Description of the second permission'
			),
				//Provide a logical-name to reference and a corresponding value to store by default.
			'config'	=> array( 
				'config_1' => '', //Blank defaults my be used.   
				'config_2' => false, 
			),
				//Provide any databse structures used by this addon here.
				//The associative name of the table encapsulates the sql markup, following ADODB datadictionary markup.
				//Any indexes needed by the table can also be specified by name-to-fields associative arrays.
			'sql_tables' => array(
				'email_log' =>array(
					'sql' =>   '`msg_id` I(11) NOTNULL AUTO PRIMARY,
 		 						`sender_name` C(128) NOTNULL,
 		 		 				`sender_email` C(128) NOTNULL,
 		 		 				`subject` C(64) NOTNULL,
	 	 		 				`body` X NOTNULL,
  		 		 				`send_time` I(32) NOTNULL,
		 		 				`send_to` C(128) NOTNULL',
					'idx' => array('sender_name' => 'sender_name')
				)
			),
				//Provide any default database entries here.
				//One insert statement per array index.
			'sql_records' => array()
		);	
			
		
	}
	
	
	//This function is used to house and display the configurations form(s) needed by this addon. 
	public function ConfigPanel()
	{
		global $gpc, $tpl, $db, $config, $cache;
		
		$gpc->RegisterSEOVariable(array(''=>''));
		
		return 'test';
	}
}

?>