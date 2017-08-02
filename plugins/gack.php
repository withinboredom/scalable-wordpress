<?php
/*
Plugin Name: Google Analytics: I can read
Plugin URI:  https://developer.wordpress.org/plugins/the-basics/
Description: Tells Google Analytics when a post has been read...
Version:     0.0.1
Author:      withinboredom
Author URI:  https://www.withinboredom.info/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: gack
Domain Path: /languages
*/

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_script( 'gack', "/wp-content/gack/gack.js", [], false, true );
} );
