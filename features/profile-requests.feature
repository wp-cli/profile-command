Feature: Profile HTTP requests

  Scenario: Profile HTTP requests during WordPress load
    Given a WP install
    And that HTTP requests to https://www.apple.com/ will respond with:
      """
      HTTP/1.1 200
      Content-Type: text/plain

      Hello world
      """
    And that HTTP requests to https://www.example.com/ will respond with:
      """
      HTTP/1.1 201
      Content-Type: application/json

      {"status":"created"}
      """
    And a wp-content/mu-plugins/http-requests.php file:
      """
      <?php
      add_action( 'muplugins_loaded', function() {
        wp_remote_get( 'https://www.apple.com/' );
        wp_remote_post( 'https://www.example.com/', array( 'body' => 'test' ) );
      });
      """

    When I run `wp profile requests --fields=method,url,status`
    Then STDOUT should be a table containing rows:
      | method    | url                      | status |
      | GET       | https://www.apple.com/   | 200    |
      | POST      | https://www.example.com/ | 201    |
      | total (2) |                          |        |
    And STDERR should be empty

  Scenario: Profile HTTP requests that are not short-circuited
    Given a WP install
    And a wp-content/mu-plugins/http-requests.php file:
      """
      <?php
      define( 'WP_HTTP_BLOCK_EXTERNAL', true );
      add_action( 'muplugins_loaded', function() {
        wp_remote_head( 'https://example.org/blocked' );
      });
      """

    When I run `wp profile requests --fields=method,url,status`
    Then STDOUT should be a table containing rows:
      | method    | url                         | status |
      | HEAD      | https://example.org/blocked | error  |
      | total (1) |                             |        |
    And STDERR should be empty

    When I run `wp profile requests --format=json --fields=url,time`
    Then STDOUT should be JSON containing:
      """
      [{"url":"https://example.org/blocked"}]
      """

  Scenario: Profile shows no requests when none are made
    Given a WP install

    When I run `wp profile requests --fields=method,url`
    Then STDOUT should be a table containing rows:
      | method    | url |
      | total (0) |     |
