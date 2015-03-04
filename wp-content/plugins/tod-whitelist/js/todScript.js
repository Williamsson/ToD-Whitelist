jQuery(document).ready(function(){
		//Get a hold of my variables.. Precious variables..
		var logTable = jQuery('#logsTable').DataTable({
	        "order": [[ 0, "desc" ]]
	    } );
		var userTable = jQuery('#usersTable').DataTable({
	        "order": [[ 0, "desc" ]]
	    } );
		var usernameSearchField = jQuery('#tod-whitelist-search');
		var usernameSearchClear = jQuery('#tod-whitelist-clear');
		var usernameSearchSubmit = jQuery('#tod-whitelist-search-submit');
		var uuidLink = jQuery(".uuidProfileLink");
		var authKey = readCookie('apiAuthKey');
		var username = "";
		//Start doing stuff
		
		//when someone searches for a minecraft username
		usernameSearchSubmit.click(function(){
			username = usernameSearchField.val();
			jQuery.ajax({
				url: "/wp-content/plugins/tod-whitelist/api/api.php?auth=" + authKey +"&request=getUUID&username=" + username,
				type: 'GET'
			})
			.success(function( data ) {
				var uuid = JSON.parse(data).uuid;
				if(uuid){
					userTable.search(uuid).draw();
				}else{
					alert("That is not a valid minecraft username.");
				}
			});
			
		});
		//clear search
		usernameSearchClear.click(function(){
			usernameSearchField.val('');
			jQuery(".dataTables_filter label input").val('');
			userTable.search('').draw();
	    });
		
		uuidLink.click(function(e){
			e.preventDefault();
			jQuery("body").addClass("loading");
			var uuid = jQuery(this).html();
			jQuery.ajax({
				url: "/wp-content/plugins/tod-whitelist/api/api.php?auth=" + authKey +"&request=checkBans&uuid=" + uuid,
				type: 'GET'
			})
			.success(function( data ) {
				var data = JSON.parse(data);
				jQuery("body").removeClass("loading");
				if(data.success){
					var services = data.bans.service;
					var username = data.bans.username;
					var html = '<table>';
							html += '<thead>';
								html += '<tr>';
								html += '<th>Service</th>';
								html += '<th>Ban count:</th>';
								html += '<th>Reasons:</th>';
							html += '</thead>';
							html += '<tbody>';
								Object.keys(services).forEach(function(service) {
									html += '<tr>';
										html += '<td>' + service + '</td>';
										html += '<td>' + services[service].bans + '</td>';
										var object = services[service].ban_info;
										html += '<td>';
										for (var key in object) {
											  if (object.hasOwnProperty(key)) {
											    html += object[key];
											  }
											}   
										html += '</td>';
									html += '</tr>';
								});

								html += '</tr>';
							html += '</tbody>';
					jQuery.Zebra_Dialog("<h2>" + username +" ban history</h2><br/>" + html, {
					    'type':     'information',
					    'title':    'User information'
					});
//					console.log(bans);
				}else{
					alert("No such user found");
				}
//				console.log(bans);
			});

		});
		
		
});

function readCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}