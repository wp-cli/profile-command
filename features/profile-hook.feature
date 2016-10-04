Feature: Profile a specific hook

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
