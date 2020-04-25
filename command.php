<?php

if ( class_exists( 'WP_CLI' ) ) {
	require_once dirname( __FILE__ ) . '/inc/class-command.php';
	require_once dirname( __FILE__ ) . '/inc/class-formatter.php';
	require_once dirname( __FILE__ ) . '/inc/class-logger.php';
	require_once dirname( __FILE__ ) . '/inc/class-profiler.php';
	if ( version_compare( PHP_VERSION, '7.0.0' ) >= 0 ) {
		require_once dirname( __FILE__ ) . '/inc/class-filestreamwrapper.php';
	}
	WP_CLI::add_command( 'profile', 'runcommand\Profile\Command' );
}
