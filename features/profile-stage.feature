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
      | hook                     |
      | muplugins_loaded:before  |
      | muplugins_loaded         |
      | plugins_loaded:before    |
      | plugins_loaded           |
      | setup_theme:before       |
      | setup_theme              |
      | after_setup_theme:before |
      | after_setup_theme        |
      | init:before              |
      | init                     |
      | wp_loaded:before         |
      | wp_loaded                |
      | wp_loaded:after          |
      | total                    |

    When I run `wp profile stage main_query --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook                     |
      | parse_request:before     |
      | parse_request            |
      | send_headers:before      |
      | send_headers             |
      | pre_get_posts:before     |
      | pre_get_posts            |
      | the_posts:before         |
      | the_posts                |
      | wp:before                |
      | wp                       |
      | wp:after                 |
      | total                    |

    When I run `wp profile stage template --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook                     |
      | template_redirect:before |
      | template_redirect        |
      | template_include:before  |
      | template_include         |
      | wp_head:before           |
      | wp_head                  |
      | loop_start:before        |
      | loop_start               |
      | loop_end:before          |
      | loop_end                 |
      | wp_footer:before         |
      | wp_footer                |
      | wp_footer:after          |
      | total                    |

  Scenario: Use --all flag to profile all stages
    Given a WP install

    When I run `wp profile stage --all --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook                     |
      | muplugins_loaded:before  |
      | muplugins_loaded         |
      | plugins_loaded:before    |
      | plugins_loaded           |
      | setup_theme:before       |
      | setup_theme              |
      | after_setup_theme:before |
      | after_setup_theme        |
      | init:before              |
      | init                     |
      | wp_loaded:before         |
      | wp_loaded                |
      | parse_request:before     |
      | parse_request            |
      | send_headers:before      |
      | send_headers             |
      | pre_get_posts:before     |
      | pre_get_posts            |
      | the_posts:before         |
      | the_posts                |
      | wp:before                |
      | wp                       |
      | template_redirect:before |
      | template_redirect        |
      | template_include:before  |
      | template_include         |
      | wp_head:before           |
      | wp_head                  |
      | loop_start:before        |
      | loop_start               |
      | loop_end:before          |
      | loop_end                 |
      | wp_footer:before         |
      | wp_footer                |
      | wp_footer:after          |
      | total                    |

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

  Scenario: Use spotlight mode to filter out the zero-ish values
    Given a WP install

    When I run `wp profile stage bootstrap --fields=hook`
    Then STDOUT should be a table containing rows:
      | hook              |
      | init              |
      | wp_loaded:before  |
      | wp_loaded         |
      | wp_loaded:after   |

    When I run `wp profile stage bootstrap --fields=hook --spotlight`
    Then STDOUT should be a table containing rows:
      | hook              |
      | init              |
      | wp_loaded:after   |
