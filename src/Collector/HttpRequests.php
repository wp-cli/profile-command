<?php

namespace WP_CLI\Profile\Collector;

use RuntimeException;
use WP_CLI\Profile\Collector;
use WP_CLI\Profile\Report;
use WP_CLI\Profile\Scope;

/**
 * Collect HTTP requests for the requested scope.
 */
final class HttpRequests implements Collector {

	/**
	 * Internal storage for collected data.
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 * Register the collector with the WordPress lifecycle.
	 *
	 * @param Scope $scope Scope for which to collect the data.
	 *
	 * @return void
	 */
	public function register( Scope $scope ) {
		switch ( $scope->get_type() ) {
			case Scope::TYPE_ALL_HOOKS:
				break;
			case Scope::TYPE_HOOK:
				break;
			case Scope::TYPE_STAGE:
				break;
			default:
				throw new RuntimeException(
					"Trying to register unsupported scope {$scope->get_type()} in HttpRequests collector"
				);
		}
	}

	/**
	 * Start the collection for a given hook.
	 *
	 * @param string $hook Hook to start the collection for.
	 *
	 * @return void
	 */
	public function start_hook( $hook ) {

	}

	/**
	 * Stop the collection for a given hook.
	 *
	 * @param string $hook Hook to stop the collection for.
	 *
	 * @return void
	 */
	public function stop_hook( $hook ) {

	}

	/**
	 * Start the collection for a given hook and callback.
	 *
	 * @param string   $hook     Hook to start the collection for.
	 * @param callable $callback Callback to start the collection for.
	 * @param int      $index    Index of the callback.
	 *
	 * @return void
	 */
	public function start_callback( $hook, $callback, $index ) {

	}

	/**
	 * Stop the collection for a given hook and callback.
	 *
	 * @param string   $hook     Hook to start the collection for.
	 * @param callable $callback Callback to start the collection for.
	 * @param int      $index    Index of the callback.
	 *
	 * @return void
	 */
	public function stop_callback( $hook, $callback, $index ) {

	}

	/**
	 * Report the collected data.
	 *
	 * @return Report
	 */
	public function report() {
		return new Report\HttpRequests( $this->data );
	}
}
