Feature: Profile database queries

  @require-wp-4.0
  Scenario: Show all database queries
    Given a WP install

    When I run `wp profile queries --fields=time`
    Then STDOUT should contain:
      """
      time
      """
    And STDOUT should contain:
      """
      total
      """
    And STDERR should be empty

  @require-wp-4.0
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

  @require-wp-4.0
  Scenario: Order queries by execution time
    Given a WP install

    When I run `wp profile queries --fields=time --orderby=time --order=DESC`
    Then STDOUT should contain:
      """
      time
      """
    And STDERR should be empty

  @require-wp-4.0
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

  @require-wp-4.0
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

  @require-wp-4.0
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
