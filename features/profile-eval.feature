Feature: Profile arbitary code execution

  Scenario: Profile a function that doesn't do anything
    Given a WP install
    And a wp-content/mu-plugins/lame-function.php file:
      """
      <?php
      function wp_cli_do_nothing() {

      }
      """

    When I run `wp profile eval 'wp_cli_do_nothing();' --fields=query_time,query_count,cache_ratio,cache_hits,cache_misses,redis_calls,request_time,request_count`
    Then STDOUT should be a table containing rows:
      | query_time | query_count | cache_ratio | cache_hits | cache_misses | redis_calls | request_time | request_count |
      | 0s         | 0           |             | 0          | 0            | 0           | 0s           | 0             |

  Scenario: Profile a function that makes one HTTP request
    Given a WP install

    When I run `wp profile eval 'wp_remote_get( "https://www.apple.com/" );' --fields=request_count`
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

  Scenario: redis_calls shows 0 when no persistent object cache is active
    Given a WP install

    When I run `wp profile eval 'wp_cache_get( "foo" );' --fields=cache_misses,redis_calls`
    Then STDOUT should be a table containing rows:
      | cache_misses | redis_calls |
      | 1            | 0           |

  Scenario: redis_calls tracks calls to a persistent object cache that exposes redis_calls
    Given a WP install
    And a wp-content/object-cache.php file:
      """
      <?php
      function wp_cache_init() {
        global $wp_object_cache;
        $wp_object_cache = new WP_Object_Cache_With_Redis_Calls();
      }
      function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
        global $wp_object_cache;
        return $wp_object_cache->add( $key, $data, $group, (int) $expire );
      }
      function wp_cache_add_global_groups( $groups ) {
        global $wp_object_cache;
        $wp_object_cache->add_global_groups( $groups );
      }
      function wp_cache_add_non_persistent_groups( $groups ) {}
      function wp_cache_close() { return true; }
      function wp_cache_decr( $key, $offset = 1, $group = '' ) {
        global $wp_object_cache;
        return $wp_object_cache->decr( $key, $offset, $group );
      }
      function wp_cache_delete( $key, $group = '' ) {
        global $wp_object_cache;
        return $wp_object_cache->delete( $key, $group );
      }
      function wp_cache_flush() {
        global $wp_object_cache;
        return $wp_object_cache->flush();
      }
      function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
        global $wp_object_cache;
        return $wp_object_cache->get( $key, $group, $force, $found );
      }
      function wp_cache_incr( $key, $offset = 1, $group = '' ) {
        global $wp_object_cache;
        return $wp_object_cache->incr( $key, $offset, $group );
      }
      function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
        global $wp_object_cache;
        return $wp_object_cache->replace( $key, $data, $group, (int) $expire );
      }
      function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
        global $wp_object_cache;
        return $wp_object_cache->set( $key, $data, $group, (int) $expire );
      }
      function wp_cache_switch_to_blog( $blog_id ) {}
      class WP_Object_Cache_With_Redis_Calls {
        public $cache = array();
        public $cache_hits = 0;
        public $cache_misses = 0;
        public $redis_calls = array();
        private $global_groups = array();
        private function cache_key( $key, $group ) {
          return $group . ':' . $key;
        }
        private function track( $command ) {
          $this->redis_calls[ $command ] = isset( $this->redis_calls[ $command ] ) ? $this->redis_calls[ $command ] + 1 : 1;
        }
        public function add( $key, $data, $group = 'default', $expire = 0 ) {
          if ( isset( $this->cache[ $this->cache_key( $key, $group ) ] ) ) {
            return false;
          }
          return $this->set( $key, $data, $group, $expire );
        }
        public function set( $key, $data, $group = 'default', $expire = 0 ) {
          $this->cache[ $this->cache_key( $key, $group ) ] = $data;
          $this->track( 'set' );
          return true;
        }
        public function get( $key, $group = 'default', $force = false, &$found = null ) {
          $this->track( 'get' );
          if ( isset( $this->cache[ $this->cache_key( $key, $group ) ] ) ) {
            $found = true;
            $this->cache_hits++;
            return $this->cache[ $this->cache_key( $key, $group ) ];
          }
          $found = false;
          $this->cache_misses++;
          return false;
        }
        public function delete( $key, $group = 'default' ) {
          $cache_key = $this->cache_key( $key, $group );
          if ( ! isset( $this->cache[ $cache_key ] ) ) {
            return false;
          }
          unset( $this->cache[ $cache_key ] );
          $this->track( 'del' );
          return true;
        }
        public function flush() {
          $this->cache = array();
          return true;
        }
        public function decr( $key, $offset = 1, $group = 'default' ) {
          $cache_key = $this->cache_key( $key, $group );
          if ( ! isset( $this->cache[ $cache_key ] ) ) {
            return false;
          }
          $this->cache[ $cache_key ] = max( 0, (int) $this->cache[ $cache_key ] - $offset );
          $this->track( 'decrby' );
          return $this->cache[ $cache_key ];
        }
        public function incr( $key, $offset = 1, $group = 'default' ) {
          $cache_key = $this->cache_key( $key, $group );
          if ( ! isset( $this->cache[ $cache_key ] ) ) {
            return false;
          }
          $this->cache[ $cache_key ] = (int) $this->cache[ $cache_key ] + $offset;
          $this->track( 'incrby' );
          return $this->cache[ $cache_key ];
        }
        public function replace( $key, $data, $group = 'default', $expire = 0 ) {
          if ( ! isset( $this->cache[ $this->cache_key( $key, $group ) ] ) ) {
            return false;
          }
          return $this->set( $key, $data, $group, $expire );
        }
        public function add_global_groups( $groups ) {
          foreach ( (array) $groups as $group ) {
            $this->global_groups[ $group ] = true;
          }
        }
      }
      """

    When I run `wp profile eval 'wp_cache_set( "foo", "bar" ); wp_cache_get( "foo" ); wp_cache_get( "foo" );' --fields=cache_hits,cache_misses,redis_calls`
    Then STDOUT should be a table containing rows:
      | cache_hits | cache_misses | redis_calls |
      | 2          | 0            | 3           |
