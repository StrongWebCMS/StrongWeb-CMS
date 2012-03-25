/** Begin File-Manager variables **/

// An active array for storing file/directory names.
var fs_json;
var current_dir;
var num_uploads = 0;

/** Begin File-Manager functions **/
function fs_openDirectory( relDir )
{
  $.get("/admin.php?admin_panel=filemanager&ajax=opendir&dir=" + relDir, function( data ) 
  {
    elm = $('#file_list_div');
	elm.empty().append( data );
	
	$.get("/admin.php?admin_panel=filemanager&ajax=getcurrentdir", function( data ) 
    {
    	//Update the file-path URI box.
 		$('#file_path_box').val( data );
	});
  });
}

function fs_gotoFileURI()
{
	var uri = $('#file_path_box').attr('value');
	
	$.get("/admin.php?admin_panel=filemanager&ajax=gotodir&dir=" + uri, function( data ) 
	{
	    elm = $('#file_list_div');
		elm.empty().append( data );
  	});
}

function fs_JSON_LoadData()
{
	if( fs_json == undefined )
	{
		var json = $.parseJSON( $('#fs_json_data').attr('value') );
		var newArr = new Array();
		
		for(var i=0; i < json.length; i++)
		{
			//do regex check for trailing slash, checks for is_dir.
			
			newArr[i] = new Array( json[i], false ); //index: filename, rename_active, is_dir
		}
		
		fs_json = newArr;
	}
}

function fs_renameItem( elmNum )
{
	fs_JSON_LoadData();
	
	var selector = '#fs_label_' + elmNum;
	var lbl = $( selector );
	
	if( fs_json[ elmNum ][1] == false )
	{
		//Set the rename-global to true, for the next run.
		fs_json[ elmNum ][1] = true;
		
		lbl.removeAttr('readonly');
		lbl.attr( 'class', 'fs_label_active' );
		
		lbl.focus().select();
		
		lbl.bind('keydown',function(e)
		{
			if( e.which == 13 )
			{	fs_renameItem( elmNum ); } 
		});
	}
	else
	{
		//get the "new" name of the file.
		var newName = lbl.attr( 'value' );
		//Strip the white-space from both ends.
		newName = newName.replace(/^\s+/,"");  // strip leading spaces
		newName = newName.replace(/\s+$/,"");  // strip trailing spaces
		
		//The pattern used to check for certain characters or system-specific sequences.
		var invalidName = RegExp( '[\\\/\:\*\?\"\<\>\|\;]+|(^con$)|(^lpt[0-9]*$)|(^prn$)', 'i' );
		
		//check that the name is valid in the filesystem.
		if( !invalidName.test( newName ) )
		{ 
			//the name is valid!
			//Send the update to the server to process.
			var uri = "/admin.php?admin_panel=filemanager&ajax=rename&on=" + encodeURI(fs_json[ elmNum ][0]) + "&nn="+ encodeURI(newName);
			$.get(uri, function( data ) 
 			{
				//test the response.
				var reg = new RegExp( '^error\:(.*?)$', 'im');
				if( reg.test( data ) )
				{
					data = data.replace( /^error\:/, '', data );
					
					alert( data );
					
					//reset the name to its original value.
					lbl.attr( 'value', fs_json[ elmNum ][0] );
				}
				else
				{
					//Add the new name to the JSON list.
					fs_json[ elmNum ][0] = newName;
					
					//Get the icon alt data, which is used to determine updates.
					var icon_type = $('#fs_icon_'+elmNum ).attr('alt');
					
					//If the icon isn't a folder, then go get the updated icon path from the server.
					if( icon_type != 'DIR' )
					{
						$.get( "/admin.php?admin_panel=filemanager&ajax=getfileicon&filename=" + encodeURI( newName ), function( data )
						{
							$( '#fs_icon_'+elmNum ).attr('src', data );
						});
					}
				}
  			});
		}
		else //Is not valid name.
		{
			alert("The following characters are not valid:\n | \\ / ? : ; * \" < > \nPlease chose another name and try again.");
			
			//reset the name to its original value.
			lbl.attr( 'value', fs_json[ elmNum ][0] );
		}
		
		//make field read-only after the edit.
		lbl.attr('readonly', true);
		lbl.attr('class', 'fs_label');
		lbl.unbind( 'keydown' );
		
		//reset the rename function global.
		fs_json[ elmNum ][1] = false;
	}
}

function fs_removeItem( elmNum )
{
	fs_JSON_LoadData();
	
	var lbl  = $( '#fs_label_'+ elmNum ).attr('value');
	var type = $( '#fs_icon_'+ elmNum ).attr('alt');
	
	//Make the dialog box active depending on the deletion-type
	if( type == 'DIR' )
	{
		$( '#dir_name' ).empty().append( ' "'+lbl+'"' );
		
		$( "#fs_delete_dir_dialog" ).dialog({
			resizable: false,
			height: 215,
			width: 380,
			modal: true,
			draggable: true,
			buttons: {
				"Delete file(s)": function() 
				{
					//send the delete command.
					var uri = "/admin.php?admin_panel=filemanager&ajax=rm&item=" + encodeURI(lbl);
					$.get(uri, function( data ) 
 					{
						//should check for successful delete first.
						if( data == 'false' )
						{	alert('The file "'+ lbl +'" could not be removed.'+"\nReload the page and try again."); }
						else
						{
							//Reload the current listing.
							$.get("/admin.php?admin_panel=filemanager&ajax=reloadcurrentdir", function( data ) 
    						{
								elm = $('#file_list_div');
								elm.empty().append( data );
								
								//refresh the client data array.
								fs_JSON_LoadData();
							});
						}
					});
					$( this ).dialog( "close" );
				},
				Cancel: function() {
					$( this ).dialog( "close" );
				}
			}
		});
	}
	else
	{
		$( '#file_name' ).empty().append( ' "'+lbl+'"' );
		
		$( "#fs_delete_file_dialog" ).dialog({
			resizable: false,
			height: 215,
			width: 380,
			modal: true,
			draggable: true,
			buttons: {
				"Delete": function() 
				{
					//send the delete command.
					var uri = "/admin.php?admin_panel=filemanager&ajax=rm&item=" + encodeURI(lbl);
					$.get(uri, function( data ) 
 					{
						//should check for successful delete first.
						if( data == 'false' )
						{	alert('The file "'+ lbl +'" could not be removed.'+"\nReload the page and try again."); }
						else
						{
							//Reload the current listing.
							$.get("/admin.php?admin_panel=filemanager&ajax=reloadcurrentdir", function( data ) 
    						{
								elm = $('#file_list_div');
								elm.empty().append( data );
								
								//refresh the client data array.
								fs_JSON_LoadData();
							});
						}
					});
					$( this ).dialog( "close" );
				},
				Cancel: function() {
					$( this ).dialog( "close" );
				}
			}
		});
	}
}

