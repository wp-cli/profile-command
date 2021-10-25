<?php

namespace WP_CLI\Profile;

use WP_CLI;
use WP_Hook;

final class NewProfiler {

	const DEFAULT_COLLECTORS = [
		Collector\ExecutionTime::class,
		Collector\DatabaseQueries::class,
		Collector\HttpRequests::class,
	];

	/**
	 * Array of instantiated collectors.
	 *
	 * @var array<Collector>
	 */
	private $collectors = [];

	/**
	 * Scope to profile.
	 *
	 * @var Scope
	 */
	private $scope;

	/**
	 * Current depth of the hook.
	 *
	 * @var int
	 */
	private $hook_depth = 0;

	/**
	 * Instantiate a profiler instance.
	 *
	 * @param Scope $scope Scope to profile.
	 */
	public function __construct( Scope $scope ) {
		$this->scope = $scope;
	}

	/**
	 * Execute a profile run.
	 */
	public function profile() {
		$this->collectors = $this->register_collectors();

		WP_CLI::add_wp_hook( 'all', [ $this, 'wp_hook_begin' ] );

		$this->run_wordpress();
	}

	/**
	 * Profiling verbosity at the beginning of every action and filter.
	 */
	public function wp_hook_begin() {
		$hook = current_filter();
		if ( $this->scope->includes_hook( $hook ) ) {
			$this->start_hook_collectors( $hook );
		}

		if ( 0 === $this->hook_depth
		     && ! is_null( $this->previous_filter_callbacks ) ) {
			$this->set_hook_callbacks( $this->previous_filter, $this->previous_filter_callbacks );
			$this->previous_filter_callbacks = null;
		}

		if ( 0 === $this->hook_depth
		     && $this->scope->includes_hook( $hook ) ) {
			$this->wrap_hook_callbacks( $hook );
		}

		$this->hook_depth++;

		WP_CLI::add_wp_hook( $hook, [ $this, 'wp_hook_end' ], PHP_INT_MAX );
	}

	/**
	 * Profiling verbosity at the end of every action and filter.
	 */
	public function wp_hook_end( $filter_value = null ) {
		$hook = current_filter();

		$this->stop_hook_collectors( $hook );

		$this->hook_depth--;

		return $filter_value;
	}

	/**
	 * Get an aggregated report of all collectors.
	 *
	 * @return Report Aggregated report of all collectors.
	 */
	public function report() {
		$reports = [];

		foreach ( $this->collectors as $collector ) {
			$reports[] = $collector->report();
		}

		return new Report\Aggregated( $reports );
	}

	/**
	 * Register a set of collectors based on their classes.
	 *
	 * @return array<Collector> Array of collectors that were registered.
	 */
	private function register_collectors() {
		$collectors = [];

		foreach ( self::DEFAULT_COLLECTORS as $collector_class ) {
			$collector = $this->instantiate_collector_class( $collector_class );
			$collector->register( $this->scope );
			$collectors[] = $collector;
		}

		return $collectors;
	}

	/**
	 * Instantiate a collector class.
	 *
	 * @param string $collector_class Collector class to instantiate.
	 *
	 * @return Collector Instantiated collector.
	 */
	private function instantiate_collector_class( $collector_class ) {
		return new $collector_class();
	}

	/**
	 * Runs through the entirety of the WP bootstrap process.
	 */
	private function run_wordpress() {
		// WordPress already ran once.
		if ( function_exists( 'add_filter' ) ) {
			return;
		}

		WP_CLI::get_runner()->load_wordpress();

		wp();

		define( 'WP_USE_THEMES', true );

		// Template is normally loaded in global scope, so we need to replicate
		foreach ( $GLOBALS as $key => $value ) {
			global ${$key}; // phpcs:ignore
			// PHPCompatibility.PHP.ForbiddenGlobalVariableVariable.NonBareVariableFound -- Syntax is updated to compatible with php 5 and 7.
		}

		ob_start();
		require_once ABSPATH . WPINC . '/template-loader.php';
		ob_get_clean();
	}

	/**
	 * Wrap hook callbacks with a timer.
	 */
	private function wrap_hook_callbacks( $hook ) {
		$callbacks = $this->get_hook_callbacks( $hook );

		if ( false === $callbacks ) {
			return;
		}

		foreach ( $callbacks as $priority => $priority_callbacks ) {
			foreach ( $priority_callbacks as $index => $callback ) {
				$callbacks[ $priority ][ $index ] = [
					'function'      => function ( ...$args ) use ( $hook, $callback, $index ) {
						var_dump( $index );
						$this->start_callback_collectors( $hook, $callback, $index );
						$value = $callback['function']( ...$args );
						$this->stop_callback_collectors( $hook, $callback, $index );
						return $value;
					},
					'accepted_args' => $callback['accepted_args'],
				];
			}
		}

		$this->set_hook_callbacks( $hook, $callbacks );
	}

	private function start_hook_collectors( $hook ) {
		foreach ( $this->collectors as $collector ) {
			$collector->start_hook( $hook );
		}
	}

	private function stop_hook_collectors( $hook ) {
		foreach ( $this->collectors as $collector ) {
			$collector->stop_hook( $hook );
		}
	}

	private function start_callback_collectors( $hook, $callback, $index ) {
		foreach ( $this->collectors as $collector ) {
			$collector->start_callback( $hook, $callback, $index );
		}
	}

	private function stop_callback_collectors( $hook, $callback, $index ) {
		foreach ( $this->collectors as $collector ) {
			$collector->stop_callback( $hook, $callback, $index );
		}
	}

	/**
	 * Get the callbacks for a given hook.
	 *
	 * @param string $hook Hook to get the callbacks for.
	 * @return array|false Array of callbacks, or false if the hook is unknown.
	 */
	private function get_hook_callbacks( $hook ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return false;
		}

		$callbacks = $wp_filter[ $hook ] instanceof WP_Hook
			? $wp_filter[ $hook ]->callbacks
			: $wp_filter[ $hook ];

		if ( is_array( $callbacks ) ) {
			return $callbacks;
		}

		return false;
	}


	/**
	 * Set the callbacks for a given hook.
	 *
	 * @param string $hook      Hook to set the callbacks for.
	 * @param mixed  $callbacks Callbacks to set for the hook.
	 */
	private function set_hook_callbacks( $hook, $callbacks ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) && class_exists( 'WP_Hook' ) ) {
			$wp_filter[ $hook ] = new WP_Hook();
		}

		if ( $wp_filter[ $hook ] instanceof WP_Hook ) {
			$wp_filter[ $hook ]->callbacks = $callbacks;
		} else {
			$wp_filter[ $hook ] = $callbacks;
		}
	}
}
