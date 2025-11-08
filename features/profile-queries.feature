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
