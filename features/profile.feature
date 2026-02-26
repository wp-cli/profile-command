Feature: Basic profile usage

  Scenario: Assert available commands
    Given a WP install

    When I run `wp profile`
    Then STDOUT should be:
      """
      usage: wp profile eval <php-code> [--hook[=<hook>]] [--fields=<fields>] [--format=<format>] [--order=<order>] [--orderby=<fields>]
         or: wp profile eval-file <file> [--hook[=<hook>]] [--fields=<fields>] [--format=<format>] [--order=<order>] [--orderby=<fields>]
         or: wp profile hook [<hook>] [--all] [--spotlight] [--url=<url>] [--fields=<fields>] [--format=<format>] [--order=<order>] [--orderby=<fields>]
         or: wp profile requests [--url=<url>] [--fields=<fields>] [--format=<format>] [--order=<order>] [--orderby=<fields>]
         or: wp profile stage [<stage>] [--all] [--spotlight] [--url=<url>] [--fields=<fields>] [--format=<format>] [--order=<order>] [--orderby=<fields>]

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
    And I run `wp core config {CORE_CONFIG_SETTINGS} --skip-check --extra-php < extra-php`

    When I run `wp core install --url='https://localhost' --title='Test' --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then the return code should be 0

    When I try `wp profile stage`
    Then STDERR should be:
      """
      Error: 'SAVEQUERIES' is defined as false, and must be true. Please check your wp-config.php
      """

  @require-wp-4.0
  Scenario: Profile a hook without any callbacks
    Given a WP install

    When I run `wp profile hook get_search_form --fields=callback,time`
    Then STDOUT should be a table containing rows:
      | callback          | time   |
      | total (0)         |        |
    And STDERR should be empty

  @require-wp-4.0
  Scenario: Trailingslash provided URL to avoid canonical redirect
    Given a WP install

    When I run `wp profile hook get_search_form --url=example.com --fields=callback,time`
    Then STDERR should be empty
    And STDOUT should be a table containing rows:
      | callback          | time   |
      | total (0)         |        |

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
