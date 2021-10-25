<?php

namespace WP_CLI\Profile\Scope;

use WP_CLI\Profile\Scope;

/**
 * Collect data across all hooks.
 */
final class AllHooks implements Scope {

	/**
	 * Check if the scope includes a given hook.
	 *
	 * @param string $hook Hook to check.
	 *
	 * @return bool Whether the hook is included in the scope.
	 */
	public function includes_hook( $hook ) {
		return true;
	}

	/**
	 * Get the type of the scope.
	 *
	 * @return string Type of the scope.
	 */
	public function get_type() {
		return Scope::TYPE_ALL_HOOKS;
	}
}
