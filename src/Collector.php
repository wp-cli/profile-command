<?php

namespace WP_CLI\Profile;

/**
 * The collector is responsible for collect a specific subset of data across the
 * requested scope.
 */
interface Collector {

	/**
	 * Register the collector with the WordPress lifecycle.
	 *
	 * @param Scope $scope Scope for which to collect the data.
	 *
	 * @return void
	 */
	public function register( Scope $scope );

	/**
	 * Start the collection for a given hook.
	 *
	 * @param string $hook Hook to start the collection for.
	 *
	 * @return void
	 */
	public function start_hook( $hook );

	/**
	 * Stop the collection for a given hook.
	 *
	 * @param string $hook Hook to stop the collection for.
	 *
	 * @return void
	 */
	public function stop_hook( $hook );

	/**
	 * Start the collection for a given hook and callback.
	 *
	 * @param string   $hook     Hook to start the collection for.
	 * @param callable $callback Callback to start the collection for.
	 * @param int      $index    Index of the callback.
	 *
	 * @return void
	 */
	public function start_callback( $hook, $callback, $index );

	/**
	 * Stop the collection for a given hook and callback.
	 *
	 * @param string   $hook     Hook to start the collection for.
	 * @param callable $callback Callback to start the collection for.
	 * @param int      $index    Index of the callback.
	 *
	 * @return void
	 */
	public function stop_callback( $hook, $callback, $index );
	/**
	 * Report the collected data.
	 *
	 * @return Report
	 */
	public function report();
}
