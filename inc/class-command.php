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
	 * [--spotlight]
	 * : Filter out logs with zero-ish values from the set.
	 *
	 * [--url=<url>]
	 * : Execute a request against a specified URL. Defaults to the home URL.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Default is all fields.
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
			$base = array(
				'hook',
				'callback_count',
			);
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
		} else {
			$base = array(
				'stage',
			);
			$metrics = array(
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
		$fields = array_merge( $base, $metrics );
		$formatter = new Formatter( $assoc_args, $fields );
		$loggers = $profiler->get_loggers();
		if ( Utils\get_flag_value( $assoc_args, 'spotlight' ) ) {
			$loggers = self::shine_spotlight( $loggers, $metrics );
		}
		$formatter->display_items( $loggers );
	}

	/**
	 * Profile key metrics for WordPress hooks (actions and filters).
	 *
	 * In order to profile callbacks on a specific hook, the action or filter
	 * will need to execute during the course of the request.
	 *
	 * ## OPTIONS
	 *
	 * [<hook>]
	 * : Drill into key metrics of callbacks on a specific WordPress hook.
	 *
	 * [--all]
	 * : Profile callbacks for all WordPress hooks.
	 *
	 * [--spotlight]
	 * : Filter out logs with zero-ish values from the set.
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

		$focus = Utils\get_flag_value( $assoc_args, 'all', isset( $args[0] ) ? $args[0] : null );

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
		$loggers = $profiler->get_loggers();
		if ( Utils\get_flag_value( $assoc_args, 'spotlight' ) ) {
			$loggers = self::shine_spotlight( $loggers, $metrics );
		}
		$formatter->display_items( $loggers );
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

	/**
	 * Filter loggers with zero-ish values.
	 *
	 * @param array $loggers
	 * @param array $metrics
	 * @return array
	 */
	private static function shine_spotlight( $loggers, $metrics ) {

		foreach( $loggers as $k => $logger ) {
			$non_zero = false;
			foreach( $metrics as $metric ) {
				switch ( $metric ) {
					// 100% cache ratio is fine by us
					case 'cache_ratio':
					case 'cache_hits':
					case 'cache_misses':
						if ( $logger->cache_ratio && '100%' !== $logger->cache_ratio ) {
							$non_zero = true;
						}
						break;
					case 'time':
					case 'query_time':
						if ( $logger->$metric > 0.01 ) {
							$non_zero = true;
						}
						break;
					default:
						if ( $logger->$metric ) {
							$non_zero = true;
						}
						break;
				}
			}
			if ( ! $non_zero ) {
				unset( $loggers[ $k ] );
			}
		}

		return $loggers;
	}

}
