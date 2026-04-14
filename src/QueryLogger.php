<?php

namespace WP_CLI\Profile;

/**
 * Logger for tracking individual database queries.
 */
class QueryLogger {

	/** @var string The SQL query string. */
	public $query;

	/** @var float The time taken to execute the query, in seconds. */
	public $time;

	/** @var string The caller that initiated the query. */
	public $caller;

	/** @var string|null The hook associated with the query, if any. */
	public $hook;

	/** @var string|null The callback associated with the query, if any. */
	public $callback;

	/**
	 * QueryLogger constructor.
	 *
	 * @param string      $query    The SQL query string.
	 * @param float       $time     The time taken to execute the query, in seconds.
	 * @param string      $caller   The caller that initiated the query.
	 * @param string|null $hook     Optional. The hook associated with the query.
	 * @param string|null $callback Optional. The callback associated with the query.
	 */
	public function __construct( $query, $time, $caller, $hook = null, $callback = null ) {
		$this->query    = $query;
		$this->time     = $time;
		$this->caller   = $caller;
		$this->hook     = $hook;
		$this->callback = $callback;
	}
}
