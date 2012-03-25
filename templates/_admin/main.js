/* Scripts used for various elements of the admin-panel */

//This variable stores the sites' web-root value given from the server.
var web_doc_root = "{WEB_DOC_ROOT}";

function liClick( elmId )
{
	var elm = document.getElementById( elmId );
	
	document.location = elm.href;
}

function toggleLinkType( type )
{
	if( type == 'Link to a local page.')
	{
		jQuery("#localLink").show(200);
		jQuery("#externalLink").hide(200);
	}
	else if( type == 'Link to an external page.')
	{
		jQuery("#localLink").hide(200);
		jQuery("#externalLink").show(200);
	}
}

/**/
function users_SortList()
{
	var selectList = document.getElementById( 'users_sort_list' );
	
	if( {SEO_LINKS_ENABLED} == true)
	{
		document.location = '/admin/users/list/starting_with-' + selectList.value;
	}
	else
	{
		document.location = '/admin.php?admin_panel=users&users_sec=list&starting_with=' + selectList.value;
	}
}/**/

function replaceSubstring(inputString, fromString, toString)
{
   
   var temp = inputString;
   if (fromString == "") {
      return inputString;
   }
   if (toString.indexOf(fromString) == -1) { // If the string being replaced is not a part of the replacement string (normal situation)
      while (temp.indexOf(fromString) != -1) {
         var toTheLeft = temp.substring(0, temp.indexOf(fromString));
         var toTheRight = temp.substring(temp.indexOf(fromString)+fromString.length, temp.length);
         temp = toTheLeft + toString + toTheRight;
      }
   } else { // String being replaced is part of replacement string (like "+" being replaced with "++") - prevent an infinite loop
      var midStrings = new Array("~", "`", "_", "^", "#");
      var midStringLen = 1;
      var midString = "";
      // Find a string that doesn't exist in the inputString to be used
      // as an "inbetween" string
      while (midString == "") {
         for (var i=0; i < midStrings.length; i++) {
            var tempMidString = "";
            for (var j=0; j < midStringLen; j++) { tempMidString += midStrings[i]; }
            if (fromString.indexOf(tempMidString) == -1) {
               midString = tempMidString;
               i = midStrings.length + 1;
            }
         }
      } // Keep on going until we build an "inbetween" string that doesn't exist
      // Now go through and do two replaces - first, replace the "fromString" with the "inbetween" string
      while (temp.indexOf(fromString) != -1) {
         var toTheLeft = temp.substring(0, temp.indexOf(fromString));
         var toTheRight = temp.substring(temp.indexOf(fromString)+fromString.length, temp.length);
         temp = toTheLeft + midString + toTheRight;
      }
      // Next, replace the "inbetween" string with the "toString"
      while (temp.indexOf(midString) != -1) {
         var toTheLeft = temp.substring(0, temp.indexOf(midString));
         var toTheRight = temp.substring(temp.indexOf(midString)+midString.length, temp.length);
         temp = toTheLeft + toString + toTheRight;
      }
   } 
   return temp; 
} 