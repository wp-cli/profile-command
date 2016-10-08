<?php

namespace runcommand\Profile;

use WP_CLI;

class Profiler {

	private $type;
	private $focus;
	private $loggers = array();
	private $stage_hooks = array(
		'bootstrap'    => array(
			'muplugins_loaded',
			'plugins_loaded',
			'setup_theme',
			'after_setup_theme',
			'init',
			'wp_loaded',
		),
		'main_query'   => array(
			'parse_request',
			'send_headers',
			'pre_get_posts',
			'the_posts',
			'wp',
		),
		'template'     => array(
			'template_redirect',
			'template_include',
			'wp_head',
			'loop_start',
			'loop_end',
			'wp_footer',
		),
	);
	private $current_stage_hooks = array();
	private $previous_filter = null;
	private $previous_filter_callbacks = null;
	private $filter_depth = 0;

	public function __construct( $type, $focus ) {
		$this->type = $type;
		$this->focus = $focus;
	}

	public function get_loggers() {
		return $this->loggers;
	}

	/**
	 * Run the profiler against WordPress
	 */
	public function run() {
		WP_CLI::add_wp_hook( 'muplugins_loaded', function(){
			if ( $url = WP_CLI::get_runner()->config['url'] ) {
				WP_CLI::set_url( trailingslashit( $url ) );
			} else {
				WP_CLI::set_url( home_url( '/' ) );
			}
		});
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
	 * Profiling verbosity at the beginning of every action and filter
	 */
	public function wp_hook_begin() {

		foreach( Logger::$active_loggers as $logger ) {
			$logger->start_hook_timer();
		}

		$current_filter = current_filter();
		if ( ( 'stage' === $this->type && in_array( $current_filter, $this->current_stage_hooks ) )
			|| ( 'hook' === $this->type && ! $this->focus ) ) {
			$pseudo_hook = "before {$current_filter}";
			if ( isset( $this->loggers[ $pseudo_hook ] ) ) {
				$this->loggers[ $pseudo_hook ]->stop();
			}
			$callback_count = 0;
			$callbacks = self::get_filter_callbacks( $current_filter );
			if ( false !== $callbacks ) {
				foreach( $callbacks as $priority => $cbs ) {
					$callback_count += count( $cbs );
				}
			}
			$this->loggers[ $current_filter ] = new Logger( array( 'hook' => $current_filter, 'callback_count' => $callback_count ) );
			$this->loggers[ $current_filter ]->start();
		}

		if ( ! is_null( $this->previous_filter_callbacks ) && 0 === $this->filter_depth ) {
			self::set_filter_callbacks( $this->previous_filter, $this->previous_filter_callbacks );
			$this->previous_filter_callbacks = null;
		}

		if ( 'hook' === $this->type && $current_filter === $this->focus && 0 === $this->filter_depth ) {
			$this->wrap_current_filter_callbacks( $current_filter );
			$this->filter_depth = 1;
		}

		if ( ! ( 'hook' === $this->type && 'shutdown' === $this->focus ) ) {
			WP_CLI::add_wp_hook( $current_filter, array( $this, 'wp_hook_end' ), 9999 );
		}
	}

	/**
	 * Wrap current filter callbacks with a timer
	 */
	private function wrap_current_filter_callbacks( $current_filter ) {

		$callbacks = self::get_filter_callbacks( $current_filter );
		if ( false === $callbacks ) {
			return;
		}
		$this->previous_filter = $current_filter;
		$this->previous_filter_callbacks = $callbacks;

		foreach( $callbacks as $priority => $priority_callbacks ) {
			foreach( $priority_callbacks as $i => $the_ ) {
				$callbacks[ $priority ][ $i ] = array(
					'function'       => function() use( $the_, $i ) {
						if ( ! isset( $this->loggers[ $i ] ) ) {
							list( $callback, $location ) = self::get_name_location_from_callback( $the_['function'] );
							$definition = array(
								'callback'     => $callback,
								'location'     => $location,
							);
							$this->loggers[ $i ] = new Logger( $definition );
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
		self::set_filter_callbacks( $current_filter, $callbacks );
	}

	/**
	 * Profiling verbosity at the end of every action and filter
	 */
	public function wp_hook_end( $filter_value = null ) {

		foreach( Logger::$active_loggers as $logger ) {
			$logger->stop_hook_timer();
		}

		$current_filter = current_filter();
		if ( 'hook' === $this->type && $current_filter === $this->focus ) {
			$this->filter_depth = 0;
		}

		if ( ( 'stage' === $this->type && in_array( $current_filter, $this->current_stage_hooks ) )
			|| ( 'hook' === $this->type && ! $this->focus ) ) {
			$this->loggers[ $current_filter ]->stop();
			if ( 'stage' === $this->type ) {
				$key = array_search( $current_filter, $this->current_stage_hooks );
				if ( false !== $key && isset( $this->current_stage_hooks[ $key + 1 ] ) ) {
					$pseudo_hook = "before {$this->current_stage_hooks[$key+1]}";
					$this->loggers[ $pseudo_hook ] = new Logger( array( 'hook' => '' ) );
					$this->loggers[ $pseudo_hook ]->start();
				} else {
					$pseudo_hook = 'wp_profile_last_hook';
					$this->loggers[ $pseudo_hook ] = new Logger( array( 'hook' => '' ) );
					$this->loggers[ $pseudo_hook ]->start();
				}
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

		if ( 'stage' === $this->type && true === $this->focus ) {
			$hooks = array();
			foreach( $this->stage_hooks as $stage_hook ) {
				$hooks = array_merge( $hooks, $stage_hook );
			}
			$this->set_stage_hooks( $hooks );
		}

		if ( 'stage' === $this->type ) {
			if ( 'bootstrap' === $this->focus ) {
				$this->set_stage_hooks( $this->stage_hooks['bootstrap'] );
			} else if ( ! $this->focus ) {
				$logger = new Logger( array( 'stage' => 'bootstrap' ) );
				$logger->start();
			}
		}
		WP_CLI::get_runner()->load_wordpress();
		if ( isset( $this->loggers['wp_profile_last_hook'] ) && $this->loggers['wp_profile_last_hook']->running() ) {
			$this->loggers['wp_profile_last_hook']->stop();
		}
		if ( 'stage' === $this->type && ! $this->focus ) {
			$logger->stop();
			$this->loggers[] = $logger;
		}

		// Set up main_query main WordPress query.
		if ( 'stage' === $this->type ) {
			if ( 'main_query' === $this->focus ) {
				$this->set_stage_hooks( $this->stage_hooks['main_query'] );
			} else if ( ! $this->focus ) {
				$logger = new Logger( array( 'stage' => 'main_query' ) );
				$logger->start();
			}
		}
		wp();
		if ( isset( $this->loggers['wp_profile_last_hook'] ) && $this->loggers['wp_profile_last_hook']->running() ) {
			$this->loggers['wp_profile_last_hook']->stop();
		}
		if ( 'stage' === $this->type && ! $this->focus ) {
			$logger->stop();
			$this->loggers[] = $logger;
		}

		define( 'WP_USE_THEMES', true );

		// Template is normally loaded in global stage, so we need to replicate
		foreach( $GLOBALS as $key => $value ) {
			global $$key;
		}

		// Load the theme template.
		if ( 'stage' === $this->type ) {
			if ( 'template' === $this->focus ) {
				$this->set_stage_hooks( $this->stage_hooks['template'] );
			} else if ( ! $this->focus ) {
				$logger = new Logger( array( 'stage' => 'template' ) );
				$logger->start();
			}
		}
		ob_start();
		require_once( ABSPATH . WPINC . '/template-loader.php' );
		ob_get_clean();
		if ( isset( $this->loggers['wp_profile_last_hook'] ) && $this->loggers['wp_profile_last_hook']->running() ) {
			$this->loggers['wp_profile_last_hook']->stop();
		}
		if ( 'stage' === $this->type && ! $this->focus ) {
			$logger->stop();
			$this->loggers[] = $logger;
		}

	}

	/**
	 * Get a human-readable name from a callback
	 */
	private static function get_name_location_from_callback( $callback ) {
		$name = $location = '';
		$reflection = false;
		if ( is_array( $callback ) && is_object( $callback[0] ) ) {
			$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			$name = get_class( $callback[0] ) . '->' . $callback[1] . '()';
		} elseif ( is_array( $callback ) && method_exists( $callback[0], $callback[1] ) ) {
			$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			$name = $callback[0] . '::' . $callback[1] . '()';
		} elseif ( is_object( $callback ) && is_a( $callback, 'Closure' ) ) {
			$reflection = new \ReflectionFunction( $callback );
			$name = 'function(){}';
		} else if ( is_string( $callback ) ) {
			$reflection = new \ReflectionFunction( $callback );
			$name = $callback . '()';
		}
		if ( $reflection ) {
			$location = $reflection->getFileName() . ':' . $reflection->getStartLine();
			$abspath = rtrim( realpath( ABSPATH ), '/' ) . '/';
			if ( 0 === stripos( $location, WP_PLUGIN_DIR ) ) {
				$location = str_replace( trailingslashit( WP_PLUGIN_DIR ), '', $location );
			} else if ( 0 === stripos( $location, WPMU_PLUGIN_DIR ) ) {
				$location = str_replace( trailingslashit( dirname( WPMU_PLUGIN_DIR ) ), '', $location );
			} else if ( 0 === stripos( $location, get_theme_root() ) ) {
				$location = str_replace( trailingslashit( get_theme_root() ), '', $location );
			} else if ( 0 === stripos( $location, $abspath . 'wp-admin/' ) ) {
				$location = str_replace( $abspath, '', $location );
			} else if ( 0 === stripos( $location, $abspath . 'wp-includes/' ) ) {
				$location = str_replace( $abspath, '', $location );
			}
		}
		return array( $name, $location );
	}

	/**
	 * Set the hooks for the current stage
	 */
	private function set_stage_hooks( $hooks ) {
		$this->current_stage_hooks = $hooks;
		$pseudo_hook = "before {$hooks[0]}";
		$this->loggers[ $pseudo_hook ] = new Logger( array( 'hook' => '' ) );
		$this->loggers[ $pseudo_hook ]->start();
	}

	/**
	 * Get the callbacks for a given filter
	 *
	 * @param string
	 * @return array|false
	 */
	private static function get_filter_callbacks( $filter ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $filter ] ) ) {
			return false;
		}

		if ( is_a( $wp_filter[ $filter ], 'WP_Hook' ) ) {
			$callbacks = $wp_filter[ $filter ]->callbacks;
		} else {
			$callbacks = $wp_filter[ $filter ];
		}
		if ( is_array( $callbacks ) ) {
			return $callbacks;
		}
		return false;
	}

	/**
	 * Set the callbacks for a given filter
	 *
	 * @param string $filter
	 * @param mixed $callbacks
	 */
	private static function set_filter_callbacks( $filter, $callbacks ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $filter ] ) && class_exists( 'WP_Hook' ) ) {
			$wp_filter[ $filter ] = new \WP_Hook;
		}

		if ( is_a( $wp_filter[ $filter ], 'WP_Hook' ) ) {
			$wp_filter[ $filter ]->callbacks = $callbacks;
		} else {
			$wp_filter[ $filter ] = $callbacks;
		}
	}
	
}
