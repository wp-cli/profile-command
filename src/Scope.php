<?php

namespace WP_CLI\Profile;

/**
 * Scope for which to collect data.
 */
interface Scope {

	const TYPE_ALL_HOOKS = 'all_hooks';
	const TYPE_HOOK      = 'hook';
	const TYPE_STAGE     = 'stage';

	/**
	 * Get the type of the scope.
	 *
	 * @return string Type of the scope.
	 */
	public function get_type();

	/**
	 * Check if the scope includes a given hook.
	 *
	 * @param string $hook Hook to check.
	 *
	 * @return bool Whether the hook is included in the scope.
	 */
	public function includes_hook( $hook );
}
