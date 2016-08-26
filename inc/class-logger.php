<?php

namespace runcommand\Profile;

class Logger {

	public $execution_time = 0;
	public $queries = array(
		'count'   => 0,
		'time'    => 0,
	);
	public $hooks = array(
		'count'   => 0,
		'time'    => 0,
	);
	public $requests = array(
		'count'   => 0,
		'time'    => 0,
	);

	private $start_time = null;
	private $query_offset = null;
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
		global $wpdb;
		$this->start_time = microtime( true );
		$this->query_offset = ! empty( $wpdb->queries ) ? count( $wpdb->queries ) : 0;
		if ( false === ( $key = array_search( $this, self::$active_loggers ) ) ) {
			self::$active_loggers[] = $this;
		}
	}

	/**
	 * Stop this logger
	 */
	public function stop() {
		global $wpdb;

		if ( ! is_null( $this->start_time ) ) {
			$this->execution_time += microtime( true ) - $this->start_time;
		}
		if ( ! is_null( $this->query_offset ) ) {
			for ( $i = $this->query_offset; $i < count( $wpdb->queries ); $i++ ) {
				$this->queries['time'] += $wpdb->queries[ $i ][1];
				$this->queries['count']++;
			}
		}

		$this->start_time = null;
		$this->query_offset = null;
		if ( false !== ( $key = array_search( $this, self::$active_loggers ) ) ) {
			unset( self::$active_loggers[ $key ] );
		}
	}

	/**
	 * Start this logger's hook timer
	 */
	public function start_hook_timer() {
		// Timer already running means a subhook has been called
		if ( ! is_null( $this->hook_start_time ) ) {
			$this->hook_depth++;
		} else {
			$this->hooks['count']++;
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
				$this->hooks['time'] += microtime( true ) - $this->hook_start_time;
			}
			$this->hook_start_time = null;
		}
	}

	/**
	 * Start this logger's request timer
	 */
	public function start_request_timer() {
		$this->requests['count']++;
		$this->request_start_time = microtime( true );
	}

	/**
	 * Stop this logger's request timer
	 */
	public function stop_request_timer() {
		if ( ! is_null( $this->request_start_time ) ) {
			$this->requests['time'] += microtime( true ) - $this->request_start_time;
		}
		$this->request_start_time = null;
	}

}
