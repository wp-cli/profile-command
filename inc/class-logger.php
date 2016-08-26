<?php

namespace runcommand\Profile;

class Logger {

	public $time = 0;
	public $query_count = 0;
	public $query_time = 0;
	public $cache_hits = 0;
	public $cache_misses = 0;
	public $cache_ratio = 0;
	public $hook_count = 0;
	public $hook_time = 0;
	public $request_count = 0;
	public $request_time = 0;

	private $start_time = null;
	private $query_offset = null;
	private $cache_hit_offset = null;
	private $cache_miss_offset = null;
	private $hook_start_time = null;
	private $hook_depth = 0;
	private $request_start_time = null;

	public static $active_loggers = array();

	public function __construct( $type, $name ) {
		$this->$type = $name;
	}

	/**
	 * Start this logger
	 */
	public function start() {
		global $wpdb, $wp_object_cache;
		$this->start_time = microtime( true );
		$this->query_offset = ! empty( $wpdb->queries ) ? count( $wpdb->queries ) : 0;
		if ( false === ( $key = array_search( $this, self::$active_loggers ) ) ) {
			self::$active_loggers[] = $this;
		}
		$this->cache_hit_offset = ! empty( $wp_object_cache->cache_hits ) ? $wp_object_cache->cache_hits : 0;
		$this->cache_miss_offset = ! empty( $wp_object_cache->cache_misses ) ? $wp_object_cache->cache_misses : 0;
	}

	/**
	 * Stop this logger
	 */
	public function stop() {
		global $wpdb, $wp_object_cache;

		if ( ! is_null( $this->start_time ) ) {
			$this->time += microtime( true ) - $this->start_time;
		}
		if ( ! is_null( $this->query_offset ) ) {
			for ( $i = $this->query_offset; $i < count( $wpdb->queries ); $i++ ) {
				$this->query_time += $wpdb->queries[ $i ][1];
				$this->query_count++;
			}
		}

		if ( ! is_null( $this->cache_hit_offset ) && ! is_null( $this->cache_miss_offset ) ) {
			$cache_hits = ! empty( $wp_object_cache->cache_hits ) ? $wp_object_cache->cache_hits : 0;
			$cache_misses = ! empty( $wp_object_cache->cache_misses ) ? $wp_object_cache->cache_misses : 0;
			$this->cache_hits = $cache_hits - $this->cache_hit_offset;
			$this->cache_misses = $cache_misses - $this->cache_miss_offset;
			$cache_total = $this->cache_hits + $this->cache_misses;
			if ( $cache_total ) {
				$ratio = ( $this->cache_hits / $cache_total ) * 100;
				$this->cache_ratio = round( $ratio, 2 ) . '%';
			}
		}

		$this->start_time = null;
		$this->query_offset = null;
		$this->cache_hit_offset = null;
		$this->cache_miss_offset = null;
		if ( false !== ( $key = array_search( $this, self::$active_loggers ) ) ) {
			unset( self::$active_loggers[ $key ] );
		}
	}

	/**
	 * Start this logger's hook timer
	 */
	public function start_hook_timer() {
		$this->hook_count++;
		// Timer already running means a subhook has been called
		if ( ! is_null( $this->hook_start_time ) ) {
			$this->hook_depth++;
		} else {
			$this->hook_start_time = microtime( true );
		}
	}

	/**
	 * Stop this logger's hook timer
	 */
	public function stop_hook_timer() {
		if ( $this->hook_depth ) {
			$this->hook_depth--;
		} else {
			if ( ! is_null( $this->hook_start_time ) ) {
				$this->hook_time += microtime( true ) - $this->hook_start_time;
			}
			$this->hook_start_time = null;
		}
	}

	/**
	 * Start this logger's request timer
	 */
	public function start_request_timer() {
		$this->request_count++;
		$this->request_start_time = microtime( true );
	}

	/**
	 * Stop this logger's request timer
	 */
	public function stop_request_timer() {
		if ( ! is_null( $this->request_start_time ) ) {
			$this->request_time += microtime( true ) - $this->request_start_time;
		}
		$this->request_start_time = null;
	}

}
