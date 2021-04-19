<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$wpcli_profile_autoloader = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $wpcli_profile_autoloader ) ) {
	require_once $wpcli_profile_autoloader;
}

WP_CLI::add_command( 'profile', 'WP_CLI\Profile\Command' );
