<?php

namespace WP_CLI\Profile;

class Formatter {

	/**
	 * @var \WP_CLI\Formatter
	 */
	private $formatter;

	/**
	 * @var array<string, mixed>
	 */
	private $args;

	/**
	 * @var int|null
	 */
	private $total_cell_index;

	/**
	 * Formatter constructor.
	 *
	 * @param array<mixed>         $assoc_args
	 * @param array<string>|null   $fields
	 * @param string|false          $prefix
	 */
	public function __construct( &$assoc_args, $fields = null, $prefix = false ) {
		if ( null === $fields ) {
			$fields = [];
		}
		$format_args = array(
			'format' => 'table',
			'fields' => $fields,
			'field'  => null,
		);

		foreach ( array( 'format', 'fields', 'field' ) as $key ) {
			if ( isset( $assoc_args[ $key ] ) ) {
				$format_args[ $key ] = $assoc_args[ $key ];
			}
		}

		if ( ! is_array( $format_args['fields'] ) ) {
			$fields_val            = $format_args['fields'];
			$fields_str            = is_scalar( $fields_val ) ? (string) $fields_val : '';
			$format_args['fields'] = explode( ',', $fields_str );
		}

		$format_args['fields'] = array_filter(
			array_map(
				function ( $val ) {
					return trim( is_scalar( $val ) ? (string) $val : '' );
				},
				$format_args['fields']
			)
		);

		if ( isset( $assoc_args['fields'] ) ) {
			if ( empty( $format_args['fields'] ) ) {
				$format_args['fields'] = $fields;
			}
			$invalid_fields = array_diff( $format_args['fields'], $fields );
			if ( ! empty( $invalid_fields ) ) {
				\WP_CLI::error( 'Invalid field(s): ' . implode( ', ', $invalid_fields ) );
			}
		}

		if ( ! empty( $fields ) && 'time' !== $fields[0] ) {
			$index                  = array_search( $fields[0], $format_args['fields'], true );
			$this->total_cell_index = ( false !== $index ) ? (int) $index : null;
		}

		$this->args      = $format_args;
		$this->formatter = new \WP_CLI\Formatter( $assoc_args, $fields, $prefix );
	}

	/**
	 * Display multiple items according to the output arguments.
	 *
	 * @param array<\WP_CLI\Profile\Logger> $items
	 * @param bool                         $include_total
	 * @param string                       $order
	 * @param string|null                  $orderby
	 * @return void
	 */
	public function display_items( $items, $include_total, $order, $orderby ) {
		if ( 'table' === $this->args['format'] && empty( $this->args['field'] ) ) {
			/** @var array<string> $fields */
			$fields = $this->args['fields'];
			$this->show_table( $order, $orderby, $items, $fields, $include_total );
		} else {
			$this->formatter->display_items( $items );
		}
	}

	/**
	 * Function to compare floats.
	 *
	 * @param float $a Floating number.
	 * @param float $b Floating number.
	 * @return int
	 */
	private function compare_float( $a, $b ) {
		$a    = round( $a, 4 );
		$b    = round( $b, 4 );
		$diff = $a - $b;
		if ( 0.0 === $diff ) {
			return 0;
		} elseif ( $diff < 0 ) {
			return -1;
		} else {
			return 1;
		}
	}

	/**
	 * Show items in a \cli\Table.
	 *
	 * @param string                       $order
	 * @param string|null                  $orderby
	 * @param array<\WP_CLI\Profile\Logger> $items
	 * @param array<string>                $fields
	 * @param bool                         $include_total
	 * @return void
	 */
	private function show_table( $order, $orderby, $items, $fields, $include_total ) {
		$table = new \cli\Table();

		$enabled = \cli\Colors::shouldColorize();
		if ( $enabled ) {
			\cli\Colors::disable( true );
		}

		$table->setHeaders( $fields );

		$totals = array_fill( 0, count( $fields ), null );
		if ( ! is_null( $this->total_cell_index ) ) {
			$totals[ $this->total_cell_index ] = 'total (' . count( $items ) . ')';
		}

		if ( $orderby ) {
			usort(
				$items,
				function ( $a, $b ) use ( $order, $orderby ) {

					$orderby_array          = 'ASC' === $order ? array( $a, $b ) : array( $b, $a );
					list( $first, $second ) = $orderby_array;

					if ( is_numeric( $first->$orderby ) && is_numeric( $second->$orderby ) ) {
						return $this->compare_float( (float) $first->$orderby, (float) $second->$orderby );
					}

					return strcmp( $first->$orderby, $second->$orderby );
				}
			);
		}

		$location_index = array_search( 'location', $fields, true );
		foreach ( $items as $item ) {
			$values = array_values( \WP_CLI\Utils\pick_fields( $item, $fields ) );
			foreach ( $values as $i => $value ) {
				if ( ! is_null( $this->total_cell_index ) && $this->total_cell_index === $i ) {
					continue;
				}

				// Ignore 'location' for hook profiling
				if ( false !== $location_index && $location_index === $i ) {
					continue;
				}

				if ( null === $totals[ $i ] ) {
					if ( stripos( $fields[ $i ], '_ratio' ) ) {
						$totals[ $i ] = array();
					} else {
						$totals[ $i ] = 0;
					}
				}
				if ( stripos( $fields[ $i ], '_ratio' ) ) {
					if ( ! is_null( $value ) ) {
						assert( is_array( $totals[ $i ] ) );
						$totals[ $i ][] = $value;
					}
				} else {
					$current_total = is_numeric( $totals[ $i ] ) ? $totals[ $i ] : 0;
					$add_value     = is_numeric( $value ) ? $value : 0;
					$totals[ $i ]  = $current_total + $add_value;
				}
				if ( stripos( $fields[ $i ], '_time' ) || 'time' === $fields[ $i ] ) {
					$value_num    = is_numeric( $value ) ? (float) $value : 0.0;
					$values[ $i ] = round( $value_num, 4 ) . 's';
				}
			}
			$table->addRow( $values );
		}
		if ( $include_total ) {
			foreach ( $totals as $i => $value ) {
				if ( null === $value ) {
					continue;
				}
				if ( stripos( $fields[ $i ], '_time' ) || 'time' === $fields[ $i ] ) {
					assert( is_numeric( $value ) );
					$totals[ $i ] = round( (float) $value, 4 ) . 's';
				}
				if ( is_array( $value ) ) {
					if ( ! empty( $value ) ) {
						$float_values = array_map(
							function ( $val ) {
								return floatval( is_scalar( $val ) ? $val : 0 );
							},
							$value
						);
						$totals[ $i ] = round( ( array_sum( $float_values ) / count( $value ) ), 2 ) . '%';
					} else {
						$totals[ $i ] = null;
					}
				}
			}
			$table->setFooters( $totals );
		}

		foreach ( $table->getDisplayLines() as $line ) {
			\WP_CLI::line( $line );
		}

		if ( $enabled ) {
			\cli\Colors::enable( true );
		}
	}
}
