<?php

namespace runcommand\Profile;

use WP_CLI;
use WP_CLI\Utils;

class Command {

	/**
	 * Profile each stage of the WordPress load process (bootstrap, main_query, template).
	 *
	 * ## OPTIONS
	 *
	 * [<stage>]
	 * : Drill down into a specific stage.
	 *
	 * [--all]
	 * : Expand upon all stages.
	 *
	 * [--url=<url>]
	 * : Execute a request against a specified URL. Defaults to the home URL.
	 *
	 * [--fields=<fields>]
	 * : Display one or more fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 *   - csv
	 * ---
	 *
	 * @when before_wp_load
	 */
	public function stage( $args, $assoc_args ) {
		global $wpdb;

		$focus = Utils\get_flag_value( $assoc_args, 'all', isset( $args[0] ) ? $args[0] : null );

		$valid_stages = array( 'bootstrap', 'main_query', 'template' );
		if ( $focus && ( true !== $focus && ! in_array( $focus, $valid_stages, true ) ) ) {
			WP_CLI::error( 'Invalid stage. Must be one of ' . implode( ', ', $valid_stages ) . ', or use --all.' );
		}

		$profiler = new Profiler( 'stage', $focus );
		$profiler->run();

		if ( $focus ) {
			$fields = array(
				'hook',
				'callback_count',
				'time',
				'query_time',
				'query_count',
				'cache_ratio',
				'cache_hits',
				'cache_misses',
				'request_time',
				'request_count',
			);
		} else {
			$fields = array(
				'stage',
				'time',
				'query_time',
				'query_count',
				'cache_ratio',
				'cache_hits',
				'cache_misses',
				'hook_time',
				'hook_count',
				'request_time',
				'request_count',
			);
		}
		$formatter = new Formatter( $assoc_args, $fields );
		$formatter->display_items( $profiler->get_loggers() );
	}

	/**
	 * Profile key metrics for WordPress hooks (actions and filters).
	 *
	 * ## OPTIONS
	 *
	 * [<hook>]
	 * : Drill into key metrics for a specific WordPress hook (action or filter).
	 *
	 * [--url=<url>]
	 * : Execute a request against a specified URL. Defaults to the home URL.
	 *
	 * [--fields=<fields>]
	 * : Display one or more fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 *   - csv
	 * ---
	 *
	 * @when before_wp_load
	 */
	public function hook( $args, $assoc_args ) {

		$focus = isset( $args[0] ) ? $args[0] : null;

		$profiler = new Profiler( 'hook', $focus );
		$profiler->run();

		// 'shutdown' won't actually fire until script completion
		// but we can mock it
		if ( 'shutdown' === $focus ) {
			do_action( 'shutdown' );
			remove_all_actions( 'shutdown' );
		}

		if ( $focus ) {
			$base = array( 'callback', 'location' );
		} else {
			$base = array( 'hook', 'callback_count' );
		}
		$metrics = array(
			'time',
			'query_time',
			'query_count',
			'cache_ratio',
			'cache_hits',
			'cache_misses',
			'request_time',
			'request_count',
		);
		$fields = array_merge( $base, $metrics );
		$formatter = new Formatter( $assoc_args, $fields );
		$formatter->display_items( $profiler->get_loggers() );
	}

	/**
	 * Profile arbitrary code execution.
	 *
	 * Code execution happens after WordPress has loaded entirely, which means
	 * you can use any utilities defined in WordPress, active plugins, or the
	 * current theme.
	 *
	 * ## OPTIONS
	 *
	 * <php-code>
	 * : The code to execute, as a string.
	 *
	 * [--fields=<fields>]
	 * : Display one or more fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 *   - csv
	 * ---
	 *
	 * @when before_wp_load
	 * @subcommand eval
	 */
	public function eval_( $args, $assoc_args ) {

		$profiler = new Profiler( false, false );
		$profiler->run();

		$logger = new Logger();
		$logger->start();
		eval( $args[0] );
		$logger->stop();

		$fields = array(
			'time',
			'query_time',
			'query_count',
			'cache_ratio',
			'cache_hits',
			'cache_misses',
			'request_time',
			'request_count',
		);
		$formatter = new Formatter( $assoc_args, $fields );
		$formatter->display_items( array( $logger ) );
	}

	/**
	 * Profile execution of an arbitrary file.
	 *
	 * File execution happens after WordPress has loaded entirely, which means
	 * you can use any utilities defined in WordPress, active plugins, or the
	 * current theme.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The path to the PHP file to execute and profile.
	 *
	 * [--fields=<fields>]
	 * : Display one or more fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 *   - csv
	 * ---
	 *
	 * @when before_wp_load
	 * @subcommand eval-file
	 */
	public function eval_file( $args, $assoc_args ) {

		$file = $args[0];
		if ( ! file_exists( $file ) ) {
			WP_CLI::error( "'$file' does not exist." );
		}

		$profiler = new Profiler( false, false );
		$profiler->run();

		$logger = new Logger();
		$logger->start();
		self::include_file( $file );
		$logger->stop();

		$fields = array(
			'time',
			'query_time',
			'query_count',
			'cache_ratio',
			'cache_hits',
			'cache_misses',
			'request_time',
			'request_count',
		);
		$formatter = new Formatter( $assoc_args, $fields );
		$formatter->display_items( array( $logger ) );
	}

	/**
	 * Include a file without exposing it to current scope
	 *
	 * @param string $file
	 */
	private static function include_file( $file ) {
		include( $file );
	}

}
