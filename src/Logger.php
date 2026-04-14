<?php

namespace WP_CLI\Profile;

/**
 * Logger class.
 *
 * @property string $callback
 * @property string $location
 * @property string $hook
 */
class Logger {

	/** @var float */
	public $time = 0.0;
	/** @var int */
	public $query_count = 0;
	/** @var float */
	public $query_time = 0.0;
	/**
	 * @var array<int> Array of query indices tracked during this logger's execution.
	 */
	public $query_indices = array();

	/** @var int */
	public $cache_hits = 0;
	/** @var int */
	public $cache_misses = 0;
	/** @var string|null */
	public $cache_ratio = null;
	/** @var int */
	public $hook_count = 0;
	/** @var float */
	public $hook_time = 0;
	/** @var int */
	public $request_count = 0;
	/** @var float */
	public $request_time = 0;
	/** @var float|null */
	private $start_time = null;
	/** @var int|null */
	private $query_offset = null;
	/** @var int|null */
	private $cache_hit_offset = null;
	/** @var int|null */
	private $cache_miss_offset = null;
	/** @var float|null */
	private $hook_start_time = null;
	/** @var int */
	private $hook_depth = 0;
	/** @var float|null */
	private $request_start_time = null;

	/** @var array<string, mixed> */
	private $definitions = array();

	/** @var array<\WP_CLI\Profile\Logger> */
	public static $active_loggers = array();

	/**
	 * Logger constructor.
	 *
	 * @param array<string, mixed> $definition
	 */
	public function __construct( $definition = array() ) {
		foreach ( $definition as $k => $v ) {
			$this->definitions[ $k ] = $v;
		}
	}

	/**
	 * Magic getter for definitions.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( isset( $this->definitions[ $key ] ) ) {
			return $this->definitions[ $key ];
		}

		return null;
	}

	/**
	 * Magic setter for definitions.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @return void
	 */
	public function __set( $key, $value ) {
		$this->definitions[ $key ] = $value;
	}

	/**
	 * Magic isset for definitions.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function __isset( $key ) {
		return isset( $this->definitions[ $key ] );
	}

	/**
	 * Start this logger
	 *
	 * @return void
	 */
	public function start() {
		global $wpdb, $wp_object_cache;
		$this->start_time   = microtime( true );
		$this->query_offset = ! empty( $wpdb->queries ) ? count( $wpdb->queries ) : 0;
		$key                = array_search( $this, self::$active_loggers, true );

		if ( false === $key ) {
			self::$active_loggers[] = $this;
		}
		$this->cache_hit_offset  = ! empty( $wp_object_cache->cache_hits ) ? $wp_object_cache->cache_hits : 0;
		$this->cache_miss_offset = ! empty( $wp_object_cache->cache_misses ) ? $wp_object_cache->cache_misses : 0;
	}

	/**
	 * Whether or not the logger is running
	 *
	 * @return bool
	 */
	public function running() {
		return ! is_null( $this->start_time );
	}

	/**
	 * Stop this logger
	 *
	 * @return void
	 */
	public function stop() {
		global $wpdb, $wp_object_cache;

		if ( ! is_null( $this->start_time ) ) {
			$this->time += microtime( true ) - $this->start_time;
		}
		if ( ! is_null( $this->query_offset ) && isset( $wpdb ) && ! empty( $wpdb->queries ) ) {

			$query_total_count = count( $wpdb->queries );

			for ( $i = $this->query_offset; $i < $query_total_count; $i++ ) {
				$this->query_time += $wpdb->queries[ $i ][1];
				++$this->query_count;
				$this->query_indices[] = $i;
			}
		}

		if ( ! is_null( $this->cache_hit_offset ) && ! is_null( $this->cache_miss_offset ) && isset( $wp_object_cache ) ) {
			$cache_hits         = ! empty( $wp_object_cache->cache_hits ) ? $wp_object_cache->cache_hits : 0;
			$cache_misses       = ! empty( $wp_object_cache->cache_misses ) ? $wp_object_cache->cache_misses : 0;
			$this->cache_hits   = $cache_hits - $this->cache_hit_offset;
			$this->cache_misses = $cache_misses - $this->cache_miss_offset;
			$cache_total        = $this->cache_hits + $this->cache_misses;
			if ( $cache_total ) {
				$ratio             = ( $this->cache_hits / $cache_total ) * 100;
				$this->cache_ratio = round( $ratio, 2 ) . '%';
			}
		}

		$this->start_time        = null;
		$this->query_offset      = null;
		$this->cache_hit_offset  = null;
		$this->cache_miss_offset = null;
		$key                     = array_search( $this, self::$active_loggers, true );

		if ( false !== $key ) {
			unset( self::$active_loggers[ $key ] );
		}
	}

	/**
	 * Start this logger's hook timer
	 *
	 * @return void
	 */
	public function start_hook_timer() {
		++$this->hook_count;
		// Timer already running means a subhook has been called
		if ( ! is_null( $this->hook_start_time ) ) {
			++$this->hook_depth;
		} else {
			$this->hook_start_time = microtime( true );
		}
	}

	/**
	 * Stop this logger's hook timer
	 *
	 * @return void
	 */
	public function stop_hook_timer() {
		if ( $this->hook_depth ) {
			--$this->hook_depth;
		} else {
			if ( ! is_null( $this->hook_start_time ) ) {
				$this->hook_time += microtime( true ) - $this->hook_start_time;
			}
			$this->hook_start_time = null;
		}
	}

	/**
	 * Start this logger's request timer
	 *
	 * @return void
	 */
	public function start_request_timer() {
		++$this->request_count;
		$this->request_start_time = microtime( true );
	}

	/**
	 * Stop this logger's request timer
	 *
	 * @return void
	 */
	public function stop_request_timer() {
		if ( ! is_null( $this->request_start_time ) ) {
			$this->request_time += microtime( true ) - $this->request_start_time;
		}
		$this->request_start_time = null;
	}
}
