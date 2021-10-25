<?php

namespace WP_CLI\Profile\Scope;

use WP_CLI\Profile\Scope;

/**
 * Collect data scoped based on a provided hook.
 */
final class Hook implements Scope {

	/**
	 * Hook to scope the data collection by.
	 *
	 * @var string
	 */
	private $hook;

	/**
	 * Instantiate a Hook object.
	 *
	 * @param string $hook Hook to scope the data collection by.
	 */
	public function __construct( $hook ) {
		$this->hook = $hook;
	}

	/**
	 * Check if the scope includes a given hook.
	 *
	 * @param string $hook Hook to check.
	 *
	 * @return bool Whether the hook is included in the scope.
	 */
	public function includes_hook( $hook ) {
		return $hook === $this->hook;
	}

	/**
	 * Get the type of the scope.
	 *
	 * @return string Type of the scope.
	 */
	public function get_type() {
		return Scope::TYPE_HOOK;
	}
}
