<?php

/**
 * Profile the performance of a WordPress request.
 */
class Profile_Command {

	private $hook_start_time = 0;
	private $hook_time = 0;
	private $scope_log;
	private $current_scope;
	private $focus_scope;
	private $focus_start_time;
	private $focus_log = array();
	private $hook_scope;
	private $hook_log = array();
	private $current_filter_callbacks = array();
	private $query_offset = 0;
	private $focus_query_offset = 0;
	private $hook_offset = 0;

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

		$this->scope_log = array();
		$scope_fields = array(
			'scope',
			'execution_time',
			'query_count',
			'query_time',
			'hook_count',
			'hook_time',
		);
		foreach( array( 'total', 'bootstrap', 'main_query', 'template' ) as $scope ) {
			$this->scope_log[ $scope ] = array();
			foreach( $scope_fields as $field ) {
				if ( 'scope' === $field ) {
					$this->scope_log[ $scope ][ $field ] = $scope;
				} else {
					$this->scope_log[ $scope ][ $field ] = 0;
				}
			}
		}

		$this->focus_scope = WP_CLI\Utils\get_flag_value( $assoc_args, 'scope' );
		$this->focus_hook = WP_CLI\Utils\get_flag_value( $assoc_args, 'hook' );

		if ( ! isset( \WP_CLI::get_runner()->config['url'] ) ) {
			WP_CLI::add_wp_hook( 'muplugins_loaded', function(){
				WP_CLI::set_url( home_url( '/' ) );
			});
		}
		if ( ! defined( 'SAVEQUERIES' ) ) {
			define( 'SAVEQUERIES', true );
		}
		WP_CLI::add_wp_hook( 'all', array( $this, 'wp_hook_begin' ) );
		try {
			$this->load_wordpress_with_template();
		} catch( Exception $e ) {
			// pass through
		}

