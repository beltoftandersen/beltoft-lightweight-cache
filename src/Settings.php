<?php
/**
 * The stored settings.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * One option: whether pages are marked cacheable, and for how long.
 */
final class Settings {

	public const OPTION  = 'beltoft_lwc_settings';
	public const DEFAULT = 600;

	/**
	 * The lifetimes a site owner can choose, in seconds.
	 *
	 * @return int[]
	 */
	public static function lifetimes(): array {
		return array( 300, 600, 1800, 3600, 21600, 43200, 86400 );
	}

	/**
	 * The stored values with defaults.
	 *
	 * @return array{enabled:bool,ttl:int}
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$ttl    = isset( $stored['ttl'] ) ? (int) $stored['ttl'] : self::DEFAULT;

		return array(
			'enabled' => ! isset( $stored['enabled'] ) || ! empty( $stored['enabled'] ),
			'ttl'     => in_array( $ttl, self::lifetimes(), true ) ? $ttl : self::DEFAULT,
		);
	}

	/**
	 * Whether pages are marked cacheable.
	 */
	public static function enabled(): bool {
		return self::get()['enabled'];
	}

	/**
	 * The lifetime in seconds, after the filter.
	 */
	public static function ttl(): int {
		/**
		 * Filters the lifetime of a cached page in seconds.
		 *
		 * @param int $ttl Seconds.
		 */
		$ttl = (int) apply_filters( 'beltoft_lwc_ttl', self::get()['ttl'] );

		return max( 1, $ttl );
	}

	/**
	 * Cleans a submitted value.
	 *
	 * @param mixed $input Submitted values.
	 * @return array{enabled:int,ttl:int}
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$ttl   = isset( $input['ttl'] ) ? absint( $input['ttl'] ) : self::DEFAULT;

		return array(
			'enabled' => empty( $input['enabled'] ) ? 0 : 1,
			'ttl'     => in_array( $ttl, self::lifetimes(), true ) ? $ttl : self::DEFAULT,
		);
	}
}
