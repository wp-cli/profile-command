Feature: Basic profile usage

  Scenario: Assert available commands
    Given a WP install

    When I run `wp profile`
    Then STDOUT should be:
      """
      usage: wp profile eval <php-code> [--fields=<fields>] [--format=<format>]
         or: wp profile eval-file <file> [--fields=<fields>] [--format=<format>]
         or: wp profile hook [<hook>] [--all] [--url=<url>] [--fields=<fields>] [--format=<format>]
         or: wp profile stage [<stage>] [--all] [--url=<url>] [--fields=<fields>] [--format=<format>]

      See 'wp help profile <command>' for more information on a specific command.
      """

  Scenario: Error when SAVEQUERIES is defined to false
    Given an empty directory
    And WP files
    And a database
    And a extra-php file:
      """
      define( 'SAVEQUERIES', false );
      """
    And I run `wp core config {CORE_CONFIG_SETTINGS} --extra-php < extra-php`

    When I run `wp core install --url='https://localhost' --title='Test' --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then the return code should be 0

    When I try `wp profile stage`
    Then STDERR should be:
      """
      Error: 'SAVEQUERIES' is defined as false, and must be true. Please check your wp-config.php
      """

  Scenario: Profile a hook without any callbacks
    Given a WP install

    When I run `wp profile hook setup_theme --fields=callback,time`
    Then STDOUT should be a table containing rows:
      | callback          | time   |
      | total             |        |
    And STDERR should be empty

  Scenario: Trailingslash provided URL to avoid canonical redirect
    Given a WP install

    When I run `wp profile hook setup_theme --url=example.com --fields=callback,time`
    Then STDERR should be empty
    And STDOUT should be a table containing rows:
      | callback          | time   |
      | total             |        |

  Scenario: Don't include 'total' cell when the name column is omitted
    Given a WP install

    When I run `wp profile eval 'wp_cache_get( "foo" );' --fields=cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | cache_hits    | cache_misses |
      | 0             | 1            |
    And STDOUT should not contain:
      """
      total
      """
