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
	 * @when before_wp_load
	 */
	public function stage( $args, $assoc_args ) {
		global $wpdb;

		$focus = Utils\get_flag_value( $assoc_args, 'all', isset( $args[0] ) ? $args[0] : null );

		$order   = Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
		$orderby = Utils\get_flag_value( $assoc_args, 'orderby', null );

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
	 * @when before_wp_load
	 */
	public function hook( $args, $assoc_args ) {

		$focus = Utils\get_flag_value( $assoc_args, 'all', isset( $args[0] ) ? $args[0] : null );

		$order   = Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
		$orderby = Utils\get_flag_value( $assoc_args, 'orderby', null );

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
		if ( Utils\get_flag_value( $assoc_args, 'spotlight' ) ) {
			$loggers = self::shine_spotlight( $loggers, $metrics );
		}
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
	 * @subcommand eval
	 */
	public function eval_( $args, $assoc_args ) {
		$statement = $args[0];

		$order   = Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
		$orderby = Utils\get_flag_value( $assoc_args, 'orderby', null );

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
	 * @subcommand eval-file
	 */
	public function eval_file( $args, $assoc_args ) {

		$file = $args[0];

		$order   = Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
		$orderby = Utils\get_flag_value( $assoc_args, 'orderby', null );

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
	 * @when before_wp_load
	 */
	public function queries( $args, $assoc_args ) {
		global $wpdb;

		$hook     = Utils\get_flag_value( $assoc_args, 'hook' );
		$callback = Utils\get_flag_value( $assoc_args, 'callback' );
		$order    = Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
		$orderby  = Utils\get_flag_value( $assoc_args, 'orderby', null );

		// Set up profiler to track hooks and callbacks
		$type  = null;
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
					$normalized_callback = trim((string) $logger->callback);
					$normalized_filter   = trim($callback);
					if ( false === stripos( $normalized_callback, $normalized_filter ) ) {
						continue;
					}
				}

				// Skip if filtering for a specific hook and this isn't the right one
				if ( $hook && isset( $logger->hook ) && $logger->hook !== $hook ) {
					continue;
				}

				// Get the query indices for this logger
				if ( isset( $logger->query_indices ) && ! empty( $logger->query_indices ) ) {
					foreach ( $logger->query_indices as $query_index ) {
						if ( ! isset( $query_map[ $query_index ] ) ) {
							$query_map[ $query_index ] = array(
								'hook'     => isset( $logger->hook ) ? $logger->hook : null,
								'callback' => isset( $logger->callback ) ? $logger->callback : null,
							);
						}
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

				$query_obj = new QueryLogger(
					$query_data[0], // SQL query
					$query_data[1], // Time
					isset( $query_data[2] ) ? $query_data[2] : '', // Caller
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
	 * @param array $loggers
	 * @param array $metrics
	 * @return array
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
}
