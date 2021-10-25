<?php

namespace WP_CLI\Profile;

use WP_CLI;

class Profiler {

	private $type;
	private $focus;
	private $loggers     = [];
	private $stage_hooks = [
		'bootstrap'  => [
			'muplugins_loaded',
			'plugins_loaded',
			'setup_theme',
			'after_setup_theme',
			'init',
			'wp_loaded',
		],
		'main_query' => [
			'parse_request',
			'send_headers',
			'pre_get_posts',
			'the_posts',
			'wp',
		],
		'template'   => [
			'template_redirect',
			'template_include',
			'wp_head',
			'loop_start',
			'loop_end',
			'wp_footer',
		],
	];

	private $current_stage_hooks       = [];
	private $running_hook              = null;
	private $previous_filter           = null;
	private $previous_filter_callbacks = null;
	private $filter_depth              = 0;

	private $tick_callback          = null;
	private $tick_location          = null;
	private $tick_start_time        = null;
	private $tick_query_offset      = null;
	private $tick_cache_hit_offset  = null;
	private $tick_cache_miss_offset = null;

	public function __construct( $type, $focus ) {
		$this->type  = $type;
		$this->focus = $focus;
	}

	public function get_loggers() {
		foreach ( $this->loggers as $i => $logger ) {
			if ( is_array( $logger ) ) {
				$logger              = new Logger( $logger );
				$this->loggers[ $i ] = $logger;
			}
			if ( ! isset( $logger->callback ) ) {
				continue;
			}
			if ( ! isset( $logger->location ) ) {
				list( $name, $location ) = self::get_name_location_from_callback( $logger->callback );
				$logger->callback        = $name;
				$logger->location        = $location;
			}
			$logger->location    = self::get_short_location( $logger->location );
			$this->loggers[ $i ] = $logger;
		}
		return $this->loggers;
	}

	/**
	 * Run the profiler against WordPress.
	 */
	public function run() {
		$this->configure_wordpress_context();
		$this->enable_savequeries();
		WP_CLI::add_wp_hook( 'all', [ $this, 'wp_hook_begin' ] );
		WP_CLI::add_wp_hook( 'pre_http_request', [ $this, 'wp_request_begin' ] );
		WP_CLI::add_wp_hook( 'http_api_debug', [ $this, 'wp_request_end' ] );
		$this->load_wordpress_with_template();
	}

	/**
	 * Profiling verbosity at the beginning of every action and filter.
	 */
	public function wp_hook_begin() {

		foreach ( Logger::$active_loggers as $logger ) {
			$logger->start_hook_timer();
		}

		$current_filter = current_filter();
		if ( ( 'stage' === $this->type && in_array( $current_filter, $this->current_stage_hooks, true ) )
			|| ( 'hook' === $this->type && ! $this->focus ) ) {
			$pseudo_hook = "{$current_filter}:before";
			if ( isset( $this->loggers[ $pseudo_hook ] ) ) {
				$this->loggers[ $pseudo_hook ]->stop();
			}
			$callback_count = 0;
			$callbacks      = self::get_filter_callbacks( $current_filter );
			if ( false !== $callbacks ) {
				foreach ( $callbacks as $priority => $cbs ) {
					$callback_count += count( $cbs );
				}
			}
			$this->loggers[ $current_filter ] = new Logger(
				[
					'hook'           => $current_filter,
					'callback_count' => $callback_count,
				]
			);
			$this->loggers[ $current_filter ]->start();
		}

		if ( 0 === $this->filter_depth
			&& ! is_null( $this->previous_filter_callbacks ) ) {
			self::set_filter_callbacks( $this->previous_filter, $this->previous_filter_callbacks );
			$this->previous_filter_callbacks = null;
		}

		if ( 'hook' === $this->type
			&& 0 === $this->filter_depth
			&& ( $current_filter === $this->focus || true === $this->focus ) ) {
			$this->wrap_current_filter_callbacks( $current_filter );
		}

		$this->filter_depth++;

		WP_CLI::add_wp_hook( $current_filter, [ $this, 'wp_hook_end' ], 9999 );
	}

