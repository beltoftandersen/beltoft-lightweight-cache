<?php
/**
 * WP-CLI commands.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * Manage the page cache from the command line.
 */
final class Cli {

	/**
	 * Registers the command when WP-CLI runs.
	 */
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'lightweight-cache', self::class );
		}
	}

	/**
	 * The state as plain data: whether pages are marked, the lifetime, the clearing method, the object cache.
	 *
	 * @return array{enabled:bool,ttl:int,clearing:string,object_cache:bool}
	 */
	public static function state(): array {
		$purger = Purge::purger();

		return array(
			'enabled'      => Settings::enabled(),
			'ttl'          => Settings::ttl(),
			'clearing'     => null === $purger ? '' : $purger->name(),
			'object_cache' => wp_using_ext_object_cache(),
		);
	}

	/**
	 * Prints the state.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table or json. Default table.
	 *
	 * @param string[]             $args  Positional arguments.
	 * @param array<string,string> $assoc Named arguments.
	 */
	public function status( $args, $assoc ): void {
		unset( $args );
		$state = self::state();

		if ( 'json' === ( $assoc['format'] ?? '' ) ) {
			\WP_CLI::line( (string) wp_json_encode( $state ) );

			return;
		}

		foreach ( $state as $key => $value ) {
			\WP_CLI::line( $key . ': ' . ( is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : (string) $value ) );
		}
	}

	/**
	 * Clears the page cache and the object cache.
	 *
	 * @param string[]             $args  Positional arguments.
	 * @param array<string,string> $assoc Named arguments.
	 */
	public function clear( $args, $assoc ): void {
		unset( $args, $assoc );
		$handed = Purge::now_all();
		wp_cache_flush();

		if ( $handed ) {
			\WP_CLI::success( 'Cache cleared.' );

			return;
		}

		\WP_CLI::warning( 'The object cache was cleared. Clearing the page cache is not set up on this server.' );
	}
}
