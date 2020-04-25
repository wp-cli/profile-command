<?php

namespace runcommand\Profile;

/**
 * Stream Wrapper Class to create a temporary file with ticks enabled
 * Props to https://github.com/hakre for the original P.O.C.
 *
 * Class FileStreamWrapper
 *
 * @package runcommand\Profile
 * @author  Derrick Hammer
 */
class FileStreamWrapper {
	/**
	 * @var string
	 */
	const PROTOCOL = 'file';
	/**
	 * @var string
	 */
	const PHP_TICK = "\ndeclare(ticks=1);\n";
	/**
	 * @var resource
	 */
	public $context;
	/**
	 * @var resource
	 */
	private $handle;
	/**
	 * @var string
	 */
	private $file;

	public function stream_open( $path, $mode, $options, &$opened_path ) {

		if ( isset( $this->handle ) ) {
			throw new \UnexpectedValueException( 'Handle congruency' );
		}

		$use_include_path = true;

		$context = $this->context;
		if ( null === $context ) {
			$context = stream_context_get_default();
		}
		self::restore();
		$data = @file_get_contents( $path );

		if ( false !== $data && preg_match( '~^(<\?php\s*)$~m', $data ) ) {
			$result     = preg_replace(
				'~^(<\?php\s*)$~m',
				'\\0' . self::PHP_TICK,
				$data,
				1
			);
			$pathinfo   = pathinfo( $path );
			$this->file = $pathinfo['dirname'] . DIRECTORY_SEPARATOR . $pathinfo['filename'] . '_profile.' . $pathinfo['extension'];
			file_put_contents( $this->file, $result );
			$handle = @fopen( $this->file, $mode, $use_include_path, $context );

		} else {
			$handle = @fopen( $path, $mode, $use_include_path, $context );
		}
		self::init();
		if ( false === $handle ) {
			return false;
		}


		$meta = stream_get_meta_data( $handle );
		if ( ! isset( $meta['uri'] ) ) {
			throw new \UnexpectedValueException( 'Uri not in meta data' );
		}

		$opened_path = $meta['uri'];

		$this->handle = $handle;

		if ( $this->file ) {
			register_shutdown_function( [ $this, 'cleanup' ] );
		}

		return true;
	}

	public static function restore() {
		$result = stream_wrapper_restore( self::PROTOCOL );
		if ( false === $result ) {
			throw new \UnexpectedValueException( 'Failed to restore' );
		}
	}

	public static function init() {
		$result = stream_wrapper_unregister( self::PROTOCOL );
		if ( false === $result ) {
			throw new \UnexpectedValueException( 'Failed to unregister' );
		}
		stream_wrapper_register( self::PROTOCOL, '\runcommand\Profile\FileStreamWrapper', 0 );
	}

	/**
	 * @return array
	 */
	public function stream_stat() {
		self::restore();
		$array = @fstat( $this->handle );
		self::init();

		return $array;
	}

	/**
	 * @param $count
	 *
	 * @return string
	 */
	public function stream_read( $count ) {
		self::restore();
		$result = fread( $this->handle, $count );
		self::init();

		return $result;
	}

	public function stream_eof() {
		self::restore();
		$result = @feof( $this->handle );
		self::init();

		return $result;
	}

	public function stream_set_option( $option, $arg1, $arg2 ) {
		return true;
	}

	public function url_stat( $path, $flags ) {
		self::restore();
		$array = @stat( $path );
		self::init();

		return $array;
	}

	public function cleanup() {
		@unlink( $this->file );
	}
}
