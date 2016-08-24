<?php

/**
 * Profile the performance of a WordPress request.
 */
class Profile_Command {

	private $hook_start_time = 0;
	private $hook_time = 0;
	private $scope_log;
	private $current_scope;
	private $query_offset = 0;
	private $hook_offset = 0;

	/**
	 * Profile the performance of a WordPress request.
	 *
	 * Monitors aspects of the WordPress execution process to display key
	 * performance indicators for audit.
	 *
	 * ```
	 * $ wp profile
	 * +------------+----------------+-------------+------------+------------+-----------+--------------+
	 * | scope      | execution_time | query_count | query_time | hook_count | hook_time | memory_usage |
	 * +------------+----------------+-------------+------------+------------+-----------+--------------+
	 * | bootstrap  | 2.34255s       | 15          | 0.00386s   | 2835       | 0.11172s  | 45mb         |
	 * | main_query | 0.01155s       | 3           | 0.0004s    | 78         | 0.00117s  | 45.75mb      |
	 * | template   | 0.32768s       | 174         | 0.0174s    | 7824       | 0.08106s  | 49.25mb      |
	 * | total      | 2.68269s       | 192         | 0.02165s   | 10737      | 0.19395s  | 49.25mb      |
	 * +------------+----------------+-------------+------------+------------+-----------+--------------+
	 * ```
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : Execute a request against a specified URL. Defaults to the home URL.
	 *
	 * [--fields=<fields>]
	 * : Display one or more fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 *   - csv
	 * ---
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;

		$this->scope_log = array();
		$scope_fields = array(
			'scope',
			'execution_time',
			'query_count',
			'query_time',
			'hook_count',
			'hook_time',
		);
		foreach( array( 'total', 'bootstrap', 'main_query', 'template' ) as $scope ) {
			$this->scope_log[ $scope ] = array();
			foreach( $scope_fields as $field ) {
				if ( 'scope' === $field ) {
					$this->scope_log[ $scope ][ $field ] = $scope;
				} else {
					$this->scope_log[ $scope ][ $field ] = 0;
				}
			}
		}

		if ( ! isset( \WP_CLI::get_runner()->config['url'] ) ) {
			WP_CLI::add_wp_hook( 'muplugins_loaded', function(){
				WP_CLI::set_url( home_url( '/' ) );
			});
		}
		if ( ! defined( 'SAVEQUERIES' ) ) {
			define( 'SAVEQUERIES', true );
		}
		WP_CLI::add_wp_hook( 'all', array( $this, 'wp_hook_begin' ) );
		$this->load_wordpress_with_template();

		foreach( $this->scope_log as $scope => $data ) {
			foreach( $data as $key => $value ) {
				// Round times to 4 decimal points
				if ( stripos( $key,'_time' ) ) {
					$this->scope_log[ $scope ][ $key ] = round( $value, 4 ) . 's';
				}
			}
		}
		$formatter = new \WP_CLI\Formatter( $assoc_args, $scope_fields );
		$formatter->display_items( $this->scope_log );
	}

	/**
	 * Profiling verbosity at the beginning of every action and filter
	 */
	public function wp_hook_begin() {
		$this->scope_log['total']['hook_count']++;
		$this->scope_log[ $this->current_scope ]['hook_count']++;
		$this->hook_start_time = microtime( true );
		WP_CLI::add_wp_hook( current_filter(), array( $this, 'wp_hook_end' ), 999 );
	}

	/**
	 * Profiling verbosity at the end of every action and filter
	 */
	public function wp_hook_end( $filter_value = null ) {

		$this->hook_time += microtime( true ) - $this->hook_start_time;
		return $filter_value;
	}

	/**
	 * Runs through the entirety of the WP bootstrap process
	 */
	private function load_wordpress_with_template() {
		global $wp_query;

		$this->scope_track_begin( 'total' );
		$this->scope_track_begin( 'bootstrap' );
		WP_CLI::get_runner()->load_wordpress();
		$this->scope_track_end( 'bootstrap' );

		// Set up the main WordPress query.
		$this->current_scope = 'main_query';
		$this->scope_track_begin( 'main_query' );
		wp();
		$this->scope_track_end( 'main_query' );

		define( 'WP_USE_THEMES', true );

		// Template is normally loaded in global scope, so we need to replicate
		foreach( $GLOBALS as $key => $value ) {
			global $$key;
		}

		// Load the theme template.
		$this->scope_track_begin( 'template' );
		ob_start();
		require_once( ABSPATH . WPINC . '/template-loader.php' );
		ob_get_clean();
		$this->scope_track_end( 'template' );
		$this->scope_track_end( 'total' );
	}

	/**
	 * Start tracking the current scope
	 */
	private function scope_track_begin( $scope ) {
		if ( 'total' !== $scope ) {
			$this->current_scope = $scope;
		}
		$this->scope_log[ $scope ]['execution_time'] = microtime( true );
		$this->hook_offset = $this->hook_time;
	}

	/**
	 * End tracking the current scope
	 */
	private function scope_track_end( $scope ) {
		global $wpdb;
		$this->scope_log[ $scope ]['execution_time'] = microtime( true ) - $this->scope_log[ $scope ]['execution_time'];
		$query_offset = 'total' === $scope ? 0 : $this->query_offset;
		for ( $i = $query_offset; $i < count( $wpdb->queries ); $i++ ) {
			$this->scope_log[ $scope ]['query_time'] += $wpdb->queries[ $i ][1];
			$this->scope_log[ $scope ]['query_count']++;
		}
		$this->query_offset = count( $wpdb->queries );
		$hook_time = 'total' === $scope ? $this->hook_time : $this->hook_time - $this->hook_offset;
		$this->scope_log[ $scope ]['hook_time'] = $hook_time;
	}

}
