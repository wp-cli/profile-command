<?php

namespace runcommand\Profile;

class Formatter {

	private $formatter;

	private $args;

	public function __construct( &$assoc_args, $fields = null, $prefix = false ) {
		$format_args = array(
			'format' => 'table',
			'fields' => $fields,
			'field' => null
		);

		foreach ( array( 'format', 'fields', 'field' ) as $key ) {
			if ( isset( $assoc_args[ $key ] ) ) {
				$format_args[ $key ] = $assoc_args[ $key ];
			}
		}

		if ( ! is_array( $format_args['fields'] ) ) {
			$format_args['fields'] = explode( ',', $format_args['fields'] );
		}

		$format_args['fields'] = array_map( 'trim', $format_args['fields'] );

		$this->args = $format_args;
		$this->formatter = new \WP_CLI\Formatter( $assoc_args, $fields, $prefix );
	}

	/**
	 * Display multiple items according to the output arguments.
	 *
	 * @param array $items
	 */
	public function display_items( $items ) {
		if ( 'table' === $this->args['format'] && empty( $this->args['field'] ) ) {
			self::show_table( $items, $this->args['fields'] );
		} else {
			$this->formatter->display_items( $items );
		}
	}

	/**
	 * Show items in a \cli\Table.
	 *
	 * @param array $items
	 * @param array $fields
	 */
	private static function show_table( $items, $fields ) {
		$table = new \cli\Table();

		$enabled = \cli\Colors::shouldColorize();
		if ( $enabled ) {
			\cli\Colors::disable( true );
		}

		$table->setHeaders( $fields );

		$totals = array(
			'total',
		);
		foreach ( $items as $item ) {
			$values = array_values( \WP_CLI\Utils\pick_fields( $item, $fields ) );
			foreach( $values as $i => $value ) {
				if ( 0 === $i ) {
					continue;
				}
				if ( ! isset( $totals[ $i ] ) ) {
					if ( stripos( $fields[ $i ], '_ratio' ) ) {
						$totals[ $i ] = array();
					} else {
						$totals[ $i ] = 0;
					}
				}
				if ( stripos( $fields[ $i ], '_ratio' ) ) {
					if ( ! is_null( $value ) ) {
						$totals[ $i ][] = $value;
					}
				} else {
					$totals[ $i ] += $value;
				}
				if ( stripos( $fields[ $i ], '_time' ) || 'time' === $fields[ $i ] ) {
					$values[ $i ] = self::format_milliseconds( $value );
				}
			}
			$table->addRow( $values );
		}
		foreach( $totals as $i => $value ) {
			if ( stripos( $fields[ $i ], '_time' ) || 'time' === $fields[ $i ] ) {
				$totals[ $i ] = self::format_milliseconds( $value );
			}
			if ( is_array( $value ) ) {
				if ( ! empty( $value ) ) {
					$totals[ $i ] = round( ( array_sum( $value ) / count( $value ) ), 2 ) . '%';
				} else {
					$totals[ $i ] = null;
				}
			}
		}
		$table->setFooters( $totals );

		foreach( $table->getDisplayLines() as $line ) {
			\WP_CLI::line( $line );
		}

		if ( $enabled ) {
			\cli\Colors::enable( true );
		}
	}

	/**
	 * Format a time as milliseconds
	 */
	private static function format_milliseconds( $time ) {
		return round( ( $time * 1000 ), 2 ) . 'ms';
	}
}
