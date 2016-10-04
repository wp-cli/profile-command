Feature: Profile arbitary code execution

  Scenario: Profile a function that doesn't do anything
    Given a WP install
    And a wp-content/mu-plugins/lame-function.php file:
      """
      <?php
      function runcommand_do_nothing() {

      }
      """

    When I run `wp profile eval 'runcommand_do_nothing();'`
    Then STDOUT should be a table containing rows:
      | time | query_time | query_count | cache_ratio | cache_hits | cache_misses | request_time | request_count |
      | 0s   | 0s         | 0           |             | 0          | 0            | 0s           | 0             |

  Scenario: Profile a function that makes one HTTP request
    Given a WP install

    When I run `wp profile eval 'wp_remote_get( "http://apple.com" );' --fields=request_count`
    Then STDOUT should be a table containing rows:
      | request_count |
      | 1             |

  Scenario: Profile calls to the object cache
    Given a WP install

    When I run `wp profile eval 'wp_cache_get( "foo" );' --fields=cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | cache_hits    | cache_misses |
      | 0             | 1            |

    When I run `wp profile eval 'wp_cache_set( "foo", "bar" ); wp_cache_get( "foo" ); wp_cache_get( "foo" );' --fields=cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | cache_hits    | cache_misses |
      | 2             | 0            |
