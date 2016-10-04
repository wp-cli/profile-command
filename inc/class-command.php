<?php

namespace runcommand\Profile;

use WP_CLI;
use WP_CLI\Utils;

class Command {

	private $loggers = array();
	private $focus_stage = null;
	private $stage_hooks = array();
	private $focus_hook = null;
	private $current_filter_callbacks = array();
	private $focus_query_offset = 0;

	private static $exception_message = "Need to bail, because can't restore the hooks";

	/**
	 * Profile each stage of the WordPress load process.
	 *
	 * ## OPTIONS
	 *
	 * [<stage>]
	 * : Drill down into a specific stage.
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

		if ( isset( $args[0] ) ) {
			$this->focus_stage = $args[0];
		}

		$valid_stages = array( 'bootstrap', 'main_query', 'template' );
		if ( $this->focus_stage && ! in_array( $this->focus_stage, $valid_stages, true ) ) {
			WP_CLI::error( 'Invalid stage. Must be one of: ' . implode( ', ', $valid_stages ) );
		}

		$this->run_profiler();

		if ( $this->focus_stage ) {
			$fields = array(
				'hook',
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
		$formatter->display_items( $this->loggers );
	}

	/**
	 * Profile key metrics for a WordPress hook (action or filter).
	 *
	 * ## OPTIONS
	 *
	 * <hook>
	 * : WordPress hook (action or filter) to profile.
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

		$this->focus_hook = $args[0];
		$this->run_profiler();

		$fields = array(
			'callback',
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
		$formatter->display_items( $this->loggers );
	}

	/**
	 * Profiling verbosity at the beginning of every action and filter
	 */
	public function wp_hook_begin() {
		global $wpdb, $wp_filter;

		foreach( Logger::$active_loggers as $logger ) {
			$logger->start_hook_timer();
		}

		$current_filter = current_filter();
		if ( in_array( $current_filter, $this->stage_hooks ) ) {
			$pseudo_hook = "before {$current_filter}";
			if ( isset( $this->loggers[ $pseudo_hook ] ) ) {
				$this->loggers[ $pseudo_hook ]->stop();
			}
			$this->loggers[ $current_filter ] = new Logger( 'hook', $current_filter );
			$this->loggers[ $current_filter ]->start();
		}

		if ( $this->focus_hook && $current_filter === $this->focus_hook ) {
			$this->wrap_current_filter_callbacks( $current_filter );
		}

		WP_CLI::add_wp_hook( $current_filter, array( $this, 'wp_hook_end' ), 9999 );
	}

	/**
	 * Wrap current filter callbacks with a timer
	 */
	private function wrap_current_filter_callbacks( $current_filter ) {
		global $wp_filter;
		$this->current_filter_callbacks = null;

		if ( ! isset( $wp_filter[ $current_filter ] ) ) {
			return;
		}

		if ( is_a( $wp_filter[ $current_filter ], 'WP_Hook' ) ) {
			$callbacks = $this->current_filter_callbacks = $wp_filter[ $current_filter ]->callbacks;
		} else {
			$callbacks = $this->current_filter_callbacks = $wp_filter[ $current_filter ];
		}

		if ( ! is_array( $callbacks ) ) {
			return;
		}

		foreach( $callbacks as $priority => $priority_callbacks ) {
			foreach( $priority_callbacks as $i => $the_ ) {
				$callbacks[ $priority ][ $i ] = array(
					'function'       => function() use( $the_, $i ) {
						if ( ! isset( $this->loggers[ $i ] ) ) {
							$this->loggers[ $i ] = new Logger( 'callback', self::get_name_from_callback( $the_['function'] ) );
						}
						$this->loggers[ $i ]->start();
						$value = call_user_func_array( $the_['function'], func_get_args() );
						$this->loggers[ $i ]->stop();
						return $value;
					},
					'accepted_args'  => $the_['accepted_args'],
				);
			}
		}

		if ( is_a( $wp_filter[ $current_filter ], 'WP_Hook' ) ) {
			$wp_filter[ $current_filter ]->callbacks = $callbacks;
		} else {
			$wp_filter[ $current_filter ] = $callbacks;
		}
	}

	/**
	 * Profiling verbosity at the end of every action and filter
	 */
	public function wp_hook_end( $filter_value = null ) {
		global $wpdb, $wp_filter;

		foreach( Logger::$active_loggers as $logger ) {
			$logger->stop_hook_timer();
		}

		$current_filter = current_filter();
		if ( in_array( $current_filter, $this->stage_hooks ) ) {
			$this->loggers[ $current_filter ]->stop();
			$key = array_search( $current_filter, $this->stage_hooks );
			if ( false !== $key && isset( $this->stage_hooks[$key+1] ) ) {
				$pseudo_hook = "before {$this->stage_hooks[$key+1]}";
				$this->loggers[ $pseudo_hook ] = new Logger( 'hook', '' );
				$this->loggers[ $pseudo_hook ]->start();
			} else {
				$pseudo_hook = 'wp_profile_last_hook';
				$this->loggers[ $pseudo_hook ] = new Logger( 'hook', '' );
				$this->loggers[ $pseudo_hook ]->start();
			}
		}

		if ( $this->focus_hook && $current_filter === $this->focus_hook && ! is_null( $this->current_filter_callbacks ) ) {
			if ( is_a( $wp_filter[ $current_filter ], 'WP_Hook' ) ) {
				$wp_filter[ $current_filter ]->callbacks = $this->current_filter_callbacks;
			} else {
				$wp_filter[ $current_filter ] = $this->current_filter_callbacks;
			}
		}

		return $filter_value;
	}

