Feature: Profile HTTP requests

  Scenario: Profile HTTP requests during WordPress load
    Given a WP install
    And a wp-content/mu-plugins/http-requests.php file:
      """
      <?php
      add_action( 'muplugins_loaded', function() {
        wp_remote_get( 'https://www.apple.com/' );
        wp_remote_post( 'https://www.example.com/', array( 'body' => 'test' ) );
      });
      """

    When I run `wp profile requests --fields=method,url`
    Then STDOUT should be a table containing rows:
      | method | url                        |
      | GET    | https://www.apple.com/     |
      | POST   | https://www.example.com/   |
    And STDOUT should contain:
      """
      total (2)
      """

  Scenario: Profile shows no requests when none are made
    Given a WP install
    And a wp-content/mu-plugins/no-requests.php file:
      """
      <?php
      // Don't make any HTTP requests
      add_filter( 'pre_http_request', '__return_false', 1 );
      """

    When I run `wp profile requests --fields=method,url`
    Then STDOUT should contain:
      """
      total (0)
      """
