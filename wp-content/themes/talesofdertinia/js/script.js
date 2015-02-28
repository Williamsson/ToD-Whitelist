jQuery(document).ready(function(){
	//Box explaining why we want email
	var whyEmail = jQuery(".sidebar.s2 .sidebar-content .widget_todwhitelist form a#whitelist_email_zebra_link");
	jQuery( whyEmail ).click(function(e) {
		e.preventDefault();
		
		new jQuery.Zebra_Dialog("In order to be whitelisted you'll get an email confirmation. When you click the link in the email you're automatically added to the whitelist. This is to root out the trolls and to automatically give you an account on the website. We will not send you spam emails and we will not sell your email to companies that do.", {
			'buttons':  false,
			'modal': false,
			'position': ['right - 20', 'middle'],
			'auto_close': 10000
		});
		
	});
	//Box explaining why we don't care about their previous experience
	var whyImportant = jQuery(".sidebar.s2 .sidebar-content .widget_todwhitelist form a#whitelist_why_zebra_link");
	jQuery( whyImportant ).click(function(e) {
		e.preventDefault();
		
		new jQuery.Zebra_Dialog("<strong>It's not!</strong> It's just convenient for us when we're looking for new staff members. Feel free to ignore these options.", {
			'buttons':  false,
			'modal': false,
			'position': ['right - 20', 'middle'],
			'auto_close': 7000
		});
		
	});
});