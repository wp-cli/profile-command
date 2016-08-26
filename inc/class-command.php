<?php

namespace runcommand\Profile;

use WP_CLI;
use WP_CLI\Utils;

/**
 * Profile the performance of a WordPress request.
 */
class Command {

	private $loggers = array();
	private $focus_scope;
	private $scope_hooks = array();
	private $focus_hook;
	private $current_filter_callbacks = array();
	private $focus_query_offset = 0;

	/**
	 * Profile the performance of a WordPress request.
	 *
	 * Monitors aspects of the WordPress execution process to display key
	 * performance indicators for audit.
	 *
	 * ```
	 * $ wp profile
	 * +------------+----------------+-------------+------------+------------+-----------+
	 * | scope      | execution_time | query_count | query_time | hook_count | hook_time |
	 * +------------+----------------+-------------+------------+------------+-----------+
	 * | total      | 2.6685s        | 196         | 0.0274s    | 10723      | 0.2173s   |
	 * | bootstrap  | 2.2609s        | 15          | 0.0037s    | 2836       | 0.1166s   |
	 * | main_query | 0.0126s        | 3           | 0.0004s    | 78         | 0.0014s   |
	 * | template   | 0.3941s        | 178         | 0.0234s    | 7809       | 0.0993s   |
	 * +------------+----------------+-------------+------------+------------+-----------+
	 * ```
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : Execute a request against a specified URL. Defaults to the home URL.
	 *
	 * [--scope=<scope>]
	 * : Drill down into a specific scope.
	 * ---
	 * options:
	 *   - bootstrap
	 *   - main_query
	 *   - template
	 * ---
	 *
	 * [--hook=<hook>]
	 * : Drill down into a specific hook.
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
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;

		$this->focus_scope = Utils\get_flag_value( $assoc_args, 'scope' );
		$this->focus_hook = Utils\get_flag_value( $assoc_args, 'hook' );

		if ( ! isset( WP_CLI::get_runner()->config['url'] ) ) {
			WP_CLI::add_wp_hook( 'muplugins_loaded', function(){
				WP_CLI::set_url( home_url( '/' ) );
			});
		}
		if ( ! defined( 'SAVEQUERIES' ) ) {
			define( 'SAVEQUERIES', true );
		}
		WP_CLI::add_wp_hook( 'all', array( $this, 'wp_hook_begin' ) );
		WP_CLI::add_wp_hook( 'pre_http_request', array( $this, 'wp_request_begin' ) );
		WP_CLI::add_wp_hook( 'http_api_debug', array( $this, 'wp_request_end' ) );
		try {
			$this->load_wordpress_with_template();
		} catch( \Exception $e ) {
			// pass through
		}

		if ( $this->focus_scope ) {
			$fields = array(
				'hook',
				'time',
				'query_time',
				'query_count',
				'cache_hits',
				'cache_misses',
				'cache_ratio',
				'request_time',
				'request_count',
			);
		} else if ( $this->focus_hook ) {
			$fields = array(
				'callback',
				'time',
				'query_time',
				'query_count',
				'cache_hits',
				'cache_misses',
				'cache_ratio',
				'request_time',
				'request_count',
			);
		} else {
			$fields = array(
				'scope',
				'time',
				'query_time',
				'query_count',
				'cache_hits',
				'cache_misses',
				'cache_ratio',
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
	 * Profiling verbosity at the beginning of every action and filter
	 */
	public function wp_hook_begin() {
		global $wpdb, $wp_filter;

		foreach( Logger::$active_loggers as $logger ) {
			$logger->start_hook_timer();
		}

		$current_filter = current_filter();
		if ( in_array( $current_filter, $this->scope_hooks ) ) {
			$pseudo_hook = "before {$current_filter}";
			if ( isset( $this->loggers[ $pseudo_hook ] ) ) {
				$this->loggers[ $pseudo_hook ]->stop();
			}
			$this->loggers[ $current_filter ] = new Logger( 'hook', $current_filter );
			$this->loggers[ $current_filter ]->start();
		}

		if ( $this->focus_hook && $current_filter === $this->focus_hook ) {
			$this->current_filter_callbacks = $wp_filter[ $current_filter ];
			unset( $wp_filter[ $current_filter ] );
			call_user_func_array( array( $this, 'do_action' ), func_get_args() );
			throw new \Exception( "Need to bail, because can't restore the hooks" );
		}

		WP_CLI::add_wp_hook( $current_filter, array( $this, 'wp_hook_end' ), 999 );
	}

