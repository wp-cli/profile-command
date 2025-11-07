Feature: Profile HTTP requests

  Scenario: Profile HTTP requests during WordPress load
    Given a WP install

    When I run `wp profile requests --fields=method,url,status,time`
    Then STDOUT should contain:
      """
      method
      """
    And STDOUT should contain:
      """
      url
      """
    And STDOUT should contain:
      """
      status
      """
    And STDOUT should contain:
      """
      time
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
