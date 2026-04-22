<?php

namespace WP_CLI\Profile;

use WP_CLI;
use WP_CLI\Utils;

/**
 * Quickly identify what's slow with WordPress.
 *
 * ## EXAMPLES
 *
 *     # See an overview for each stage of the load process.
 *     $ wp profile stage --fields=stage,time,cache_ratio
 *     +------------+---------+-------------+
 *     | stage      | time    | cache_ratio |
 *     +------------+---------+-------------+
 *     | bootstrap  | 0.7994s | 93.21%      |
 *     | main_query | 0.0123s | 94.29%      |
 *     | template   | 0.792s  | 91.23%      |
 *     +------------+---------+-------------+
 *     | total (3)  | 1.6037s | 92.91%      |
 *     +------------+---------+-------------+
 *
 *     # Dive into hook performance for a given stage.
 *     $ wp profile stage bootstrap --fields=hook,time,cache_ratio --spotlight
 *     +--------------------------+---------+-------------+
 *     | hook                     | time    | cache_ratio |
 *     +--------------------------+---------+-------------+
 *     | muplugins_loaded:before  | 0.1767s | 33.33%      |
 *     | plugins_loaded:before    | 0.103s  | 78.13%      |
 *     | plugins_loaded           | 0.0194s | 19.32%      |
 *     | setup_theme              | 0.0018s | 75%         |
 *     | after_setup_theme:before | 0.0116s | 95.45%      |
 *     | after_setup_theme        | 0.0049s | 96%         |
 *     | init                     | 0.1428s | 76.74%      |
 *     | wp_loaded:after          | 0.0236s |             |
 *     +--------------------------+---------+-------------+
 *     | total (8)                | 0.4837s | 67.71%      |
 *     +--------------------------+---------+-------------+
 *
 * @package wp-cli
 */
class Command {