	/**
	 * Wrap current filter callbacks with a timer.
	 */
	private function wrap_current_filter_callbacks( $current_filter ) {

		$callbacks = self::get_filter_callbacks( $current_filter );
		if ( false === $callbacks ) {
			return;
		}
		$this->previous_filter           = $current_filter;
		$this->previous_filter_callbacks = $callbacks;

		foreach ( $callbacks as $priority => $priority_callbacks ) {
			foreach ( $priority_callbacks as $i => $the_ ) {
				$callbacks[ $priority ][ $i ] = [
					'function'      => function () use ( $the_, $i ) {
						if ( ! isset( $this->loggers[ $i ] ) ) {
							$this->loggers[ $i ] = new Logger(
								[
									'callback' => $the_['function'],
								]
							);
						}
						$this->loggers[ $i ]->start();
						$value = call_user_func_array( $the_['function'], func_get_args() );
						$this->loggers[ $i ]->stop();
						return $value;
					},
					'accepted_args' => $the_['accepted_args'],
				];
			}
		}
		self::set_filter_callbacks( $current_filter, $callbacks );
	}

	/**
	 * Profiling verbosity at the end of every action and filter.
	 */
	public function wp_hook_end( $filter_value = null ) {

		foreach ( Logger::$active_loggers as $logger ) {
			$logger->stop_hook_timer();
		}

		$current_filter = current_filter();
		if ( ( 'stage' === $this->type && in_array( $current_filter, $this->current_stage_hooks, true ) )
			|| ( 'hook' === $this->type && ! $this->focus ) ) {
			$this->loggers[ $current_filter ]->stop();
			if ( 'stage' === $this->type ) {
				$key = array_search( $current_filter, $this->current_stage_hooks, true );
				if ( false !== $key && isset( $this->current_stage_hooks[ $key + 1 ] ) ) {
					$pseudo_hook = "{$this->current_stage_hooks[$key+1]}:before";
				} else {
					$pseudo_hook        = "{$this->current_stage_hooks[$key]}:after";
					$this->running_hook = $pseudo_hook;
				}
				$this->loggers[ $pseudo_hook ] = new Logger( [ 'hook' => $pseudo_hook ] );
				$this->loggers[ $pseudo_hook ]->start();
			}
		}

		$this->filter_depth--;

		return $filter_value;
	}

	/**
	 * Handle the tick of a function.
	 */
	public function handle_function_tick() {
		global $wpdb, $wp_object_cache;

		if ( ! is_null( $this->tick_callback ) ) {
			$time = microtime( true ) - $this->tick_start_time;

			$callback_hash = md5( serialize( $this->tick_callback . $this->tick_location ) ); // phpcs:ignore
			if ( ! isset( $this->loggers[ $callback_hash ] ) ) {
				$this->loggers[ $callback_hash ] = [
					'callback'     => $this->tick_callback,
					'location'     => $this->tick_location,
					'time'         => 0,
					'query_time'   => 0,
					'query_count'  => 0,
					'cache_hits'   => 0,
					'cache_misses' => 0,
					'cache_ratio'  => null,
				];
			}

			$this->loggers[ $callback_hash ]['time'] += $time;

			if ( isset( $wpdb ) ) {
				$total_queries = count( $wpdb->queries );
				for ( $i = $this->tick_query_offset; $i < $total_queries; $i++ ) {
					$this->loggers[ $callback_hash ]['query_time'] += $wpdb->queries[ $i ][1];
					$this->loggers[ $callback_hash ]['query_count']++;
				}
			}

			if ( isset( $wp_object_cache ) ) {
				$hits   = ! empty( $wp_object_cache->cache_hits ) ? $wp_object_cache->cache_hits : 0;
				$misses = ! empty( $wp_object_cache->cache_misses ) ? $wp_object_cache->cache_misses : 0;
				$this->loggers[ $callback_hash ]['cache_hits']   = ( $hits - $this->tick_cache_hit_offset ) + $this->loggers[ $callback_hash ]['cache_hits'];
				$this->loggers[ $callback_hash ]['cache_misses'] = ( $misses - $this->tick_cache_miss_offset ) + $this->loggers[ $callback_hash ]['cache_misses'];
				$total = $this->loggers[ $callback_hash ]['cache_hits'] + $this->loggers[ $callback_hash ]['cache_misses'];
				if ( $total ) {
					$ratio = ( $this->loggers[ $callback_hash ]['cache_hits'] / $total ) * 100;
					$this->loggers[ $callback_hash ]['cache_ratio'] = round( $ratio, 2 ) . '%';
				}
			}
		}

		$bt    = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, 2 );
		$frame = $bt[0];
		if ( isset( $bt[1] ) ) {
			$frame = $bt[1];
		}

