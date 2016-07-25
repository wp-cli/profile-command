<?php

/**
 * Profile the performance of a WordPress request.
 */
class Profile_Command {

	private $hook_start_time = 0;
	private $hook_time = 0;
	private $scope_log;
	private $current_scope;
	private $query_offset = 0;
	private $hook_offset = 0;

	/**
	 * Profile the performance of a WordPress request.
	 *
	 * Monitors aspects of the WordPress execution process to display key
	 * performance indicators for audit.
	 *
	 * ```
	 * $ wp profile
	 * +------------+----------------+-------------+------------+------------+-----------+--------------+
	 * | scope      | execution_time | query_count | query_time | hook_count | hook_time | memory_usage |
	 * +------------+----------------+-------------+------------+------------+-----------+--------------+
	 * | total      | 2.68269s       | 192         | 0.02165s   | 10737      | 0.19395s  | 49.25mb      |
	 * | bootstrap  | 2.34255s       | 15          | 0.00386s   | 2835       | 0.11172s  | 45mb         |
	 * | main_query | 0.01155s       | 3           | 0.0004s    | 78         | 0.00117s  | 45.75mb      |
	 * | template   | 0.32768s       | 174         | 0.0174s    | 7824       | 0.08106s  | 49.25mb      |
	 * +------------+----------------+-------------+------------+------------+-----------+--------------+
	 * ```
	 *
	 * ## OPTIONS
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
			'memory_usage',
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

		if ( ! isset( \WP_CLI::get_runner()->config['url'] ) ) {
			$this->add_wp_hook( 'muplugins_loaded', function(){
				WP_CLI::set_url( home_url( '/' ) );
			});
		}
		if ( ! defined( 'SAVEQUERIES' ) ) {
			define( 'SAVEQUERIES', true );
		}
		$this->add_wp_hook( 'all', array( $this, 'wp_hook_begin' ) );
		$this->load_wordpress_with_template();

		$total_query_time = 0;
		foreach( $wpdb->queries as $query ) {
			$total_query_time += $query[1];
		}
		$profile = array(
			'hook_time'         => $this->hook_time,
			'memory_usage'      => self::convert_size( memory_get_usage( true ) ),
			'query_count'       => count( $wpdb->queries ),
			'query_time'        => round( $total_query_time, 3 ) . 's',
			'template_time'     => round( $this->template_time, 3 ) . 's',
		);
		foreach( $this->scope_log as $scope => $data ) {
			foreach( $data as $key => $value ) {
				if ( stripos( $key,'_time' ) ) {
					$this->scope_log[ $scope ][ $key ] = round( $value, 5 ) . 's';
				}
			}
		}
		$formatter = new \WP_CLI\Formatter( $assoc_args, $scope_fields );
		$formatter->display_items( $this->scope_log );
	}

	/**
	 * Profiling verbosity at the beginning of every action and filter
	 */
	public function wp_hook_begin() {
		$this->scope_log['total']['hook_count']++;
		$this->scope_log[ $this->current_scope ]['hook_count']++;
		$this->hook_start_time = microtime( true );
		$this->add_wp_hook( current_filter(), array( $this, 'wp_hook_end' ), 999 );
	}

	/**
	 * Profiling verbosity at the end of every action and filter
	 */
	public function wp_hook_end( $filter_value = null ) {

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
		$this->scope_log[ $scope ]['memory_usage'] = self::convert_size( memory_get_usage( true ) );
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
	 * Convert a memory size to something human-readable
	 *
	 * @see http://php.net/manual/en/function.memory-get-usage.php#96280
	 */
	private static function convert_size( $size ) {
		$unit = array( 'b', 'kb', 'mb', 'gb', 'tb', 'pb' );
		return @round( $size / pow( 1024, ( $i= floor( log( $size,1024 ) ) ) ), 2 ) . $unit[ $i ];
	}

	/**
	 * Add a callback to a WordPress action or filter
	 *
	 * Essentially add_filter() without needing access to add_filter()
	 */
	private function add_wp_hook( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		global $wp_filter, $merged_filters;
		$idx = $this->wp_hook_build_unique_id($tag, $function_to_add, $priority);
		$wp_filter[$tag][$priority][$idx] = array('function' => $function_to_add, 'accepted_args' => $accepted_args);
		unset( $merged_filters[ $tag ] );
		return true;
	}

	/**
	 * Remove a callback from a WordPress action or filter
	 *
	 * Essentially remove_filter() without needing access to remove_filter()
	 */
	private function remove_wp_hook( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		$function_to_remove = $this->wp_hook_build_unique_id( $tag, $function_to_remove, $priority );

		$r = isset( $GLOBALS['wp_filter'][ $tag ][ $priority ][ $function_to_remove ] );

		if ( true === $r ) {
			unset( $GLOBALS['wp_filter'][ $tag ][ $priority ][ $function_to_remove ] );
			if ( empty( $GLOBALS['wp_filter'][ $tag ][ $priority ] ) ) {
				unset( $GLOBALS['wp_filter'][ $tag ][ $priority ] );
			}
			if ( empty( $GLOBALS['wp_filter'][ $tag ] ) ) {
				$GLOBALS['wp_filter'][ $tag ] = array();
			}
			unset( $GLOBALS['merged_filters'][ $tag ] );
		}

		return $r;
	}

	/**
	 * Build Unique ID for storage and retrieval.
	 *
	 * Essentially _wp_filter_build_unique_id() without needing access to _wp_filter_build_unique_id()
	 */
	private function wp_hook_build_unique_id( $tag, $function, $priority ) {
		global $wp_filter;
		static $filter_id_count = 0;

		if ( is_string($function) )
			return $function;

		if ( is_object($function) ) {
			// Closures are currently implemented as objects
			$function = array( $function, '' );
		} else {
			$function = (array) $function;
		}

		if (is_object($function[0]) ) {
			// Object Class Calling
			if ( function_exists('spl_object_hash') ) {
				return spl_object_hash($function[0]) . $function[1];
			} else {
				$obj_idx = get_class($function[0]).$function[1];
				if ( !isset($function[0]->wp_filter_id) ) {
					if ( false === $priority )
						return false;
					$obj_idx .= isset($wp_filter[$tag][$priority]) ? count((array)$wp_filter[$tag][$priority]) : $filter_id_count;
					$function[0]->wp_filter_id = $filter_id_count;
					++$filter_id_count;
				} else {
					$obj_idx .= $function[0]->wp_filter_id;
				}

				return $obj_idx;
			}
		} elseif ( is_string( $function[0] ) ) {
			// Static Calling
			return $function[0] . '::' . $function[1];
		}
	}

}
