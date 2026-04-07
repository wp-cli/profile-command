<?php

namespace WP_CLI\Profile;

use WP_CLI;

class Profiler {

	/** @var string|false */
	private $type;
	/** @var string|bool|null */
	private $focus;
	/** @var array<string|int, \WP_CLI\Profile\Logger|array<string, mixed>> */
	private $loggers = array();
	/** @var array<string, array<string>> */
	private $stage_hooks = array(
		'bootstrap'  => array(
			'muplugins_loaded',
			'plugins_loaded',
			'setup_theme',
			'after_setup_theme',
			'init',
			'wp_loaded',
		),
		'main_query' => array(
			'parse_request',
			'send_headers',
			'pre_get_posts',
			'the_posts',
			'wp',
		),
		'template'   => array(
			'template_redirect',
			'template_include',
			'wp_head',
			'loop_start',
			'loop_end',
			'wp_footer',
		),
	);

	/** @var array<string> */
	private $current_stage_hooks = array();
	/** @var string|null */
	private $running_hook = null;
	/** @var string|null */
	private $previous_filter = null;
	/** @var array<mixed>|null */
	private $previous_filter_callbacks = null;
	/** @var int */
	private $filter_depth = 0;

	/** @var string|null */
	private $tick_callback = null;
	/** @var string|null */
	private $tick_location = null;
	/** @var float|null */
	private $tick_start_time = null;
	/** @var int|null */
	private $tick_query_offset = null;
	/** @var int|null */
	private $tick_cache_hit_offset = null;
	/** @var int|null */
	private $tick_cache_miss_offset = null;

	/** @var bool */
	private $is_admin_request = false;

	/**
	 * Profiler constructor.
	 *
	 * @param string|false     $type
	 * @param string|bool|null $focus
	 */
	public function __construct( $type, $focus ) {
		$this->type  = $type;
		$this->focus = $focus;
	}

