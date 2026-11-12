<?php
/**
 * Hands a clear request to a folder that another process reads (on a hosting platform, a runner with the rights to delete).
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * Needs the constant BELTOFT_LWC_PURGE_SPOOL, the folder the requests go into. BELTOFT_LWC_SITE_ID, when defined,
 * names the site in the request. Each request is one small JSON file: {"v":1,"site":"...","all":false,"uris":[],"prefixes":[]}.
 * The file is written under a temporary name and renamed, so the reader never sees half a file.
 */
final class SpoolPurger implements Purger {

	/**
	 * The spool folder, or ''.
	 */
	public static function folder(): string {
		return defined( 'BELTOFT_LWC_PURGE_SPOOL' ) ? untrailingslashit( (string) BELTOFT_LWC_PURGE_SPOOL ) : '';
	}

	/**
	 * Whether the folder exists and PHP can add a file to it.
	 */
	public function available(): bool {
		$folder = self::folder();

		return '' !== $folder && is_dir( $folder ) && wp_is_writable( $folder );
	}

	/**
	 * Name for the settings page.
	 */
	public function name(): string {
		return __( 'request folder', 'beltoft-lightweight-cache' );
	}

	/**
	 * Writes one request file.
	 *
	 * @param string[] $uris     Exact request paths.
	 * @param string[] $prefixes Request path prefixes.
	 * @param bool     $all      Everything.
	 */
	public function purge( array $uris, array $prefixes, bool $all ): bool {
		if ( ! $this->available() ) {
			return false;
		}

		$request = array(
			'v'        => 1,
			'all'      => $all,
			'uris'     => $all ? array() : array_values( $uris ),
			'prefixes' => $all ? array() : array_values( $prefixes ),
		);

		if ( defined( 'BELTOFT_LWC_SITE_ID' ) ) {
			$request['site'] = (string) BELTOFT_LWC_SITE_ID;
		}

		$name = bin2hex( random_bytes( 8 ) );
		$temp = self::folder() . '/.tmp-' . $name;
		$done = self::folder() . '/' . $name . '.req';

		// The documented purge request: one small file into the request folder, not a file of the site.
		if ( false === file_put_contents( $temp, wp_json_encode( $request ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return false;
		}

		if ( ! rename( $temp, $done ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			wp_delete_file( $temp );

			return false;
		}

		return true;
	}
}
