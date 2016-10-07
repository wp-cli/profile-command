<?php

if ( class_exists( 'WP_CLI' ) ) {
	require_once dirname( __FILE__ ) . '/inc/class-command.php';
	require_once dirname( __FILE__ ) . '/inc/class-formatter.php';
	require_once dirname( __FILE__ ) . '/inc/class-logger.php';
	WP_CLI::add_command( 'profile stage', array( 'runcommand\Profile\Command', 'stage' ) );
	WP_CLI::add_command( 'profile hook', array( 'runcommand\Profile\Command', 'hook' ) );
	WP_CLI::add_command( 'profile eval', array( 'runcommand\Profile\Command', 'eval_' ) );
	WP_CLI::add_command( 'profile eval-file', array( 'runcommand\Profile\Command', 'eval_file' ) );
}
