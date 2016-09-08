Feature: Profile a specific hook

  Scenario: Profile a hook without any callbacks
    Given a WP install

    When I run `wp profile --hook=setup_theme --fields=callback`
    Then STDOUT should be a table containing rows:
      | callback          |
      | total             |
    And STDERR should be empty
