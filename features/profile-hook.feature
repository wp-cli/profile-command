Feature: Profile a specific hook

  Scenario: Profile a hook before the template is loaded
    Given a WP install

    When I run `wp profile --hook=plugins_loaded --fields=callback`
    Then STDOUT should be a table containing rows:
      | callback          |
    And STDERR should be empty

  Scenario: Profile a hook without any callbacks
    Given a WP install

    When I run `wp profile --hook=setup_theme --fields=callback`
    Then STDOUT should be a table containing rows:
      | callback          |
      | total             |
    And STDERR should be empty

  Scenario: Profile a hook that has actions with output
    Given a WP install

    When I run `wp profile --hook=wp_head --fields=callback`
    Then STDOUT should be a table containing rows:
      | callback          |
    And STDOUT should not contain:
      """
      <meta name="generator"
      """
