Feature: Basic profile usage

  Scenario: Profiler loads a summary table
    Given a WP install

    When I run `wp profile --fields=stage`
    Then STDOUT should be a table containing rows:
      | stage        |
      | bootstrap    |
      | main_query   |
      | template     |

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

    When I try `wp profile`
    Then STDERR should be:
      """
      Error: 'SAVEQUERIES' is defined as false, and must be true. Please check your wp-config.php
      """