	/**
	 * Profile each stage of the WordPress load process (bootstrap, main_query, template).
	 *
	 * When WordPress handles a request from a browser, it’s essentially
	 * executing as one long PHP script. `wp profile stage` breaks the script
	 * into three stages:
	 *
	 * * **bootstrap** is where WordPress is setting itself up, loading plugins
	 * and the main theme, and firing the `init` hook.
	 * * **main_query** is how WordPress transforms the request (e.g. `/2016/10/21/moms-birthday/`)
	 * into the primary WP_Query.
	 * * **template** is where WordPress determines which theme template to
	 * render based on the main query, and renders it.
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
	 * [--order=<order>]
	 * : Ascending or Descending order.
	 * ---
	 * default: ASC
	 * options:
	 *   - ASC
	 *   - DESC
	 * ---
	 *
	 * [--orderby=<fields>]
	 * : Set orderby which field.
	 *
	 * ## EXAMPLES
	 *
	 *     # See an overview for each stage of the load process.
	 *     $ wp profile stage --fields=stage,time,cache_ratio
	 *     +------------+---------+-------------+
	 *     | stage      | time    | cache_ratio |
	 *     +------------+---------+-------------+
	 *     | bootstrap  | 0.7994s | 93.21%      |
	 *     | main_query | 0.0123s | 94.29%      |
	 *     | template   | 0.792s  | 91.23%      |
	 *     +------------+---------+-------------+
	 *     | total (3)  | 1.6037s | 92.91%      |
	 *     +------------+---------+-------------+
	 *
	 *     # Dive into hook performance for a given stage.
	 *     $ wp profile stage bootstrap --fields=hook,time,cache_ratio --spotlight
	 *     +--------------------------+---------+-------------+
	 *     | hook                     | time    | cache_ratio |
	 *     +--------------------------+---------+-------------+
	 *     | muplugins_loaded:before  | 0.2335s | 40%         |
	 *     | muplugins_loaded         | 0.0007s | 50%         |
	 *     | plugins_loaded:before    | 0.2792s | 77.63%      |
	 *     | plugins_loaded           | 0.1502s | 100%        |
	 *     | after_setup_theme:before | 0.068s  | 100%        |
	 *     | init                     | 0.2643s | 96.88%      |
	 *     | wp_loaded:after          | 0.0377s |             |
	 *     +--------------------------+---------+-------------+
	 *     | total (7)                | 1.0335s | 77.42%      |
	 *     +--------------------------+---------+-------------+
	 *
	 * @skipglobalargcheck
	 * @when before_wp_load
	 *
	 * @param array{0?: string} $args Positional arguments.
	 * @param array{all?: bool, spotlight?: bool, url?: string, fields?: string, format: string, order: string, orderby?: string} $assoc_args Associative arguments.
	 * @return void
	 */
	public function stage( $args, $assoc_args ) {
		global $wpdb;

		$focus = Utils\get_flag_value( $assoc_args, 'all', isset( $args[0] ) ? $args[0] : null );

		$order_val   = Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
		$order       = is_string( $order_val ) ? $order_val : 'ASC';
		$orderby_val = Utils\get_flag_value( $assoc_args, 'orderby', null );
		$orderby     = ( is_string( $orderby_val ) || is_null( $orderby_val ) ) ? $orderby_val : null;

		$valid_stages = array( 'bootstrap', 'main_query', 'template' );
		if ( $focus && ( true !== $focus && ! in_array( $focus, $valid_stages, true ) ) ) {
			WP_CLI::error( 'Invalid stage. Must be one of ' . implode( ', ', $valid_stages ) . ', or use --all.' );
		}

		$profiler = new Profiler( 'stage', $focus );
		$profiler->run();

		if ( $focus ) {
			$base    = array(
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
			$base    = array(
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
		$fields    = array_merge( $base, $metrics );
		$formatter = new Formatter( $assoc_args, $fields );
		$loggers   = $profiler->get_loggers();
		/** @var array<string, bool|string> $assoc_args */
		if ( Utils\get_flag_value( $assoc_args, 'spotlight' ) ) {
			$loggers = self::shine_spotlight( $loggers, $metrics );
		}

		$formatter->display_items( $loggers, true, $order, $orderby );
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
	 * [--order=<order>]
	 * : Ascending or Descending order.
	 * ---
	 * default: ASC
	 * options:
	 *   - ASC
	 *   - DESC
	 * ---
	 *
	 * [--orderby=<fields>]
	 * : Set orderby which field.
	 *
	 * [--search=<pattern>]
	 * : Filter callbacks to those matching the given search pattern (case-insensitive).
	 *
	 * ## EXAMPLES
	 *
	 *     # Profile a hook.
	 *     $ wp profile hook template_redirect --fields=callback,cache_hits,cache_misses
	 *     +--------------------------------+------------+--------------+
	 *     | callback                       | cache_hits | cache_misses |
	 *     +--------------------------------+------------+--------------+
	 *     | _wp_admin_bar_init()           | 0          | 0            |
	 *     | wp_old_slug_redirect()         | 0          | 0            |
	 *     | redirect_canonical()           | 5          | 0            |
	 *     | WP_Sitemaps->render_sitemaps() | 0          | 0            |
	 *     | rest_output_link_header()      | 3          | 0            |
	 *     | wp_shortlink_header()          | 0          | 0            |
	 *     | wp_redirect_admin_locations()  | 0          | 0            |
	 *     +--------------------------------+------------+--------------+
	 *     | total (7)                      | 8          | 0            |
	 *     +--------------------------------+------------+--------------+
	 *
	 * @skipglobalargcheck
	 * @when before_wp_load
	 *
	 * @param array{0?: string} $args Positional arguments.
	 * @param array{all?: bool, spotlight?: bool, url?: string, fields?: string, format: string, order: string, orderby?: string} $assoc_args
	 * @return void
	 */
	public function hook( $args, $assoc_args ) {

		$focus = Utils\get_flag_value( $assoc_args, 'all', isset( $args[0] ) ? $args[0] : null );

		$order_val   = Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
		$order       = is_string( $order_val ) ? $order_val : 'ASC';
		$orderby_val = Utils\get_flag_value( $assoc_args, 'orderby', null );
		$orderby     = ( is_string( $orderby_val ) || is_null( $orderby_val ) ) ? $orderby_val : null;

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
		$metrics   = array(
			'time',
			'query_time',
			'query_count',
			'cache_ratio',
			'cache_hits',
			'cache_misses',
			'request_time',
			'request_count',
		);
		$fields    = array_merge( $base, $metrics );
		$formatter = new Formatter( $assoc_args, $fields );
		$loggers   = $profiler->get_loggers();
		/** @var array<string, bool|string> $assoc_args */
		if ( Utils\get_flag_value( $assoc_args, 'spotlight' ) ) {
			$loggers = self::shine_spotlight( $loggers, $metrics );
		}
		/** @var array<string, bool|string> $assoc_args */
		$search_val = Utils\get_flag_value( $assoc_args, 'search', '' );
		$search     = is_string( $search_val ) ? $search_val : '';
		if ( '' !== $search ) {
			if ( ! $focus ) {
				WP_CLI::error( '--search requires --all or a specific hook.' );
			}
			$loggers = self::filter_by_callback( $loggers, $search );
		}
		$formatter->display_items( $loggers, true, $order, $orderby );
	}

	/**
	 * Profile HTTP requests made during the WordPress load process.
	 *
	 * Monitors all HTTP requests made during the WordPress load process,
	 * displaying information about each request including URL, method,
	 * execution time, and response code.
	 *
	 * ## OPTIONS
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
	 * [--order=<order>]
	 * : Ascending or Descending order.
	 * ---
	 * default: ASC
	 * options:
	 *   - ASC
	 *   - DESC
	 * ---
	 *
	 * [--orderby=<fields>]
	 * : Set orderby which field.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all HTTP requests during page load
	 *     $ wp profile requests
	 *     +-----------+----------------------------+----------+---------+
	 *     | method    | url                        | status   | time    |
	 *     +-----------+----------------------------+----------+---------+
	 *     | GET       | https://api.example.com    | 200      | 0.2341s |
	 *     | POST      | https://api.example.com    | 201      | 0.1653s |
	 *     +-----------+----------------------------+----------+---------+
	 *     | total (2) |                            |          | 0.3994s |
	 *     +-----------+----------------------------+----------+---------+
	 * @skipglobalargcheck
	 * @when before_wp_load
	 *
	 * @param array<string> $args Positional arguments. Unused.
	 * @param array{url?: string, fields?: string, format: string, order: string, orderby?: string} $assoc_args Associative arguments.
	 * @return void
	 */
	public function requests( $args, $assoc_args ) {
		$order   = Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
		$orderby = Utils\get_flag_value( $assoc_args, 'orderby', null );

		$profiler = new Profiler( 'request', false );
		$profiler->run();

		$fields    = array(
			'method',
			'url',
			'status',
			'time',
		);
		$formatter = new Formatter( $assoc_args, $fields );
		$loggers   = $profiler->get_loggers();

		$formatter->display_items( $loggers, true, $order, $orderby );
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
	 * [--hook[=<hook>]]
	 * : Focus on key metrics for all hooks, or callbacks on a specific hook.
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
	 * [--order=<order>]
	 * : Ascending or Descending order.
	 * ---
	 * default: ASC
	 * options:
	 *   - ASC
	 *   - DESC
	 * ---
	 *
	 * [--orderby=<fields>]
	 * : Set orderby which field.
	 *
	 * ## EXAMPLES
	 *
	 *     # Profile a function that makes one HTTP request.
	 *     $ wp profile eval 'wp_remote_get( "https://www.apple.com/" );' --fields=time,cache_ratio,request_count
	 *     +---------+-------------+---------------+
	 *     | time    | cache_ratio | request_count |
	 *     +---------+-------------+---------------+
	 *     | 0.1009s | 100%        | 1             |
	 *     +---------+-------------+---------------+
	 *
	 * @param array{0: string} $args Positional arguments.
	 * @param array{hook?: bool|string, fields: string, format: string, order: string, orderby?: string} $assoc_args Associative arguments.
	 * @return void
	 *
	 * @subcommand eval
	 */
	public function eval_( $args, $assoc_args ) {
		$statement = $args[0];

		$order_val   = Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
		$order       = is_string( $order_val ) ? $order_val : 'ASC';
		$orderby_val = Utils\get_flag_value( $assoc_args, 'orderby', null );
		$orderby     = ( is_string( $orderby_val ) || is_null( $orderby_val ) ) ? $orderby_val : null;

		self::profile_eval_ish(
			$assoc_args,
			function () use ( $statement ) {
				eval( $statement ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- no other way around here
			},
			$order,
			$orderby
		);
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
	 * [--hook[=<hook>]]
	 * : Focus on key metrics for all hooks, or callbacks on a specific hook.
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
	 * [--order=<order>]
	 * : Ascending or Descending order.
	 * ---
	 * default: ASC
	 * options:
	 *   - ASC
	 *   - DESC
	 * ---
	 *
	 * [--orderby=<fields>]
	 * : Set orderby which field.
	 *
	 * ## EXAMPLES
	 *
	 *     # Profile from a file `request.php` containing `<?php wp_remote_get( "https://www.apple.com/" );`.
	 *     $ wp profile eval-file request.php --fields=time,cache_ratio,request_count
	 *     +---------+-------------+---------------+
	 *     | time    | cache_ratio | request_count |
	 *     +---------+-------------+---------------+
	 *     | 0.1009s | 100%        | 1             |
	 *     +---------+-------------+---------------+
	 *
	 * @param array{0: string} $args Positional arguments.
	 * @param array{hook?: string|bool, fields?: string, format: string, order: string, orderby?: string} $assoc_args Associative arguments.
	 * @return void
	 *
	 * @subcommand eval-file
	 */
	public function eval_file( $args, $assoc_args ) {

		$file = $args[0];

		$order_val   = Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
		$order       = is_string( $order_val ) ? $order_val : 'ASC';
		$orderby_val = Utils\get_flag_value( $assoc_args, 'orderby', null );
		$orderby     = ( is_string( $orderby_val ) || is_null( $orderby_val ) ) ? $orderby_val : null;

		if ( ! file_exists( $file ) ) {
			WP_CLI::error( "'$file' does not exist." );
		}

		self::profile_eval_ish(
			$assoc_args,
			function () use ( $file ) {
				self::include_file( $file );
			},
			$order,
			$orderby
		);
	}

	/**
	 * Profile an eval or eval-file statement.
	 *
	 * @param array{hook?: string|bool} $assoc_args
	 * @param callable                  $profile_callback
	 * @param string                    $order
	 * @param string|null               $orderby
	 * @return void
	 */
	private static function profile_eval_ish( $assoc_args, $profile_callback, $order = 'ASC', $orderby = null ) {
		$hook   = Utils\get_flag_value( $assoc_args, 'hook' );
		$focus  = false;
		$type   = false;
		$fields = array();
		if ( $hook ) {
			$type = 'hook';
			if ( true !== $hook ) {
				$focus    = $hook;
				$fields[] = 'callback';
				$fields[] = 'location';
			} else {
				$fields[] = 'hook';
			}
		}
		$profiler = new Profiler( $type, $focus );
		$profiler->run();
		if ( $hook ) {
			$profile_callback();
			$loggers = $profiler->get_loggers();
		} else {
			$logger = new Logger();
			$logger->start();
			$profile_callback();
			$logger->stop();
			$loggers = array( $logger );
		}
		$fields    = array_merge(
			$fields,
			array(
				'time',
				'query_time',
				'query_count',
				'cache_ratio',
				'cache_hits',
				'cache_misses',
				'request_time',
				'request_count',
			)
		);
		$formatter = new Formatter( $assoc_args, $fields );
		$formatter->display_items( $loggers, false, $order, $orderby );
	}

	/**
	 * Include a file without exposing it to current scope
	 *
	 * @param string $file
	 * @return void
	 */
	private static function include_file( $file ) {
		include $file;
	}

	/**
	 * Profile database queries and their execution time.
	 *
	 * Displays all database queries executed during a WordPress request,
	 * along with their execution time and caller information. You can filter
	 * queries to only show those executed during a specific hook or by a
	 * specific callback.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : Execute a request against a specified URL. Defaults to the home URL.
	 *
	 * [--hook=<hook>]
	 * : Filter queries to only show those executed during a specific hook.
	 *
	 * [--callback=<callback>]
	 * : Filter queries to only show those executed by a specific callback.
	 *
	 * [--time_threshold=<seconds>]
	 * : Filter queries to only show those that took longer than or equal to a certain number of seconds.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
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
	 * [--order=<order>]
	 * : Ascending or Descending order.
	 * ---
	 * default: ASC
	 * options:
	 *   - ASC
	 *   - DESC
	 * ---
	 *
	 * [--orderby=<fields>]
	 * : Set orderby which field.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show all queries with their execution time
	 *     $ wp profile queries --fields=query,time
	 *
	 *     # Show queries executed during the 'init' hook
	 *     $ wp profile queries --hook=init --fields=query,time,caller
	 *
	 *     # Show queries executed by a specific callback
	 *     $ wp profile queries --callback='WP_Query->get_posts()' --fields=query,time
	 *
	 *     # Show queries ordered by execution time
	 *     $ wp profile queries --fields=query,time --orderby=time --order=DESC
	 *
	 * @skipglobalargcheck
	 * @when before_wp_load
	 *
	 * @param array<string> $args Positional arguments. Unused
	 * @param array{url?: string, hook?: string, callback?: string, time_threshold?: string, fields?: string, format: string, order: string, orderby: string}  $assoc_args Associative arguments.
	 * @return void
	 */
	public function queries( $args, $assoc_args ) {
		global $wpdb;

		$hook           = Utils\get_flag_value( $assoc_args, 'hook' );
		$callback       = Utils\get_flag_value( $assoc_args, 'callback' );
		$time_threshold = Utils\get_flag_value( $assoc_args, 'time_threshold' );
		$order          = Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
		$orderby        = Utils\get_flag_value( $assoc_args, 'orderby', null );

		// Set up profiler to track hooks and callbacks
		$type  = false;
		$focus = null;
		if ( $hook && $callback ) {
			// When both are provided, profile all hooks to find the specific callback
			$type  = 'hook';
			$focus = true;
		} elseif ( $hook ) {
			$type  = 'hook';
			$focus = $hook;
		} elseif ( $callback ) {
			$type  = 'hook';
			$focus = true; // Profile all hooks to find the specific callback
		}

		$profiler = new Profiler( $type, $focus );
		$profiler->run();

		// Build a map of query indices to hooks/callbacks
		// This is O(N*Q + M) where N=loggers, Q=queries per logger, M=total queries
		// For typical WordPress sites, this performs well with the array-based lookups
		$query_map = array();
		if ( $hook || $callback ) {
			$loggers = $profiler->get_loggers();
			foreach ( $loggers as $logger ) {
				// Skip if filtering by callback and this logger doesn't have a callback
				if ( $callback && ! isset( $logger->callback ) ) {
					continue;
				}

				// Skip if filtering by callback and this isn't the right one
				if ( $callback && isset( $logger->callback ) ) {
					// Normalize callback for comparison
					$normalized_callback = trim( (string) $logger->callback );
					$normalized_filter   = trim( $callback );
					if ( false === stripos( $normalized_callback, $normalized_filter ) ) {
						continue;
					}
				}

				// Skip if filtering for a specific hook and this isn't the right one
				if ( $hook && isset( $logger->hook ) && $logger->hook !== $hook ) {
					continue;
				}

				// Skip if filtering for a specific hook and the logger has no hook property
				if ( $hook && ! isset( $logger->hook ) ) {
					continue;
				}

				// Get the query indices for this logger
				if ( ! empty( $logger->query_indices ) ) {
					foreach ( $logger->query_indices as $query_index ) {
						// Use last-logger-wins to get the most specific hook/callback
						$query_map[ $query_index ] = array(
							'hook'     => isset( $logger->hook ) ? $logger->hook : null,
							'callback' => isset( $logger->callback ) ? $logger->callback : null,
						);
					}
				}
			}
		}

		// Get all queries
		$queries = array();
		if ( ! empty( $wpdb->queries ) ) {
			foreach ( $wpdb->queries as $index => $query_data ) {
				// If filtering by hook/callback, only include queries in the map
				if ( ( $hook || $callback ) && ! isset( $query_map[ $index ] ) ) {
					continue;
				}

				$query_time = $query_data[1];
				if ( null !== $time_threshold && $query_time < (float) $time_threshold ) {
					continue;
				}

				$caller = isset( $query_data[2] ) ? $query_data[2] : '';

				// Exclude WP-CLI frames up to load_wordpress_with_template
				$marker = 'WP_CLI\Profile\Profiler->load_wordpress_with_template';
				$pos    = strpos( $caller, $marker );
				if ( false !== $pos ) {
					$caller = substr( $caller, $pos + strlen( $marker ) );
					if ( 0 === strpos( $caller, '()' ) ) {
						$caller = substr( $caller, 2 );
					}
					$caller = ltrim( $caller, ', ' );
				}

				$caller = str_replace( ', ', "\n", $caller );

				$query_obj = new QueryLogger(
					$query_data[0], // SQL query
					$query_time, // Time
					$caller, // Caller
					isset( $query_map[ $index ]['hook'] ) ? $query_map[ $index ]['hook'] : null,
					isset( $query_map[ $index ]['callback'] ) ? $query_map[ $index ]['callback'] : null
				);
				$queries[] = $query_obj;
			}
		}

		// Set up fields for output
		$fields = array( 'query', 'time', 'caller' );
		if ( $hook && ! $callback ) {
			$fields = array( 'query', 'time', 'callback', 'caller' );
		} elseif ( $callback && ! $hook ) {
			$fields = array( 'query', 'time', 'hook', 'caller' );
		} elseif ( $hook && $callback ) {
			$fields = array( 'query', 'time', 'hook', 'callback', 'caller' );
		}

		$formatter = new Formatter( $assoc_args, $fields );
		$formatter->display_items( $queries, true, $order, $orderby );
	}

	/**
	 * Filter loggers with zero-ish values.
	 *
	 * @param array<\WP_CLI\Profile\Logger> $loggers
	 * @param array<string>                 $metrics
	 * @return array<\WP_CLI\Profile\Logger>
	 */
	private static function shine_spotlight( $loggers, $metrics ) {

		foreach ( $loggers as $k => $logger ) {
			$non_zero = false;
			foreach ( $metrics as $metric ) {
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

	/**
	 * Filter loggers to only those whose callback name matches a pattern.
	 *
	 * @param array<\WP_CLI\Profile\Logger> $loggers
	 * @param string                        $pattern
	 * @return array<\WP_CLI\Profile\Logger>
	 */
	private static function filter_by_callback( $loggers, $pattern ) {
		return array_filter(
			$loggers,
			function ( $logger ) use ( $pattern ) {
				return isset( $logger->callback ) && false !== stripos( $logger->callback, $pattern );
			}
		);
	}
}
