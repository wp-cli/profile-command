Feature: Profile arbitary file execution

  Scenario: Profile a function that doesn't do anything
    Given a WP install
    And a lame-function.php file:
      """
      <?php
      function runcommand_do_nothing() {

      }
      runcommand_do_nothing();
      """

    When I run `wp profile eval-file lame-function.php --fields=query_time,query_count,cache_ratio,cache_hits,cache_misses,request_time,request_count`
    Then STDOUT should be a table containing rows:
      | query_time | query_count | cache_ratio | cache_hits | cache_misses | request_time | request_count |
      | 0s         | 0           |             | 0          | 0            | 0s           | 0             |

  Scenario: Profile a function that makes one HTTP request
    Given a WP install
    And a http-request.php file:
      """
      <?php
      wp_remote_get( "http://apple.com" );
      """

    When I run `wp profile eval-file http-request.php --fields=request_count`
    Then STDOUT should be a table containing rows:
      | request_count |
      | 1             |

  Scenario: Profile calls to the object cache
    Given a WP install
    And a one-miss.php file:
      """
      <?php
      wp_cache_get( "foo" );
      """
    And a two-hits.php file:
      """
      <?php
      wp_cache_set( "foo", "bar" );
      wp_cache_get( "foo" );
      wp_cache_get( "foo" );
      """

    When I run `wp profile eval-file one-miss.php --fields=cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | cache_hits    | cache_misses |
      | 0             | 1            |

    When I run `wp profile eval-file two-hits.php --fields=cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | cache_hits    | cache_misses |
      | 2             | 0            |

  Scenario: Profile a function calling a hook
    Given a WP install
    And a calls-hook.php file:
      """
      <?php
      add_filter( 'logout_url', function( $url ) { wp_cache_get( 'foo' ); return $url; });
      wp_logout_url();
      """

    When I run `wp profile eval-file calls-hook.php --hook --fields=hook,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | hook        | cache_hits   | cache_misses   |
      | logout_url  | 0            | 1              |

    When I run `wp profile eval-file calls-hook.php --hook=logout_url --fields=callback,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | callback        | cache_hits   | cache_misses   |
      | function(){}    | 0            | 1              |
