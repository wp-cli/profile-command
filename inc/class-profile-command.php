<?php

/**
 * Profile the performance of a request to WordPress.
 */
class Profile_Command {

	/**
	 * Profile the performance of a request to WordPress.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : Execute a request against a specified URL. Defaults to the home URL.
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {

		if ( ! isset( \WP_CLI::get_runner()->config['url'] ) ) {
			$this->add_wp_hook( 'muplugins_loaded', function(){
				WP_CLI::set_url( home_url( '/' ) );
			});
		}

		$start_time = microtime( true );
		$this->load_wordpress_with_template();

		$profile = array(
			'memory_usage'      => self::convert_size( memory_get_usage( true ) ),
			'total_time'        => round( microtime( true ) - $start_time, 3 ) . 's',
		);
		$formatter = new \WP_CLI\Formatter( $assoc_args, array_keys( $profile ) );
		$formatter->display_item( $profile );
	}

	/**
	 * Runs through the entirety of the WP bootstrap process
	 */
	private function load_wordpress_with_template() {
		global $wp_query;

		WP_CLI::get_runner()->load_wordpress();

		// Set up the main WordPress query.
		wp();

		define( 'WP_USE_THEMES', true );

		// Template is normally loaded in global scope, so we need to replicate
		foreach( $GLOBALS as $key => $value ) {
			global $$key;
		}

		// Load the theme template.
		ob_start();
		require_once( ABSPATH . WPINC . '/template-loader.php' );
		ob_get_clean();
	}

	/**
	 * Convert a memory size to something human-readable
	 *
	 * @see http://php.net/manual/en/function.memory-get-usage.php#96280
	 */
	private static function convert_size( $size ) {
		$unit = array( 'b', 'kb', 'mb', 'gb', 'tb', 'pb' );
		return @round( $size / pow( 1024, ( $i= floor( log( $size,1024 ) ) ) ), 2 ) . ' ' . $unit[ $i ];
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
