Feature: Profile arbitary code execution

  Scenario: Profile a function that doesn't do anything
    Given a WP install
    And a wp-content/mu-plugins/lame-function.php file:
      """
      <?php
      function runcommand_do_nothing() {

      }
      """

    When I run `wp profile eval 'runcommand_do_nothing();' --fields=query_time,query_count,cache_ratio,cache_hits,cache_misses,request_time,request_count`
    Then STDOUT should be a table containing rows:
      | query_time | query_count | cache_ratio | cache_hits | cache_misses | request_time | request_count |
      | 0s         | 0           |             | 0          | 0            | 0s           | 0             |

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

  Scenario: Profile a function calling a hook
    Given a WP install

    When I run `wp profile eval "add_filter( 'logout_url', function( $url ) { wp_cache_get( 'foo' ); return $url; }); wp_logout_url();" --hook --fields=hook,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | hook        | cache_hits   | cache_misses   |
      | logout_url  | 0            | 1              |

    When I run `wp profile eval "add_filter( 'logout_url', function( $url ) { wp_cache_get( 'foo' ); return $url; }); wp_logout_url();" --hook=logout_url --fields=callback,cache_hits,cache_misses`
    Then STDOUT should be a table containing rows:
      | callback        | cache_hits   | cache_misses   |
      | function(){}    | 0            | 1              |
