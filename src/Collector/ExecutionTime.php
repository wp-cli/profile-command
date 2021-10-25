<?php

namespace WP_CLI\Profile\Collector;

use RuntimeException;
use WP_CLI;
use WP_CLI\Profile\Collector;
use WP_CLI\Profile\Report;
use WP_CLI\Profile\Scope;

/**
 * Collect execution time for the requested scope.
 */
final class ExecutionTime implements Collector {

	/**
	 * Internal storage for collected data.
	 *
	 * @var array
	 */
	private $data = [
		'hook' => [],
	];

	/**
	 * Previous hook that was collected.
	 *
	 * @var string
	 */
	private $previous_hook;

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
					"Trying to register unsupported scope {$scope->get_type()} in ExecutionTime collector"
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
		$this->record_hook_metric(
			$hook,
			'start_time',
			microtime( true )
		);
	}

	/**
	 * Stop the collection for a given hook.
	 *
	 * @param string $hook Hook to stop the collection for.
	 *
	 * @return void
	 */
	public function stop_hook( $hook ) {
		$this->record_hook_metric(
			$hook,
			'end_time',
			microtime( true )
		);
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
		$this->record_callback_metric(
			$hook,
			$callback,
			'start_time',
			microtime( true )
		);
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
		$this->record_callback_metric(
			$hook,
			$callback,
			'end_time',
			microtime( true )
		);
	}

	/**
	 * Report the collected data.
	 *
	 * @return Report
	 */
	public function report() {
		var_dump( $this->data );
		return new Report\ExecutionTime( $this->data );
	}

	/**
	 * Record a metric for a hook.
	 *
	 * @param string $hook   Hook to collect the metric fo.
	 * @param string $metric Metric to collect.
	 * @param mixed  $value  Value to collect.
	 */
	private function record_hook_metric( $hook, $metric, $value ) {
		$this->add_array_keys_as_needed( $this->data, [ 'hooks', $hook ] );

		$this->data['hooks'][ $hook ][ $metric ] = $value;
	}

	/**
	 * Record a metric for a callback.
	 *
	 * @param string   $hook     Hook to collect the metric fo.
	 * @param callable $callback Callback to collect the metric for.
	 * @param string   $metric   Metric to collect.
	 * @param mixed    $value    Value to collect.
	 */
	private function record_callback_metric( $hook, $callback, $metric, $value ) {
		if ( is_callable( $callback ) ) {
			$callback = $this->get_callback_hash( $callback );
		}

		if ( is_array( $callback ) ) {
			$callback = $this->get_array_hash( $callback );
		}

		$this->add_array_keys_as_needed(
			$this->data,
			[ 'hooks', $hook, 'callbacks', $callback ]
		);

		$this->data['hooks'][ $hook ]['callbacks'][ $callback ][ $metric ] = $value;
	}

	/**
	 * Add missing array keys as needed for several levels in one go.
	 *
	 * @param array         $array Array to set the missing keys for.
	 * @param array<string> $keys  Array of keys to add if missing.
	 */
	private function add_array_keys_as_needed( &$array, $keys ) {
		while ( count( $keys ) > 0) {
			$key = array_shift( $keys );

			if ( ! isset( $array[ $key ]) || ! is_array( $array[ $key ] ) ) {
				$array[ $key ] = [];
			}

			$array =& $array[ $key ];
		}
	}

	private function get_callback_hash( callable $callback ) {
		return 5;
	}

	private function get_array_hash( array $callback ) {
		var_dump( array_keys( $callback ) );
	}
}
