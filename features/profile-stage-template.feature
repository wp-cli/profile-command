Feature: Profile the template render stage

  Scenario: Profiler loads a table with the correct hooks
    Given a WP install

    When I run `wp profile --stage=template --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook              |
      |                   |
      | template_redirect |
      |                   |
      | template_include  |
      |                   |
      | wp_head           |
      |                   |
      | loop_start        |
      |                   |
      | loop_end          |
      |                   |
      | wp_footer         |
      | total             |
