<?php

namespace WP_CLI\Profile\Scope;

use InvalidArgumentException;
use WP_CLI\Profile\Scope;

/**
 * Collect data scoped based on a provided stage.
 */
final class Stage implements Scope {

	const BOOTSTRAP  = 'bootstrap';
	const MAIN_QUERY = 'main_query';
	const TEMPLATE   = 'template';

	const STAGE_HOOKS = [
		self::BOOTSTRAP  => [
			'muplugins_loaded',
			'plugins_loaded',
			'setup_theme',
			'after_setup_theme',
			'init',
			'wp_loaded',
		],
		self::MAIN_QUERY => [
			'parse_request',
			'send_headers',
			'pre_get_posts',
			'the_posts',
			'wp',
		],
		self::TEMPLATE   => [
			'template_redirect',
			'template_include',
			'wp_head',
			'loop_start',
			'loop_end',
			'wp_footer',
		],
	];

	/**
	 * Stage to scope the data collection by.
	 *
	 * @var string
	 */
	private $stage;

	/**
	 * Instantiate a Stage object.
	 *
	 * @param string $stage Stage to scope the data collection by.
	 */
	public function __construct( $stage ) {
		if ( ! array_key_exists( $stage, self::STAGE_HOOKS ) ) {
			throw new InvalidArgumentException( "Invalid stage {$stage}" );
		}

		$this->stage = $stage;
	}

	/**
	 * Get the stage to scope the collection of data by.
	 *
	 * @return string Stage to scope the data collection by.
	 */
	public function get_stage() {
		return $this->stage;
	}

	/**
	 * Check if the scope includes a given hook.
	 *
	 * @param string $hook Hook to check.
	 *
	 * @return bool Whether the hook is included in the scope.
	 */
	public function includes_hook( $hook ) {
		return array_key_exists( $hook, self::STAGE_HOOKS[ $this->stage ] );
	}

	/**
	 * Get the type of the scope.
	 *
	 * @return string Type of the scope.
	 */
	public function get_type() {
		return Scope::TYPE_STAGE;
	}
}
