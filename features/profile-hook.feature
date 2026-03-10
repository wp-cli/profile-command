Feature: Profile a specific hook

  Scenario: Profile all hooks when a specific hook isn't specified
    Given a WP install

    When I run `wp profile hook --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook              |
      | plugins_loaded    |
      | init              |
      | template_redirect |
    And STDERR should be empty

  Scenario: Profile all callbacks when --all flag is used
    Given a WP install

    When I run `wp profile hook --all --fields=callback,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | callback                   | cache_hits | cache_misses |
      | sanitize_comment_cookies() | 0          | 0            |

  Scenario: Profile a hook before the template is loaded
    Given a WP install

    When I run `wp profile hook plugins_loaded --fields=callback`
    Then STDOUT should be a table containing rows:
      | callback          |
    And STDERR should be empty

  Scenario: Profile a hook without any callbacks
    Given a WP install

    When I run `wp profile hook get_search_form --fields=callback`
    Then STDOUT should be a table containing rows:
      | callback          |
      | total (0)         |
    And STDERR should be empty

  @require-wp-4.0
  Scenario: Profile a hook that has actions with output
    Given a WP install

    When I run `wp profile hook wp_head --fields=callback`
    Then STDOUT should be a table containing rows:
      | callback          |
    And STDOUT should not contain:
      """
      <meta name="generator"
      """

  @less-than-wp-6.9
  Scenario: Profile the shutdown hook
    Given a WP install
    And a wp-content/mu-plugins/shutdown.php file:
      """
      <?php
      function wp_cli_shutdown_hook() {
        wp_cache_get( 'foo' );
      }
      add_action( 'shutdown', 'wp_cli_shutdown_hook' );
      """

    When I run `wp profile hook shutdown --fields=callback,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | callback               | cache_hits     | cache_misses     |
      | wp_cli_shutdown_hook() | 0              | 1                |
      | wp_ob_end_flush_all()  | 0              | 0                |
      | total (2)              | 0              | 1                |
    And STDERR should be empty

  # `_wp_cron` was added to shutdown hook in 6.9, see https://core.trac.wordpress.org/changeset/60925.
  @require-wp-6.9
  Scenario: Profile the shutdown hook
    Given a WP install
    And a wp-content/mu-plugins/shutdown.php file:
      """
      <?php
      function wp_cli_shutdown_hook() {
        wp_cache_get( 'foo' );
      }
      add_action( 'shutdown', 'wp_cli_shutdown_hook' );
      """

    When I run `wp profile hook shutdown --fields=callback,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | callback               | cache_hits     | cache_misses     |
      | wp_cli_shutdown_hook() | 0              | 1                |
      | wp_ob_end_flush_all()  | 0              | 0                |
      | _wp_cron()             | 0              | 0                |
      | total (3)              | 0              | 1                |
    And STDERR should be empty

  Scenario: Indicate where a callback is defined with profiling a hook
    Given a WP install
    And a wp-content/mu-plugins/custom-action.php file:
      """
      <?php
      function wp_cli_custom_action_hook() {
        wp_cache_get( 'foo' );
      }
      add_action( 'wp_cli_custom_action', 'wp_cli_custom_action_hook' );
      do_action( 'wp_cli_custom_action' );
      """

    When I run `wp profile hook wp_cli_custom_action --fields=callback,location,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | callback                    | location                                  | cache_hits | cache_misses |
      | wp_cli_custom_action_hook() | mu-plugins/custom-action.php:2            | 0          | 1            |
      | total (1)                   |                                           | 0          | 1            |
    And STDERR should be empty

  Scenario: Search for callbacks by name pattern on a specific hook
    Given a WP install
    And a wp-content/mu-plugins/search-test.php file:
      """
      <?php
      function wp_cli_search_hook_one() {}
      function wp_cli_search_hook_two() {}
      function unrelated_callback() {}
      add_action( 'init', 'wp_cli_search_hook_one' );
      add_action( 'init', 'wp_cli_search_hook_two' );
      add_action( 'init', 'unrelated_callback' );
      """

    When I run `wp profile hook init --fields=callback --search=wp_cli_search_hook`
    Then STDOUT should contain:
      """
      wp_cli_search_hook_one()
      """
    And STDOUT should contain:
      """
      wp_cli_search_hook_two()
      """
    And STDOUT should not contain:
      """
      unrelated_callback()
      """
    And STDERR should be empty

  Scenario: Search for callbacks by name pattern across all hooks
    Given a WP install
    And a wp-content/mu-plugins/search-all-test.php file:
      """
      <?php
      function wp_cli_search_all_hook() {}
      add_action( 'init', 'wp_cli_search_all_hook' );
      """

    When I run `wp profile hook --all --fields=callback --search=wp_cli_search_all_hook`
    Then STDOUT should contain:
      """
      wp_cli_search_all_hook()
      """
    And STDERR should be empty

  Scenario: Profile an intermediate stage hook
    Given a WP install

    When I run `wp profile hook muplugins_loaded --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook            |
      | muplugins_loaded |
    And STDERR should be empty

  Scenario: Profile the muplugins_loaded:before hook
    Given a WP install

    When I run `wp profile hook muplugins_loaded:before --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook                  |
      | muplugins_loaded:before |
    And STDERR should be empty

  Scenario: Profile the muplugins_loaded:after hook
    Given a WP install

    When I run `wp profile hook muplugins_loaded:after --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook                 |
      | muplugins_loaded:after |
    And STDERR should be empty
  Scenario: Hooks should only be called once
    Given a WP install
    And a wp-content/mu-plugins/action-test.php file:
      """
      <?php
      add_action( 'init', function(){
        static $i;
        if ( ! isset( $i ) ) {
          $i = 0;
        }
        $i++;
        WP_CLI::warning( 'Called ' . $i );
      });
      """

    When I try `wp profile hook init`
    Then STDERR should be:
      """
      Warning: Called 1
      """
