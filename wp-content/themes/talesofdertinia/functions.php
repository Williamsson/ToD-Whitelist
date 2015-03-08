<?php

add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );
function theme_enqueue_styles() {
	
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'custom', get_stylesheet_directory_uri() . '/zebra-dialog/default/zebra_dialog.css' );
    wp_enqueue_script( 'zebra_dialog', get_stylesheet_directory_uri() . '/zebra-dialog/zebra_dialog.js', array(), '1.0.0', true );
    wp_enqueue_script( 'script', get_stylesheet_directory_uri() . '/js/script.js', array(), '1.0.0', true );
}

update_option('siteurl','http://localhost/tod/');
update_option('home','http://localhost/tod/');