	/**
	 * Get the loggers.
	 *
	 * @return array<\WP_CLI\Profile\Logger>
	 */
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
		/** @var array<\WP_CLI\Profile\Logger> $loggers */
		$loggers = $this->loggers;
		return $loggers;
	}

	/**
	 * Run the profiler against WordPress
	 *
	 * @return void
	 */
	public function run() {
		$url  = WP_CLI::get_runner()->config['url'];
		$path = '';
		if ( ! empty( $url ) ) {
			$parsed_url = WP_CLI\Utils\parse_url( $url );
			if ( false !== $parsed_url && isset( $parsed_url['path'] ) ) {
				$path = $parsed_url['path'];
			} else {
				// Fallback for cases where $url is just a path.
				$path = $url;
			}
		}
		$this->is_admin_request = ! empty( $path ) && (bool) preg_match( '#/wp-admin(/|$|\?)#i', $path );

		if ( $this->is_admin_request && 'admin' !== WP_CLI::get_runner()->config['context'] ) {
			WP_CLI::error( 'Profiling an admin URL requires --context=admin.' );
		}

		WP_CLI::add_wp_hook(
			'muplugins_loaded',
			function () {
				$url = WP_CLI::get_runner()->config['url'];
				if ( ! empty( $url ) ) {
					WP_CLI::set_url( trailingslashit( $url ) );
				} else {
					WP_CLI::set_url( home_url( '/' ) );
				}
			}
		);
		WP_CLI::add_hook(
			'after_wp_config_load',
			function () {
				if ( defined( 'SAVEQUERIES' ) && ! SAVEQUERIES ) {
					WP_CLI::error( "'SAVEQUERIES' is defined as false, and must be true. Please check your wp-config.php" );
				}
				if ( ! defined( 'SAVEQUERIES' ) ) {
					define( 'SAVEQUERIES', true );
				}
			}
		);
		if (
			'hook' === $this->type &&
			is_string( $this->focus ) &&
			':before' === substr( $this->focus, -7, 7 )
		) {
			$stage_hooks = array();
			foreach ( $this->stage_hooks as $hooks ) {
				$stage_hooks = array_merge( $stage_hooks, $hooks );
			}
			$end_hook = substr( $this->focus, 0, -7 );
			$key      = array_search( $end_hook, $stage_hooks, true );
			if ( is_int( $key ) && isset( $stage_hooks[ $key - 1 ] ) ) {
				$start_hook = $stage_hooks[ $key - 1 ];
				WP_CLI::add_wp_hook( $start_hook, array( $this, 'wp_tick_profile_begin' ), 9999 );
			} else {
				WP_CLI::add_hook( 'after_wp_config_load', array( $this, 'wp_tick_profile_begin' ) );
			}
			WP_CLI::add_wp_hook( $end_hook, array( $this, 'wp_tick_profile_end' ), -9999 );
		} elseif (
			'hook' === $this->type &&
			is_string( $this->focus ) &&
			':after' === substr( $this->focus, -6, 6 )
		) {
			$start_hook = substr( $this->focus, 0, -6 );
			WP_CLI::add_wp_hook( $start_hook, array( $this, 'wp_tick_profile_begin' ), 9999 );
		} else {
			WP_CLI::add_wp_hook( 'all', array( $this, 'wp_hook_begin' ) );
		}
		WP_CLI::add_wp_hook( 'pre_http_request', array( $this, 'wp_request_begin' ) );
		WP_CLI::add_wp_hook( 'http_api_debug', array( $this, 'wp_request_end' ) );
		$this->load_wordpress_with_template();
	}

	/**
	 * Start profiling function calls on the end of this filter
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function wp_tick_profile_begin( $value = null ) {

		if ( version_compare( PHP_VERSION, '7.0.0' ) >= 0 ) {
			WP_CLI::error( 'Profiling intermediate hooks is broken in PHP 7, see https://bugs.php.net/bug.php?id=72966' );
		}

		// Disable opcode optimizers.  These "optimize" calls out of the stack
		// and hide calls from the tick handler and backtraces.
		// Copied from P3 Profiler
		if ( extension_loaded( 'xcache' ) ) {
			@ini_set( 'xcache.optimizer', false ); // phpcs:ignore
			// WordPress.PHP.NoSilencedErrors.Discouraged -- ini_set can be disabled on server.
		} elseif ( extension_loaded( 'apc' ) ) {
			@ini_set( 'apc.optimization', 0 ); // phpcs:ignore
			// WordPress.PHP.NoSilencedErrors.Discouraged -- ini_set can be disabled on server.
			apc_clear_cache();
		} elseif ( extension_loaded( 'eaccelerator' ) ) {
			@ini_set( 'eaccelerator.optimizer', 0 ); // phpcs:ignore
			// WordPress.PHP.NoSilencedErrors.Discouraged -- ini_set can be disabled on server.
			if ( function_exists( 'eaccelerator_optimizer' ) ) {
				@eaccelerator_optimizer( false ); // phpcs:ignore
				// WordPress.PHP.NoSilencedErrors.Discouraged -- disabling eaccelerator on runtime can faild
			}
		} elseif ( extension_loaded( 'Zend Optimizer+' ) ) {
			@ini_set( 'zend_optimizerplus.optimization_level', 0 ); // phpcs:ignore
			// WordPress.PHP.NoSilencedErrors.Discouraged -- ini_set can be disabled on server.
		}

		register_tick_function( array( $this, 'handle_function_tick' ) );
		declare( ticks = 1 );
		return $value;
	}

	/**
	 * Stop profiling function calls at the beginning of this filter
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function wp_tick_profile_end( $value = null ) {
		unregister_tick_function( array( $this, 'handle_function_tick' ) );
		$this->tick_callback = null;
		return $value;
	}

	/**
	 * Profiling verbosity at the beginning of every action and filter
	 *
	 * @return void
	 */
	public function wp_hook_begin() {

		foreach ( Logger::$active_loggers as $logger ) {
			$logger->start_hook_timer();
		}

		$current_filter = current_filter();
		if ( ! is_string( $current_filter ) ) {
			return;
		}
		if ( ( 'stage' === $this->type && in_array( $current_filter, $this->current_stage_hooks, true ) )
			|| ( 'hook' === $this->type && ! $this->focus ) ) {
			$pseudo_hook = "{$current_filter}:before";
			if ( isset( $this->loggers[ $pseudo_hook ] ) ) {
				assert( $this->loggers[ $pseudo_hook ] instanceof Logger );
				$this->loggers[ $pseudo_hook ]->stop();
			}
			$callback_count = 0;
			$callbacks      = self::get_filter_callbacks( $current_filter );
			if ( false !== $callbacks ) {
				foreach ( $callbacks as $priority => $cbs ) {
					if ( is_array( $cbs ) ) {
						$callback_count += count( $cbs );
					}
				}
			}
			$this->loggers[ $current_filter ] = new Logger(
				array(
					'hook'           => $current_filter,
					'callback_count' => $callback_count,
				)
			);
			$this->loggers[ $current_filter ]->start();
		}

		if ( 0 === $this->filter_depth
			&& ! is_null( $this->previous_filter_callbacks )
			&& ! is_null( $this->previous_filter ) ) {
			self::set_filter_callbacks( $this->previous_filter, $this->previous_filter_callbacks );
			$this->previous_filter_callbacks = null;
		}

		if ( 'hook' === $this->type
			&& 0 === $this->filter_depth
			&& ( $current_filter === $this->focus || true === $this->focus ) ) {
			$this->wrap_current_filter_callbacks( $current_filter );
		}

		++$this->filter_depth;

		WP_CLI::add_wp_hook( $current_filter, array( $this, 'wp_hook_end' ), 9999 );
	}

	/**
	 * Wrap current filter callbacks with a timer
	 *
	 * @param string $current_filter
	 * @return void
	 */
	private function wrap_current_filter_callbacks( $current_filter ) {

		$callbacks = self::get_filter_callbacks( $current_filter );
		if ( false === $callbacks ) {
			return;
		}
		$this->previous_filter           = $current_filter;
		$this->previous_filter_callbacks = $callbacks;

		foreach ( $callbacks as $priority => $priority_callbacks ) {
			if ( is_array( $priority_callbacks ) ) {
				$new_priority_callbacks = $priority_callbacks;
				foreach ( $priority_callbacks as $i => $the_ ) {
					if ( is_array( $the_ ) && isset( $the_['function'] ) && isset( $the_['accepted_args'] ) ) {
						$func                         = $the_['function'];
						$new_priority_callbacks[ $i ] = array(
							'function'      => function () use ( $func, $i ) {
								if ( ! isset( $this->loggers[ $i ] ) ) {
									$this->loggers[ $i ] = new Logger(
										array(
											'callback' => $func,
										)
									);
								}
								assert( $this->loggers[ $i ] instanceof Logger );
								$this->loggers[ $i ]->start();

								$args = func_get_args();
								if ( is_callable( $func ) ) {
									$value = call_user_func_array( $func, $args );
								} else {
									$value = null;
								}

								$this->loggers[ $i ]->stop();
								return $value;
							},
							'accepted_args' => $the_['accepted_args'],
						);
					}
				}
				$callbacks[ $priority ] = $new_priority_callbacks;
			}
		}
		self::set_filter_callbacks( $current_filter, $callbacks );
	}

	/**
	 * Profiling verbosity at the end of every action and filter
	 *
	 * @param mixed $filter_value
	 * @return mixed
	 */
	public function wp_hook_end( $filter_value = null ) {

		foreach ( Logger::$active_loggers as $logger ) {
			$logger->stop_hook_timer();
		}

		$current_filter = current_filter();
		if ( ( 'stage' === $this->type && in_array( $current_filter, $this->current_stage_hooks, true ) )
			|| ( 'hook' === $this->type && ! $this->focus ) ) {
			assert( $this->loggers[ $current_filter ] instanceof Logger );
			$this->loggers[ $current_filter ]->stop();
			if ( 'stage' === $this->type ) {
				$key = array_search( $current_filter, $this->current_stage_hooks, true );
				if ( is_int( $key ) && isset( $this->current_stage_hooks[ $key + 1 ] ) ) {
					$pseudo_hook = "{$this->current_stage_hooks[$key+1]}:before";
				} else {
					$pseudo_hook        = "{$this->current_stage_hooks[$key]}:after";
					$this->running_hook = $pseudo_hook;
				}
				$this->loggers[ $pseudo_hook ] = new Logger( array( 'hook' => $pseudo_hook ) );
				$this->loggers[ $pseudo_hook ]->start();
			}
		}

		--$this->filter_depth;

		return $filter_value;
	}

	/**
	 * Handle the tick of a function
	 *
	 * @return void
	 */
	public function handle_function_tick() {
		global $wpdb, $wp_object_cache;

		if ( ! is_null( $this->tick_callback ) ) {
			$time = microtime( true ) - $this->tick_start_time;

			$callback_hash = md5( serialize( $this->tick_callback . $this->tick_location ) ); // phpcs:ignore
			if ( ! isset( $this->loggers[ $callback_hash ] ) ) {
				$this->loggers[ $callback_hash ] = array(
					'callback'     => $this->tick_callback,
					'location'     => $this->tick_location,
					'time'         => 0,
					'query_time'   => 0,
					'query_count'  => 0,
					'cache_hits'   => 0,
					'cache_misses' => 0,
					'cache_ratio'  => null,
				);
			}

			$logger_data = $this->loggers[ $callback_hash ];
			if ( is_array( $logger_data ) ) {
				$current_time        = isset( $logger_data['time'] ) && is_numeric( $logger_data['time'] ) ? $logger_data['time'] : 0.0;
				$logger_data['time'] = (float) $current_time + $time;

				if ( isset( $wpdb ) ) {
					$total_queries = count( $wpdb->queries );
					for ( $i = $this->tick_query_offset; $i < $total_queries; $i++ ) {
						$q_time                    = isset( $wpdb->queries[ $i ][1] ) ? $wpdb->queries[ $i ][1] : 0.0;
						$current_q_time            = isset( $logger_data['query_time'] ) && is_numeric( $logger_data['query_time'] ) ? $logger_data['query_time'] : 0.0;
						$q_time_val                = is_numeric( $q_time ) ? $q_time : 0.0;
						$logger_data['query_time'] = (float) $current_q_time + (float) $q_time_val;

						$current_q_count            = isset( $logger_data['query_count'] ) && is_numeric( $logger_data['query_count'] ) ? $logger_data['query_count'] : 0;
						$logger_data['query_count'] = (int) $current_q_count + 1;
					}
				}

				if ( isset( $wp_object_cache ) ) {
					$hits   = ! empty( $wp_object_cache->cache_hits ) ? $wp_object_cache->cache_hits : 0;
					$misses = ! empty( $wp_object_cache->cache_misses ) ? $wp_object_cache->cache_misses : 0;

					$current_hits              = isset( $logger_data['cache_hits'] ) && is_numeric( $logger_data['cache_hits'] ) ? $logger_data['cache_hits'] : 0;
					$logger_data['cache_hits'] = ( $hits - $this->tick_cache_hit_offset ) + (int) $current_hits;

					$current_misses              = isset( $logger_data['cache_misses'] ) && is_numeric( $logger_data['cache_misses'] ) ? $logger_data['cache_misses'] : 0;
					$logger_data['cache_misses'] = ( $misses - $this->tick_cache_miss_offset ) + (int) $current_misses;

					$total = $logger_data['cache_hits'] + $logger_data['cache_misses'];
					if ( $total ) {
						$ratio                      = ( $logger_data['cache_hits'] / $total ) * 100;
						$logger_data['cache_ratio'] = round( $ratio, 2 ) . '%';
					}
				}
				$this->loggers[ $callback_hash ] = $logger_data;
			}
		}

		$bt    = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, 2 );
		$frame = $bt[0];
		if ( isset( $bt[1] ) ) {
			$frame = $bt[1];
		}

		$location = '';
		$callback = '';
		if ( in_array( strtolower( $frame['function'] ), array( 'include', 'require', 'include_once', 'require_once' ), true ) ) {
			$callback = $frame['function'];
			if ( isset( $frame['args'] ) && is_array( $frame['args'] ) && isset( $frame['args'][0] ) && is_scalar( $frame['args'][0] ) ) {
				$callback .= " '" . (string) $frame['args'][0] . "'";
			}
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
	 * Profiling request time for any active Loggers
	 *
	 * @param mixed $filter_value
	 * @return mixed
	 */
	public function wp_request_begin( $filter_value = null ) {
		foreach ( Logger::$active_loggers as $logger ) {
			$logger->start_request_timer();
		}
		return $filter_value;
	}

	/**
	 * Profiling request time for any active Loggers
	 *
	 * @param mixed $filter_value
	 * @return mixed
	 */
	public function wp_request_end( $filter_value = null ) {
		foreach ( Logger::$active_loggers as $logger ) {
			$logger->stop_request_timer();
		}
		return $filter_value;
	}

	/**
	 * Runs through the entirety of the WP bootstrap process
	 *
	 * @return void
	 */
	private function load_wordpress_with_template() {

		// WordPress already ran once.
		if ( function_exists( 'add_filter' ) ) {
			return;
		}

		if ( 'stage' === $this->type && true === $this->focus ) {
			$hooks = array();
			foreach ( $this->stage_hooks as $stage_hook ) {
				$hooks = array_merge( $hooks, $stage_hook );
			}
			$this->set_stage_hooks( $hooks );
		}

		if ( 'stage' === $this->type ) {
			if ( 'bootstrap' === $this->focus ) {
				$this->set_stage_hooks( $this->stage_hooks['bootstrap'] );
			} elseif ( ! $this->focus ) {
				$bootstrap_logger = new Logger( array( 'stage' => 'bootstrap' ) );
				$bootstrap_logger->start();
			}
		}
		WP_CLI::get_runner()->load_wordpress();
		if ( $this->running_hook ) {
			assert( $this->loggers[ $this->running_hook ] instanceof Logger );
			$this->loggers[ $this->running_hook ]->stop();
			$this->running_hook = null;
		}
		if ( 'hook' === $this->type && 'wp_loaded:after' === $this->focus ) {
			$this->wp_tick_profile_end();
		}
		if ( 'stage' === $this->type && ! $this->focus && isset( $bootstrap_logger ) ) {
			$bootstrap_logger->stop();
			$this->loggers[] = $bootstrap_logger;
		}

		// Skip main_query and template stages for admin requests.
		if ( $this->is_admin_request ) {
			return;
		}

		// Set up main_query main WordPress query.
		if ( 'stage' === $this->type ) {
			if ( 'main_query' === $this->focus ) {
				$this->set_stage_hooks( $this->stage_hooks['main_query'] );
			} elseif ( ! $this->focus ) {
				$main_query_logger = new Logger( array( 'stage' => 'main_query' ) );
				$main_query_logger->start();
			}
		}
		wp();
		if ( $this->running_hook ) {
			assert( $this->loggers[ $this->running_hook ] instanceof Logger );
			$this->loggers[ $this->running_hook ]->stop();
			$this->running_hook = null;
		}
		if ( 'hook' === $this->type && 'wp:after' === $this->focus ) {
			$this->wp_tick_profile_end();
		}
		if ( 'stage' === $this->type && ! $this->focus && isset( $main_query_logger ) ) {
			$main_query_logger->stop();
			$this->loggers[] = $main_query_logger;
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
				$template_logger = new Logger( array( 'stage' => 'template' ) );
				$template_logger->start();
			}
		}
		ob_start();
		require_once ABSPATH . WPINC . '/template-loader.php';
		ob_get_clean();
		if ( $this->running_hook ) {
			assert( $this->loggers[ $this->running_hook ] instanceof Logger );
			$this->loggers[ $this->running_hook ]->stop();
			$this->running_hook = null;
		}
		if ( 'hook' === $this->type && 'wp_footer:after' === $this->focus ) {
			$this->wp_tick_profile_end();
		}
		if ( 'stage' === $this->type && ! $this->focus && isset( $template_logger ) ) {
			$template_logger->stop();
			$this->loggers[] = $template_logger;
		}
	}

	/**
	 * Get a human-readable name from a callback
	 *
	 * @param mixed $callback
	 * @return array{0: string, 1: string}
	 */
	private static function get_name_location_from_callback( $callback ) {
		$location   = '';
		$name       = '';
		$reflection = false;
		if ( is_array( $callback ) && is_object( $callback[0] ) ) {
			$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			$name       = get_class( $callback[0] ) . '->' . $callback[1] . '()';
		} elseif ( is_array( $callback ) && isset( $callback[0] ) && isset( $callback[1] ) && ( is_object( $callback[0] ) || is_string( $callback[0] ) ) && is_string( $callback[1] ) && method_exists( $callback[0], $callback[1] ) ) {
			$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			/** @var string $class_name */
			$class_name = $callback[0];
			$name       = $class_name . '::' . $callback[1] . '()';
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
		return array( $name, $location );
	}

	/**
	 * Get the short location from the full location
	 *
	 * @param string $location
	 * @return string
	 */
	private static function get_short_location( $location ) {
		$real_abspath = realpath( ABSPATH );
		$abspath      = rtrim( false !== $real_abspath ? $real_abspath : ABSPATH, '/' ) . '/';
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
	 * Set the hooks for the current stage
	 *
	 * @param array<string> $hooks
	 * @return void
	 */
	private function set_stage_hooks( $hooks ) {
		$this->current_stage_hooks     = $hooks;
		$pseudo_hook                   = "{$hooks[0]}:before";
		$this->loggers[ $pseudo_hook ] = new Logger( array( 'hook' => $pseudo_hook ) );
		$this->loggers[ $pseudo_hook ]->start();
	}

	/**
	 * Get the callbacks for a given filter
	 *
	 * @param string $filter
	 * @return array<mixed>|false
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
	 * @param mixed  $callbacks
	 * @return void
	 */
	private static function set_filter_callbacks( $filter, $callbacks ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $filter ] ) && class_exists( 'WP_Hook' ) ) {
			$wp_filter[ $filter ] = new \WP_Hook(); // phpcs:ignore
		}

		if ( is_a( $wp_filter[ $filter ], 'WP_Hook' ) ) {
			/** @var array<mixed> $callbacks */
			$wp_filter[ $filter ]->callbacks = $callbacks;
		} else {
			$wp_filter[ $filter ] = $callbacks; // phpcs:ignore
		}
	}
}