	/**
	 * Profiling request time for any active Loggers
	 */
	public function wp_request_begin( $filter_value = null ) {
		foreach( Logger::$active_loggers as $logger ) {
			$logger->start_request_timer();
		}
		return $filter_value;
	}

	/**
	 * Profiling request time for any active Loggers
	 */
	public function wp_request_end( $filter_value = null ) {
		foreach( Logger::$active_loggers as $logger ) {
			$logger->stop_request_timer();
		}
		return $filter_value;
	}

	/**
	 * Run the profiler against WordPress
	 */
	private function run_profiler() {
		if ( ! isset( WP_CLI::get_runner()->config['url'] ) ) {
			WP_CLI::add_wp_hook( 'muplugins_loaded', function(){
				WP_CLI::set_url( home_url( '/' ) );
			});
		}
		WP_CLI::add_hook( 'after_wp_config_load', function() {
			if ( defined( 'SAVEQUERIES' ) && ! SAVEQUERIES ) {
				WP_CLI::error( "'SAVEQUERIES' is defined as false, and must be true. Please check your wp-config.php" );
			}
			if ( ! defined( 'SAVEQUERIES' ) ) {
				define( 'SAVEQUERIES', true );
			}
		});
		WP_CLI::add_wp_hook( 'all', array( $this, 'wp_hook_begin' ) );
		WP_CLI::add_wp_hook( 'pre_http_request', array( $this, 'wp_request_begin' ) );
		WP_CLI::add_wp_hook( 'http_api_debug', array( $this, 'wp_request_end' ) );
		$this->load_wordpress_with_template();
	}

	/**
	 * Runs through the entirety of the WP bootstrap process
	 */
	private function load_wordpress_with_template() {
		global $wp_query;

		if ( 'bootstrap' === $this->focus_stage ) {
			$this->set_stage_hooks( array(
				'muplugins_loaded',
				'plugins_loaded',
				'setup_theme',
				'after_setup_theme',
				'init',
				'wp_loaded',
			) );
		} else if ( ! $this->focus_stage && ! $this->focus_hook ) {
			$logger = new Logger( 'stage', 'bootstrap' );
			$logger->start();
		}
		WP_CLI::get_runner()->load_wordpress();
		if ( isset( $this->loggers['wp_profile_last_hook'] ) && $this->loggers['wp_profile_last_hook']->running() ) {
			$this->loggers['wp_profile_last_hook']->stop();
		}
		if ( ! $this->focus_stage && ! $this->focus_hook ) {
			$logger->stop();
			$this->loggers[] = $logger;
		}

		// Set up main_query main WordPress query.
		if ( 'main_query' === $this->focus_stage ) {
			$this->set_stage_hooks( array(
				'parse_request',
				'send_headers',
				'pre_get_posts',
				'the_posts',
				'wp',
			) );
		} else if ( ! $this->focus_stage && ! $this->focus_hook ) {
			$logger = new Logger( 'stage', 'main_query' );
			$logger->start();
		}
		wp();
		if ( isset( $this->loggers['wp_profile_last_hook'] ) && $this->loggers['wp_profile_last_hook']->running() ) {
			$this->loggers['wp_profile_last_hook']->stop();
		}
		if ( ! $this->focus_stage && ! $this->focus_hook ) {
			$logger->stop();
			$this->loggers[] = $logger;
		}

		define( 'WP_USE_THEMES', true );

		// Template is normally loaded in global stage, so we need to replicate
		foreach( $GLOBALS as $key => $value ) {
			global $$key;
		}

		// Load the theme template.
		if ( 'template' === $this->focus_stage ) {
			$this->set_stage_hooks( array(
				'template_redirect',
				'template_include',
				'wp_head',
				'loop_start',
				'loop_end',
				'wp_footer',
			) );
		} else if ( ! $this->focus_stage && ! $this->focus_hook ) {
			$logger = new Logger( 'stage', 'template' );
			$logger->start();
		}
		ob_start();
		require_once( ABSPATH . WPINC . '/template-loader.php' );
		ob_get_clean();
		if ( isset( $this->loggers['wp_profile_last_hook'] ) && $this->loggers['wp_profile_last_hook']->running() ) {
			$this->loggers['wp_profile_last_hook']->stop();
		}
		if ( ! $this->focus_stage && ! $this->focus_hook ) {
			$logger->stop();
			$this->loggers[] = $logger;
		}

	}

	/**
	 * Get a human-readable name from a callback
	 */
	private static function get_name_from_callback( $callback ) {
		$name = '';
		if ( is_array( $callback ) && is_object( $callback[0] ) ) {
			$name = get_class( $callback[0] ) . '->' . $callback[1] . '()';
		} elseif ( is_array( $callback ) && method_exists( $callback[0], $callback[1] ) ) {
			$name = $callback[0] . '::' . $callback[1] . '()';
		} elseif ( is_object( $callback ) && is_a( $callback, 'Closure' ) ) {
			$name = 'function(){}';
		} else if ( is_string( $callback ) ) {
			$name = $callback . '()';
		}
		return $name;
	}

	/**
	 * Set the hooks for the current stage
	 */
	private function set_stage_hooks( $hooks ) {
		$this->stage_hooks = $hooks;
		$pseudo_hook = "before {$hooks[0]}";
		$this->loggers[ $pseudo_hook ] = new Logger( 'hook', '' );
		$this->loggers[ $pseudo_hook ]->start();
	}

}
