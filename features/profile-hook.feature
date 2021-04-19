Feature: Profile a specific hook

  @require-wp-4.0
  Scenario: Profile all hooks when a specific hook isn't specified
    Given a WP install

    When I run `wp profile hook --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook              |
      | plugins_loaded    |
      | init              |
      | template_redirect |
    And STDERR should be empty

  @require-wp-4.4
  Scenario: Profile all callbacks when --all flag is used
    Given a WP install

    When I run `wp profile hook --all --fields=callback,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | callback                   | cache_hits    | cache_misses  |
      | sanitize_comment_cookies() | 0             | 0             |
      | smilies_init()             | 2             | 0             |
      | feed_links()               | 8             | 0             |

  @less-than-php-7 @require-wp-4.0
  Scenario: Profile an intermediate stage hook
    Given a WP install

    When I run `wp profile hook wp_head:before --fields=callback,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | callback                  | cache_hits     | cache_misses  |
      | locate_template()         | 0              | 0             |
      | load_template()           | 0              | 0             |
    And STDOUT should not contain:
      """
      WP_CLI\Profile\Profiler->wp_tick_profile_begin()
      """

  @require-wp-4.0
  Scenario: Profile a hook before the template is loaded
    Given a WP install

    When I run `wp profile hook plugins_loaded --fields=callback`
    Then STDOUT should be a table containing rows:
      | callback          |
    And STDERR should be empty

  @require-wp-4.0
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

  @require-wp-4.0
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

  @require-wp-4.0
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

  @require-wp-4.4
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

  @less-than-php-7 @require-wp-4.0
  Scenario: Profile the mu_plugins:before hook
    Given a WP install
    And a wp-content/mu-plugins/awesome-file.php file:
      """
      <?php
      function awesome_func() {
        // does nothing
      }
      awesome_func();
      """

    When I run `wp profile hook muplugins_loaded:before --fields=callback`
    Then STDOUT should contain:
      """
      wp-content/mu-plugins/awesome-file.php
      """

  @less-than-php-7 @require-wp-4.0
  Scenario: Profile the :after hooks
    Given a WP install

    When I run `wp profile hook wp_loaded:after`
    Then STDOUT should contain:
      """
      do_action()
      """

    When I run `wp profile hook wp:after`
    Then STDOUT should contain:
      """
      do_action_ref_array()
      """

    When I run `wp profile hook wp_footer:after`
    Then STDOUT should contain:
      """
      do_action()
      """