function fs_addDir()
{
	$( "#fs_adddir_dialog" ).dialog({
		resizable: false,
		height: 215,
		width: 380,
		modal: true,
		draggable: true,
		buttons: {
			"Add Directory": function() 
			{
				//get the "new" name of the file.
				var newName = $('#fs_adddir_name').attr( 'value' );
				//Strip the white-space from both ends.
				newName = newName.replace(/^\s+/,"");  // strip leading spaces
				newName = newName.replace(/\s+$/,"");  // strip trailing spaces
				
				//The pattern used to check for certain characters or system-specific sequences.
				var invalidName = RegExp( '[\\\/\:\*\?\"\<\>\|\;]+|(^con$)|(^lpt[0-9]*$)|(^prn$)', 'i' );
				
				//check that the name is valid in the filesystem.
				if( !invalidName.test( newName ) && newName != "" )
				{ 
					$.get("/admin.php?admin_panel=filemanager&ajax=addfolder&dirname="+ encodeURI(newName), function( data ) 
    				{
						if( data == 'false')
						{
							alert( "Error: Directory creation failed!" );
						}
						else
						{
							elm = $('#file_list_div');
							elm.empty().append( data );
							
							$('#fs_adddir_name').val('');
							
							//refresh the client data array.
							fs_JSON_LoadData();
						}
					});/**/
					
					$( this ).dialog( "close" );
				}
				else
				{
					alert( "New Directory name is Invalid!\nPlease try a different name." );
				}
			},
			Cancel: function() 
			{
				$( this ).dialog( "close" );
			}
		}
	});
}


var timeout = 0;
function fs_addFile()
{	
	$( "#fs_addfile_dialog" ).dialog({
		resizable: true,
		height: 215,
		width: 400,
		modal: true,
		draggable: true,
		buttons: {
			"Add File": function()
			{
				var fileForm = $("#fs_upload_form");
				
				num_uploads = num_uploads + 1;
				
				var newFileElm = $('<label>New File: <input id="fs_up_'+num_uploads+'" name="upload['+num_uploads+']" type="file" /></label><br />');
				fileForm.append( newFileElm );
			},
			"Upload File(s)": function() 
			{
				var fileForm = $("#fs_upload_form");
				var count = $('input[type="file"]', fileForm).length;
				var sendForm = false;
				
				//reset the timeout variable to 0 for this next run.
				timeout = 0;
				
				//looking for a reason to send the form-data.
				for(var i=0; i < count; i++)
				{
					var felm = $('#fs_up_'+i, fileForm);
					if( felm.val() != '' )
					{
						sendForm = true;
						
						//Add a second to the timeout timer.
						timeout = timeout + 1095;
					}
				}/**/
				
				if( sendForm )
				{	//Submit form data.
					fileForm.submit();
				}
				
				//Show some kind of loading message.
				$("#fs_upload_form").empty().append('<span> Uploading<br />Please wait... </span>');
				
				var that = this;
				setTimeout( function(){ $(that).dialog("close"); }, timeout );
			},
			Cancel: function() 
			{
				//Reload form-box after close.
				num_uploads = 0;
				var fileForm = $("#fs_upload_form");
				var newFileElm = $('<label>New File: <input id="fs_up_'+num_uploads+'" name="upload['+num_uploads+']" type="file" /></label><br />');
				
				fileForm.empty().append( newFileElm );
			
				$( this ).dialog( "close" );
			}
		}
	});
}


// prepare the file manager when the DOM is ready
$(document).ready(function() {
    var af_options = {
        success: function(data) 
		{// retrieve a success/failure code from the server
			if( data != '')
			{	alert( data ); } 
			
			setTimeout( function()
			{
				//Reload the current listing.
				$.get("/admin.php?admin_panel=filemanager&ajax=reloadcurrentdir", function( data ) 
    			{
					elm = $('#file_list_div');
					elm.empty().append( data );
					
					//refresh the client data array.
					fs_JSON_LoadData();
				});/**/
				
				//Reload form-box after submit.
				num_uploads = 0;
				var fileForm = $("#fs_upload_form");
				var newFileElm = $('<label>New File: <input id="fs_up_'+num_uploads+'" name="upload['+num_uploads+']" type="file" /></label><br />');
				
				fileForm.empty().append( newFileElm );
				
			}, (timeout) ) ;
		}
    };

    // bind form using 'ajaxForm' on our upload box.
    $('#fs_upload_form').ajaxForm(af_options);
	
	$('#file_path_box').bind('keydown',function(e)
	{
		if( e.which == 13 )
		{	fs_gotoFileURI(); } 
	});
	
	fs_JSON_LoadData();
});
