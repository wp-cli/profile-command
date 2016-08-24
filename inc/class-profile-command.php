<?php

/**
 * Profile the performance of a WordPress request.
 */
class Profile_Command {

	private $hook_start_time = 0;
	private $hook_time = 0;
	private $scope_log;
	private $hook_log = array();
	private $current_scope;
	private $focus_scope = null;
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
	 * +------------+----------------+-------------+------------+------------+-----------+
	 * | scope      | execution_time | query_count | query_time | hook_count | hook_time |
	 * +------------+----------------+-------------+------------+------------+-----------+
	 * | total      | 2.6685s        | 196         | 0.0274s    | 10723      | 0.2173s   |
	 * | bootstrap  | 2.2609s        | 15          | 0.0037s    | 2836       | 0.1166s   |
	 * | main_query | 0.0126s        | 3           | 0.0004s    | 78         | 0.0014s   |
	 * | template   | 0.3941s        | 178         | 0.0234s    | 7809       | 0.0993s   |
	 * +------------+----------------+-------------+------------+------------+-----------+
	 * ```
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : Execute a request against a specified URL. Defaults to the home URL.
	 *
	 * [--focus=<scope>]
	 * : Focus profiling on a particular scope.
	 * ---
	 * options:
	 *   - bootstrap
	 *   - main_query
	 *   - template
	 * ---
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

		$this->focus_scope = WP_CLI\Utils\get_flag_value( $assoc_args, 'focus' );

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

		if ( $this->focus_scope ) {
			$hook_fields = array(
				'hook',
				'call_count',
				'execution_time',
				'query_count',
				'query_time',
			);
			foreach( $this->hook_log as $hook => $data ) {
				foreach( $data as $key => $value ) {
					// Round times to 4 decimal points
					if ( stripos( $key,'_time' ) ) {
						$this->hook_log[ $hook ][ $key ] = round( $value, 4 ) . 's';
					}
				}
			}
			$formatter = new \WP_CLI\Formatter( $assoc_args, $hook_fields );
			$formatter->display_items( $this->hook_log );
		} else {
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
	}

	/**
	 * Profiling verbosity at the beginning of every action and filter
	 */
	public function wp_hook_begin() {
		$this->scope_log['total']['hook_count']++;
		$this->scope_log[ $this->current_scope ]['hook_count']++;
		$this->hook_start_time = microtime( true );
		if ( $this->focus_scope && $this->focus_scope === $this->current_scope ) {
			$current_filter = current_filter();
			if ( ! isset( $this->hook_log[ $current_filter ] ) ) {
				$this->hook_log[ $current_filter ] = array(
					'hook'            => $current_filter,
					'call_count'      => 0,
					'execution_time'  => 0,
					'query_count'     => 0,
					'query_time'      => 0,
				);
			}
			$this->hook_log[ $current_filter ]['call_count']++;
		}
		WP_CLI::add_wp_hook( current_filter(), array( $this, 'wp_hook_end' ), 999 );
	}

	/**
	 * Profiling verbosity at the end of every action and filter
	 */
	public function wp_hook_end( $filter_value = null ) {

		$this->hook_time += microtime( true ) - $this->hook_start_time;
		if ( $this->focus_scope && $this->focus_scope === $this->current_scope ) {
			$current_filter = current_filter();
			$this->hook_log[ $current_filter ]['execution_time'] += microtime( true ) - $this->hook_start_time;
		}
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