		if ( $this->focus_scope ) {
			$focus_fields = array(
				'hook',
				'execution_time',
				'query_time',
				'query_count',
			);
			foreach( $this->focus_log as $hook => $data ) {
				foreach( $data as $key => $value ) {
					// Round times to 4 decimal points
					if ( stripos( $key,'_time' ) ) {
						$this->focus_log[ $hook ][ $key ] = round( $value, 4 ) . 's';
					}
				}
				// Drop hook labels with 'pre_' in the name
				if ( 0 === strpos( $hook, 'pre_' ) ) {
					$this->focus_log[ $hook ]['hook'] = '';
				}
			}
			$formatter = new \WP_CLI\Formatter( $assoc_args, $focus_fields );
			$formatter->display_items( $this->focus_log );
		} else if ( $this->focus_hook ) {
			$hook_fields = array(
				'callback',
				'execution_time',
				'query_time',
				'query_count',
			);
			foreach( $this->hook_log as $id => $data ) {
				foreach( $data as $key => $value ) {
					// Round times to 4 decimal points
					if ( stripos( $key,'_time' ) ) {
						$this->hook_log[ $id ][ $key ] = round( $value, 4 ) . 's';
					}
				}
			}
			$formatter = new \WP_CLI\Formatter( $assoc_args, $hook_fields );
			$formatter->display_items( $this->hook_log );
		} else {

			foreach( $this->scope_log as $scope => $data ) {
				foreach( $data as $key => $value ) {
					// Round times to 4 decimal points
					if ( stripos( $key,'_time' ) ) {
						$this->scope_log[ $scope ][ $key ] = round( $value, 4 ) . 's';
					}
				}
			}
			$formatter = new \WP_CLI\Formatter( $assoc_args, $scope_fields );
			$formatter->display_items( $this->scope_log );
		}
	}

	/**
	 * Profiling verbosity at the beginning of every action and filter
	 */
	public function wp_hook_begin() {
		global $wpdb, $wp_filter;

		$this->scope_log['total']['hook_count']++;
		$this->scope_log[ $this->current_scope ]['hook_count']++;
		$this->hook_start_time = microtime( true );

		$current_filter = current_filter();
		if ( array_key_exists( $current_filter, $this->focus_log ) ) {
			$this->focus_log["pre_{$current_filter}"]['execution_time'] = microtime( true ) - $this->focus_start_time;
			$this->focus_start_time = microtime( true );
			for ( $i = $this->focus_query_offset; $i < count( $wpdb->queries ); $i++ ) {
				$this->focus_log["pre_{$current_filter}"]['query_time'] += $wpdb->queries[ $i ][1];
				$this->focus_log["pre_{$current_filter}"]['query_count']++;
			}
			$this->focus_query_offset = count( $wpdb->queries );
		}

		if ( $this->focus_hook && $current_filter === $this->focus_hook ) {
			$this->current_filter_callbacks = $wp_filter[ $current_filter ];
			unset( $wp_filter[ $current_filter ] );
			call_user_func_array( array( $this, 'do_action' ), func_get_args() );
			throw new Exception( "Need to bail, because can't restore the hooks" );
		}

		WP_CLI::add_wp_hook( $current_filter, array( $this, 'wp_hook_end' ), 999 );
	}

	/**
	 * Instrumented version of do_action()
	 */
	private function do_action( $tag, $arg = '' ) {
		global $wp_actions, $merged_filters, $wp_current_filter, $wpdb;
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
					if ( ! isset( $this->hook_log[ $i ] ) ) {
						$this->hook_log[ $i ] = array(
							'callback'        => self::get_name_from_callback( $the_['function'] ),
							'execution_time'  => 0,
							'query_count'     => 0,
							'query_time'      => 0,
						);
					}
					$start_time = microtime( true );
					$query_offset = count( $wpdb->queries );
					call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));
					$this->hook_log[ $i ]['execution_time'] = microtime( true ) - $start_time;
					for ( $j = $query_offset; $j < count( $wpdb->queries ); $j++ ) {
						$this->hook_log[ $i ]['query_time'] += $wpdb->queries[ $i ][1];
						$this->hook_log[ $i ]['query_count']++;
					}
				}

		} while ( next($wp_filter[$tag]) !== false );

		array_pop($wp_current_filter);
	}

	/**
	 * Profiling verbosity at the end of every action and filter
	 */
	public function wp_hook_end( $filter_value = null ) {
		global $wpdb, $wp_filter;

		$current_filter = current_filter();
		if ( array_key_exists( $current_filter, $this->focus_log ) ) {
			$this->focus_log[ $current_filter ]['execution_time'] = microtime( true ) - $this->focus_start_time;
			$this->focus_start_time = microtime( true );
			for ( $i = $this->focus_query_offset; $i < count( $wpdb->queries ); $i++ ) {
				$this->focus_log[ $current_filter ]['query_time'] += $wpdb->queries[ $i ][1];
				$this->focus_log[ $current_filter ]['query_count']++;
			}
			$this->focus_query_offset = count( $wpdb->queries );
		}

		$this->hook_time += microtime( true ) - $this->hook_start_time;
		return $filter_value;
	}

	/**
	 * Runs through the entirety of the WP bootstrap process
	 */
	private function load_wordpress_with_template() {
		global $wp_query;

		$this->scope_track_begin( 'total' );
		$this->scope_track_begin( 'bootstrap' );
		if ( 'bootstrap' === $this->focus_scope ) {
			$this->fill_hooks( array(
				'muplugins_loaded',
				'plugins_loaded',
				'setup_theme',
				'after_setup_theme',
				'init',
				'wp_loaded',
			) );
		}
		WP_CLI::get_runner()->load_wordpress();
		$this->scope_track_end( 'bootstrap' );

		// Set up the main WordPress query.
		$this->current_scope = 'main_query';
		$this->scope_track_begin( 'main_query' );
		wp();
		$this->scope_track_end( 'main_query' );

		define( 'WP_USE_THEMES', true );

		// Template is normally loaded in global scope, so we need to replicate
		foreach( $GLOBALS as $key => $value ) {
			global $$key;
		}

		// Load the theme template.
		$this->scope_track_begin( 'template' );
		ob_start();
		require_once( ABSPATH . WPINC . '/template-loader.php' );
		ob_get_clean();
		$this->scope_track_end( 'template' );
		$this->scope_track_end( 'total' );
	}

	/**
	 * Start tracking the current scope
	 */
	private function scope_track_begin( $scope ) {
		if ( 'total' !== $scope ) {
			$this->current_scope = $scope;
		}
		$this->scope_log[ $scope ]['execution_time'] = microtime( true );
		$this->hook_offset = $this->hook_time;
	}

	/**
	 * End tracking the current scope
	 */
	private function scope_track_end( $scope ) {
		global $wpdb;
		$this->scope_log[ $scope ]['execution_time'] = microtime( true ) - $this->scope_log[ $scope ]['execution_time'];
		$query_offset = 'total' === $scope ? 0 : $this->query_offset;
		for ( $i = $query_offset; $i < count( $wpdb->queries ); $i++ ) {
			$this->scope_log[ $scope ]['query_time'] += $wpdb->queries[ $i ][1];
			$this->scope_log[ $scope ]['query_count']++;
		}
		$this->query_offset = count( $wpdb->queries );
		$hook_time = 'total' === $scope ? $this->hook_time : $this->hook_time - $this->hook_offset;
		$this->scope_log[ $scope ]['hook_time'] = $hook_time;
	}

	/**
	 * Fill the hooks with start data
	 */
	private function fill_hooks( $hooks ) {
		foreach( $hooks as $hook ) {
			foreach( array( "pre_{$hook}", $hook ) as $k ) {
				$this->focus_log[ $k ] = array(
					'hook'             => $k,
					'execution_time'   => 0,
					'query_count'      => 0,
					'query_time'       => 0,
				);
			}
		}
		$this->focus_start_time = microtime( true );
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

}
