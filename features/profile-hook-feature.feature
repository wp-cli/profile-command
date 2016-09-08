Feature: Profile a specific hook

  Scenario: Profile a hook without any callbacks
    Given a WP install

    When I run `wp profile --hook=setup_theme`
    Then STDOUT should be empty
    And STDERR should be empty
