<?php

namespace WP_CLI\Profile;

/**
 * Logger for tracking individual database queries.
 */
class QueryLogger {

	public $query;
	public $time;
	public $caller;
	public $hook;
	public $callback;

	public function __construct( $query, $time, $caller, $hook = null, $callback = null ) {
		$this->query    = $query;
		$this->time     = $time;
		$this->caller   = $caller;
		$this->hook     = $hook;
		$this->callback = $callback;
	}
}
