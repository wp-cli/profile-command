Feature: Profile a specific hook

  Scenario: Profile all hooks when a specific hook isn't specified
    Given a WP install

    When I run `wp profile hook --fields=hook,callback_count`
    Then STDOUT should be a table containing rows:
      | hook              | callback_count   |
      | plugins_loaded    | 3                |
      | init              | 11               |
      | template_redirect | 6                |
    And STDERR should be empty

  Scenario: Profile all callbacks when --all flag is used
    Given a WP install

    When I run `wp profile hook --all --fields=callback,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | callback                   | cache_hits    | cache_misses  |
      | sanitize_comment_cookies() | 0             | 0             |
      | smilies_init()             | 2             | 0             |
      | feed_links()               | 8             | 0             |

  Scenario: Profile a hook before the template is loaded
    Given a WP install

    When I run `wp profile hook plugins_loaded --fields=callback`
    Then STDOUT should be a table containing rows:
      | callback          |
    And STDERR should be empty

  Scenario: Profile a hook without any callbacks
    Given a WP install

    When I run `wp profile hook setup_theme --fields=callback`
    Then STDOUT should be a table containing rows:
      | callback          |
      | total             |
    And STDERR should be empty

  Scenario: Profile a hook that has actions with output
    Given a WP install

    When I run `wp profile hook wp_head --fields=callback`
    Then STDOUT should be a table containing rows:
      | callback          |
    And STDOUT should not contain:
      """
      <meta name="generator"
      """

  Scenario: Profile the shutdown hook
    Given a WP install
    And a wp-content/mu-plugins/shutdown.php file:
      """
      <?php
      function runcommand_shutdown_hook() {
        wp_cache_get( 'foo' );
      }
      add_action( 'shutdown', 'runcommand_shutdown_hook' );
      """

    When I run `wp profile hook shutdown --fields=callback,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | callback                   | cache_hits     | cache_misses     |
      | runcommand_shutdown_hook() | 0              | 1                |
      | wp_ob_end_flush_all()      | 0              | 0                |
      | total                      | 0              | 1                |
    And STDERR should be empty

  Scenario: Indicate where a callback is defined with profiling a hook
    Given a WP install
    And a wp-content/mu-plugins/custom-action.php file:
      """
      <?php
      function runcommand_custom_action_hook() {
        wp_cache_get( 'foo' );
      }
      add_action( 'runcommand_custom_action', 'runcommand_custom_action_hook' );
      do_action( 'runcommand_custom_action' );
      """

    When I run `wp profile hook runcommand_custom_action --fields=callback,location,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | callback                        | location                                  | cache_hits | cache_misses |
      | runcommand_custom_action_hook() | mu-plugins/custom-action.php:2            | 0          | 1            |
      | total                           |                                           | 0          | 1            |
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

    When I run `wp profile hook init`
    Then STDERR should be:
      """
      Warning: Called 1
      """