		$location = '';
		$callback = '';
		if ( in_array( strtolower( $frame['function'] ), [ 'include', 'require', 'include_once', 'require_once' ], true ) ) {
			$callback = $frame['function'] . " '" . $frame['args'][0] . "'";
		} elseif ( isset( $frame['object'] ) && method_exists( $frame['object'], $frame['function'] ) ) {
			$callback = get_class( $frame['object'] ) . '->' . $frame['function'] . '()';
		} elseif ( isset( $frame['class'] ) && method_exists( $frame['class'], $frame['function'] ) ) {
			$callback = $frame['class'] . '::' . $frame['function'] . '()';
		} elseif ( ! empty( $frame['function'] ) && function_exists( $frame['function'] ) ) {
			$callback = $frame['function'] . '()';
		} elseif ( '__lambda_func' === $frame['function'] || '{closure}' === $frame['function'] ) {
			$callback = 'function(){}';
		}

		if ( 'WP_CLI\Profile\Profiler->wp_tick_profile_begin()' === $callback ) {
			$this->tick_callback = null;
			return;
		}

		if ( isset( $frame['file'] ) ) {
			$location = $frame['file'];
			if ( isset( $frame['line'] ) ) {
				$location .= ':' . $frame['line'];
			}
		}

		$this->tick_callback          = $callback;
		$this->tick_location          = $location;
		$this->tick_start_time        = microtime( true );
		$this->tick_query_offset      = ! empty( $wpdb->queries ) ? count( $wpdb->queries ) : 0;
		$this->tick_cache_hit_offset  = ! empty( $wp_object_cache->cache_hits ) ? $wp_object_cache->cache_hits : 0;
		$this->tick_cache_miss_offset = ! empty( $wp_object_cache->cache_misses ) ? $wp_object_cache->cache_misses : 0;
	}

	/**
	 * Profiling request time for any active Loggers.
	 */
	public function wp_request_begin( $filter_value = null ) {
		foreach ( Logger::$active_loggers as $logger ) {
			$logger->start_request_timer();
		}
		return $filter_value;
	}

	/**
	 * Profiling request time for any active Loggers.
	 */
	public function wp_request_end( $filter_value = null ) {
		foreach ( Logger::$active_loggers as $logger ) {
			$logger->stop_request_timer();
		}
		return $filter_value;
	}

	/**
	 * Runs through the entirety of the WP bootstrap process.
	 */
	private function load_wordpress_with_template() {

		// WordPress already ran once.
		if ( function_exists( 'add_filter' ) ) {
			return;
		}

		if ( 'stage' === $this->type && true === $this->focus ) {
			$hooks = [];
			foreach ( $this->stage_hooks as $stage_hook ) {
				$hooks = array_merge( $hooks, $stage_hook );
			}
			$this->set_stage_hooks( $hooks );
		}

		if ( 'stage' === $this->type ) {
			if ( 'bootstrap' === $this->focus ) {
				$this->set_stage_hooks( $this->stage_hooks['bootstrap'] );
			} elseif ( ! $this->focus ) {
				$logger = new Logger( [ 'stage' => 'bootstrap' ] );
				$logger->start();
			}
		}
		WP_CLI::get_runner()->load_wordpress();
		if ( $this->running_hook ) {
			$this->loggers[ $this->running_hook ]->stop();
			$this->running_hook = null;
		}
		if ( 'stage' === $this->type && ! $this->focus ) {
			$logger->stop();
			$this->loggers[] = $logger;
		}

		// Set up main_query main WordPress query.
		if ( 'stage' === $this->type ) {
			if ( 'main_query' === $this->focus ) {
				$this->set_stage_hooks( $this->stage_hooks['main_query'] );
			} elseif ( ! $this->focus ) {
				$logger = new Logger( [ 'stage' => 'main_query' ] );
				$logger->start();
			}
		}
		wp();
		if ( $this->running_hook ) {
			$this->loggers[ $this->running_hook ]->stop();
			$this->running_hook = null;
		}
		if ( 'stage' === $this->type && ! $this->focus ) {
			$logger->stop();
			$this->loggers[] = $logger;
		}

		define( 'WP_USE_THEMES', true );

		// Template is normally loaded in global scope, so we need to replicate
		foreach ( $GLOBALS as $key => $value ) {
			global ${$key}; // phpcs:ignore
			// PHPCompatibility.PHP.ForbiddenGlobalVariableVariable.NonBareVariableFound -- Syntax is updated to compatible with php 5 and 7.
		}

		// Load the theme template.
		if ( 'stage' === $this->type ) {
			if ( 'template' === $this->focus ) {
				$this->set_stage_hooks( $this->stage_hooks['template'] );
			} elseif ( ! $this->focus ) {
				$logger = new Logger( [ 'stage' => 'template' ] );
				$logger->start();
			}
		}
		ob_start();
		require_once ABSPATH . WPINC . '/template-loader.php';
		ob_get_clean();
		if ( $this->running_hook ) {
			$this->loggers[ $this->running_hook ]->stop();
			$this->running_hook = null;
		}
		if ( 'stage' === $this->type && ! $this->focus ) {
			$logger->stop();
			$this->loggers[] = $logger;
		}

	}

	/**
	 * Get a human-readable name from a callback.
	 */
	private static function get_name_location_from_callback( $callback ) {
		$location   = '';
		$name       = '';
		$reflection = false;
		if ( is_array( $callback ) && is_object( $callback[0] ) ) {
			$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			$name       = get_class( $callback[0] ) . '->' . $callback[1] . '()';
		} elseif ( is_array( $callback ) && method_exists( $callback[0], $callback[1] ) ) {
			$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			$name       = $callback[0] . '::' . $callback[1] . '()';
		} elseif ( is_object( $callback ) && is_a( $callback, 'Closure' ) ) {
			$reflection = new \ReflectionFunction( $callback );
			$name       = 'function(){}';
		} elseif ( is_string( $callback ) && function_exists( $callback ) ) {
			$reflection = new \ReflectionFunction( $callback );
			$name       = $callback . '()';
		}
		if ( $reflection ) {
			$location = $reflection->getFileName() . ':' . $reflection->getStartLine();
		}
		return [ $name, $location ];
	}

	/**
	 * Get the short location from the full location.
	 *
	 * @param string $location
	 * @return string
	 */
	private static function get_short_location( $location ) {
		$abspath = rtrim( realpath( ABSPATH ), '/' ) . '/';
		if ( defined( 'WP_PLUGIN_DIR' ) && 0 === stripos( $location, WP_PLUGIN_DIR ) ) {
			$location = str_replace( trailingslashit( WP_PLUGIN_DIR ), '', $location );
		} elseif ( defined( 'WPMU_PLUGIN_DIR' ) && 0 === stripos( $location, WPMU_PLUGIN_DIR ) ) {
			$location = str_replace( trailingslashit( dirname( WPMU_PLUGIN_DIR ) ), '', $location );
		} elseif ( function_exists( 'get_theme_root' ) && 0 === stripos( $location, get_theme_root() ) ) {
			$location = str_replace( trailingslashit( get_theme_root() ), '', $location );
		} elseif ( 0 === stripos( $location, $abspath . 'wp-admin/' ) ) {
			$location = str_replace( $abspath, '', $location );
		} elseif ( 0 === stripos( $location, $abspath . 'wp-includes/' ) ) {
			$location = str_replace( $abspath, '', $location );
		}
		return $location;
	}

	/**
	 * Set the hooks for the current stage.
	 */
	private function set_stage_hooks( $hooks ) {
		$this->current_stage_hooks     = $hooks;
		$pseudo_hook                   = "{$hooks[0]}:before";
		$this->loggers[ $pseudo_hook ] = new Logger( [ 'hook' => $pseudo_hook ] );
		$this->loggers[ $pseudo_hook ]->start();
	}

	/**
	 * Get the callbacks for a given filter.
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
	 * Set the callbacks for a given filter.
	 *
	 * @param string $filter
	 * @param mixed $callbacks
	 */
	private static function set_filter_callbacks( $filter, $callbacks ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $filter ] ) && class_exists( 'WP_Hook' ) ) {
			$wp_filter[ $filter ] = new \WP_Hook(); // phpcs:ignore
		}

		if ( is_a( $wp_filter[ $filter ], 'WP_Hook' ) ) {
			$wp_filter[ $filter ]->callbacks = $callbacks;
		} else {
			$wp_filter[ $filter ] = $callbacks; // phpcs:ignore
		}
	}

	protected function configure_wordpress_context() {
		WP_CLI::add_wp_hook(
			'muplugins_loaded',
			static function () {
				$url = WP_CLI::get_runner()->config['url'];
				if ( ! empty( $url ) ) {
					WP_CLI::set_url( trailingslashit( $url ) );
				} else {
					WP_CLI::set_url( home_url( '/' ) );
				}
			}
		);
	}

	protected function enable_savequeries() {
		WP_CLI::add_hook(
			'after_wp_config_load',
			static function () {
				if ( defined( 'SAVEQUERIES' ) && ! SAVEQUERIES ) {
					WP_CLI::error( "'SAVEQUERIES' is defined as false, and must be true. Please check your wp-config.php" );
				}
				if ( ! defined( 'SAVEQUERIES' ) ) {
					define( 'SAVEQUERIES', true );
				}
			}
		);
	}

}
