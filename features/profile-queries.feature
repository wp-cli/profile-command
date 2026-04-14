Feature: Profile database queries

  Scenario: Show all database queries
    Given a WP install
    And a wp-content/mu-plugins/test-queries.php file:
      """
      <?php
      add_action( 'init', function() {
        global $wpdb;
        $wpdb->query( "SELECT 1 as test_query_one" );
        $wpdb->query( "SELECT 2 as test_query_two" );
      });
      """

    When I run `wp profile queries --fields=query,time`
    Then STDOUT should contain:
      """
      query
      """
    And STDOUT should contain:
      """
      time
      """
    And STDOUT should contain:
      """
      SELECT 1 as test_query_one
      """
    And STDOUT should contain:
      """
      SELECT 2 as test_query_two
      """
    And STDOUT should contain:
      """
      total
      """
    And STDERR should be empty

  Scenario: Show queries with specific fields
    Given a WP install

    When I run `wp profile queries --fields=query,time`
    Then STDOUT should contain:
      """
      query
      """
    And STDOUT should contain:
      """
      time
      """
    And STDOUT should contain:
      """
      SELECT
      """
    And STDERR should be empty

  Scenario: Order queries by execution time
    Given a WP install

    When I run `wp profile queries --fields=time --orderby=time --order=DESC`
    Then STDOUT should contain:
      """
      time
      """
    And STDERR should be empty

  Scenario: Display queries in JSON format
    Given a WP install

    When I run `wp profile queries --format=json --fields=query,time`
    Then STDOUT should contain:
      """
      "query"
      """
    And STDOUT should contain:
      """
      "time"
      """
    And STDERR should be empty

  Scenario: Filter queries by hook
    Given a WP install
    And a wp-content/mu-plugins/query-test.php file:
      """
      <?php
      add_action( 'init', function() {
        global $wpdb;
        $wpdb->query( "SELECT 1 as test_query" );
      });
      """

    When I run `wp profile queries --hook=init --fields=query,callback`
    Then STDOUT should contain:
      """
      SELECT 1 as test_query
      """
    And STDERR should be empty

  Scenario: Filter queries by callback
    Given a WP install
    And a wp-content/mu-plugins/callback-test.php file:
      """
      <?php
      function my_test_callback() {
        global $wpdb;
        $wpdb->query( "SELECT 2 as callback_test" );
      }
      add_action( 'init', 'my_test_callback' );
      """

    When I run `wp profile queries --callback=my_test_callback --fields=query,hook`
    Then STDOUT should contain:
      """
      SELECT 2 as callback_test
      """
    And STDERR should be empty

  Scenario: Filter queries by time threshold
    Given a WP install
    And a wp-content/mu-plugins/test-threshold.php file:
      """
      <?php
      add_action( 'init', function() {
        global $wpdb;
        $wpdb->queries[] = array( 'SELECT 1 as test_query_fast', 0.01, 'caller' );
        $wpdb->queries[] = array( 'SELECT 2 as test_query_slow', 0.2, 'caller' );
      });
      """

    When I run `wp profile queries --fields=query --time_threshold=0.1`
    Then STDOUT should contain:
      """
      SELECT 2 as test_query_slow
      """
    And STDOUT should not contain:
      """
      SELECT 1 as test_query_fast
      """
    And STDERR should be empty

  Scenario: Format caller with newlines
    Given a WP install
    And a wp-content/mu-plugins/test-caller.php file:
      """
      <?php
      function my_test_function() {
        global $wpdb;
        $wpdb->queries[] = array( 'SELECT 3 as test_caller', 0.01, 'frame1, frame2, frame3' );
      }
      add_action( 'init', 'my_test_function' );
      """

    When I run `wp profile queries --callback=my_test_function --fields=caller --format=json`
    Then STDOUT should be JSON containing:
      """
      [
        {
          "caller": "frame1\nframe2\nframe3"
        }
      ]
      """
    And STDERR should be empty

  Scenario: Format caller with newlines and strip WP-CLI frames
    Given a WP install
    And a wp-content/mu-plugins/test-caller-strip.php file:
      """
      <?php
      function my_test_function_strip() {
        global $wpdb;
        $wpdb->queries[] = array( 'SELECT 4 as test_caller_strip_1', 0.01, 'WP_CLI\Main, WP_CLI\Profile\Profiler->load_wordpress_with_template(), frame1, frame2' );
        $wpdb->queries[] = array( 'SELECT 5 as test_caller_strip_2', 0.01, 'WP_CLI\Main, WP_CLI\Profile\Profiler->load_wordpress_with_template, frame3, frame4' );
      }
      add_action( 'init', 'my_test_function_strip' );
      """

    When I run `wp profile queries --callback=my_test_function_strip --fields=caller --format=json`
    Then STDOUT should be JSON containing:
      """
      [
        {
          "caller": "frame1\nframe2"
        },
        {
          "caller": "frame3\nframe4"
        }
      ]
      """
    And STDERR should be empty
