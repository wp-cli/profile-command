Feature: Profile the template render stage

  Scenario: Profiler loads a summary table
    Given a WP install

    When I run `wp profile stage --fields=stage`
    Then STDOUT should be a table containing rows:
      | stage        |
      | bootstrap    |
      | main_query   |
      | template     |

  Scenario: Profiler loads a table with the correct hooks
    Given a WP install

    When I run `wp profile stage bootstrap --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook              |
      |                   |
      | muplugins_loaded  |
      |                   |
      | plugins_loaded    |
      |                   |
      | setup_theme       |
      |                   |
      | after_setup_theme |
      |                   |
      | init              |
      |                   |
      | wp_loaded         |
      |                   |
      | total             |

    When I run `wp profile stage main_query --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook              |
      |                   |
      | parse_request     |
      |                   |
      | send_headers      |
      |                   |
      | pre_get_posts     |
      |                   |
      | the_posts         |
      |                   |
      | wp                |
      |                   |
      | total             |

    When I run `wp profile stage template --fields=hook`
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
      |                   |
      | total             |

  Scenario: Use --all flag to profile all stages
    Given a WP install

    When I run `wp profile stage --all --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook              |
      |                   |
      | muplugins_loaded  |
      |                   |
      | plugins_loaded    |
      |                   |
      | setup_theme       |
      |                   |
      | after_setup_theme |
      |                   |
      | init              |
      |                   |
      | wp_loaded         |
      |                   |
      | parse_request     |
      |                   |
      | send_headers      |
      |                   |
      | pre_get_posts     |
      |                   |
      | the_posts         |
      |                   |
      | wp                |
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
      |                   |
      | total             |

  Scenario: Invalid stage specified
    Given a WP install

    When I try `wp profile stage foo_bar`
    Then STDERR should be:
      """
      Error: Invalid stage. Must be one of bootstrap, main_query, template, or use --all.
      """

  Scenario: Identify callback_count for each hook
    Given a WP install

    When I run `wp profile stage bootstrap --fields=hook,callback_count`
    Then STDOUT should be a table containing rows:
      | hook              | callback_count   |
      | plugins_loaded    | 3                |