	/**
	 * Instrumented version of do_action()
	 */
	private function do_action( $tag, $arg = '' ) {
		global $wp_actions, $merged_filters, $wp_current_filter;
		$wp_filter = array();
		$wp_filter[ $tag ] = $this->current_filter_callbacks;

		if ( ! isset($wp_actions[$tag]) )
			$wp_actions[$tag] = 1;
		else
			++$wp_actions[$tag];

		if ( !isset($wp_filter['all']) )
			$wp_current_filter[] = $tag;

		$args = array();
		if ( is_array($arg) && 1 == count($arg) && isset($arg[0]) && is_object($arg[0]) ) // array(&$this)
			$args[] =& $arg[0];
		else
			$args[] = $arg;
		for ( $a = 2, $num = func_num_args(); $a < $num; $a++ )
			$args[] = func_get_arg($a);

		// Sort
		if ( !isset( $merged_filters[ $tag ] ) ) {
			ksort($wp_filter[$tag]);
			$merged_filters[ $tag ] = true;
		}

		reset( $wp_filter[ $tag ] );

		do {
			foreach ( (array) current($wp_filter[$tag]) as $i => $the_ )
				if ( !is_null($the_['function']) ) {
					if ( ! isset( $this->loggers[ $i ] ) ) {
						$this->loggers[ $i ] = new Logger( 'callback', self::get_name_from_callback( $the_['function'] ) );
						$this->loggers[ $i ]->start();
					}
					call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));
					$this->loggers[ $i ]->stop();
				}

		} while ( next($wp_filter[$tag]) !== false );

		array_pop($wp_current_filter);
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
		if ( in_array( $current_filter, $this->scope_hooks ) ) {
			$this->loggers[ $current_filter ]->stop();
			$key = array_search( $current_filter, $this->scope_hooks );
			if ( false !== $key && isset( $this->scope_hooks[$key+1] ) ) {
				$pseudo_hook = "before {$this->scope_hooks[$key+1]}";
				$this->loggers[ $pseudo_hook ] = new Logger( 'hook', '' );
				$this->loggers[ $pseudo_hook ]->start();
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
	 * Runs through the entirety of the WP bootstrap process
	 */
	private function load_wordpress_with_template() {
		global $wp_query;

		if ( 'bootstrap' === $this->focus_scope ) {
			$this->set_scope_hooks( array(
				'muplugins_loaded',
				'plugins_loaded',
				'setup_theme',
				'after_setup_theme',
				'init',
				'wp_loaded',
			) );
		} else if ( ! $this->focus_scope && ! $this->focus_hook ) {
			$logger = new Logger( 'scope', 'bootstrap' );
			$logger->start();
		}
		WP_CLI::get_runner()->load_wordpress();
		if ( ! $this->focus_scope && ! $this->focus_hook ) {
			$logger->stop();
			$this->loggers[] = $logger;
		}

		// Set up main_query main WordPress query.
		if ( 'main_query' === $this->focus_scope ) {
			$this->set_scope_hooks( array(
				'parse_request',
				'send_headers',
				'pre_get_posts',
				'the_posts',
				'wp',
			) );
		} else if ( ! $this->focus_scope && ! $this->focus_hook ) {
			$logger = new Logger( 'scope', 'main_query' );
			$logger->start();
		}
		wp();
		if ( ! $this->focus_scope && ! $this->focus_hook ) {
			$logger->stop();
			$this->loggers[] = $logger;
		}

		define( 'WP_USE_THEMES', true );

		// Template is normally loaded in global scope, so we need to replicate
		foreach( $GLOBALS as $key => $value ) {
			global $$key;
		}

		// Load the theme template.
		if ( ! $this->focus_scope && ! $this->focus_hook ) {
			$logger = new Logger( 'scope', 'template' );
			$logger->start();
		}
		ob_start();
		require_once( ABSPATH . WPINC . '/template-loader.php' );
		ob_get_clean();
		if ( ! $this->focus_scope && ! $this->focus_hook ) {
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
	 * Set the hooks for the current scope
	 */
	private function set_scope_hooks( $hooks ) {
		$this->scope_hooks = $hooks;
		$pseudo_hook = "before {$hooks[0]}";
		$this->loggers[ $pseudo_hook ] = new Logger( 'hook', '' );
		$this->loggers[ $pseudo_hook ]->start();
	}

}
