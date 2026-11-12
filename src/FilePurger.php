<?php
/**
 * Deletes cache files directly, for a server where PHP may write the nginx cache folder.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * Needs the constant BELTOFT_LWC_CACHE_PATH, the fastcgi_cache_path folder of this site. BELTOFT_LWC_CACHE_LEVELS
 * is the levels parameter of that path (default 1:2). The key format is the fastcgi_cache_key; the default is
 * "https$request_method$host$request_uri" and the filter beltoft_lwc_cache_key changes it. A cache file is named by the
 * md5 of its key and sits in folders taken from the end of that name, as many characters per level as the levels say.
 */
final class FilePurger implements Purger {

	/**
	 * The cache folder, or ''.
	 */
	public static function folder(): string {
		return defined( 'BELTOFT_LWC_CACHE_PATH' ) ? untrailingslashit( (string) BELTOFT_LWC_CACHE_PATH ) : '';
	}

	/**
	 * Whether the folder exists and PHP can delete in it.
	 */
	public function available(): bool {
		$folder = self::folder();

		return '' !== $folder && is_dir( $folder ) && wp_is_writable( $folder );
	}

	/**
	 * Name for the settings page.
	 */
	public function name(): string {
		return __( 'direct deletion', 'beltoft-lightweight-cache' );
	}

	/**
	 * The key of a request.
	 *
	 * @param string $method GET or HEAD.
	 * @param string $host   Host name, lower case.
	 * @param string $uri    Request path.
	 */
	public static function key( string $method, string $host, string $uri ): string {
		/**
		 * Filters the cache key of a request. The default matches fastcgi_cache_key "https$request_method$host$request_uri".
		 *
		 * @param string $key    The key.
		 * @param string $method GET or HEAD.
		 * @param string $host   Host name.
		 * @param string $uri    Request path.
		 */
		return (string) apply_filters( 'beltoft_lwc_cache_key', 'https' . $method . $host . $uri, $method, $host, $uri );
	}

	/**
	 * The path of the cache file of a key.
	 *
	 * @param string $folder The cache folder.
	 * @param string $key    The key.
	 * @param string $levels The levels parameter, for example 1:2.
	 */
	public static function file_of( string $folder, string $key, string $levels = '1:2' ): string {
		$hash = md5( $key );
		$end  = strlen( $hash );
		$path = $folder;

		foreach ( explode( ':', $levels ) as $length ) {
			$length = max( 1, min( 2, (int) $length ) );
			$end   -= $length;
			$path  .= '/' . substr( $hash, $end, $length );
		}

		return $path . '/' . $hash;
	}

	/**
	 * Deletes the cache files of the given addresses.
	 *
	 * @param string[] $uris     Exact request paths.
	 * @param string[] $prefixes Request path prefixes.
	 * @param bool     $all      Everything.
	 */
	public function purge( array $uris, array $prefixes, bool $all ): bool {
		if ( ! $this->available() ) {
			return false;
		}

		$folder = self::folder();

		if ( $all ) {
			$this->each_file( $folder, null );

			return true;
		}

		$levels = defined( 'BELTOFT_LWC_CACHE_LEVELS' ) ? (string) BELTOFT_LWC_CACHE_LEVELS : '1:2';
		$hosts  = Urls::hosts();

		foreach ( $uris as $uri ) {
			foreach ( $hosts as $host ) {
				foreach ( array( 'GET', 'HEAD' ) as $method ) {
					wp_delete_file( self::file_of( $folder, self::key( $method, $host, $uri ), $levels ) );
				}
			}
		}

		if ( array() !== $prefixes ) {
			$starts = array();

			foreach ( $prefixes as $prefix ) {
				foreach ( $hosts as $host ) {
					foreach ( array( 'GET', 'HEAD' ) as $method ) {
						$starts[] = self::key( $method, $host, $prefix );
					}
				}
			}

			$this->each_file( $folder, $starts );
		}

		return true;
	}

	/**
	 * Deletes the files below the folder: all of them, or those whose stored key starts with one of the given strings.
	 *
	 * @param string        $folder The cache folder.
	 * @param string[]|null $starts Key starts, or null for every file.
	 */
	private function each_file( string $folder, ?array $starts ): void {
		$items = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $folder, \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $items as $file ) {
			if ( ! $file->isFile() || $file->isLink() ) {
				continue;
			}

			if ( null !== $starts && ! self::key_starts( $file->getPathname(), $starts ) ) {
				continue;
			}

			wp_delete_file( $file->getPathname() );
		}
	}

	/**
	 * Whether the key stored in a cache file starts with one of the strings.
	 *
	 * @param string   $path   Cache file.
	 * @param string[] $starts Key starts.
	 */
	private static function key_starts( string $path, array $starts ): bool {
		$head = file_get_contents( $path, false, null, 0, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$at   = false === $head ? false : strpos( $head, "\nKEY: " );

		if ( false === $at ) {
			return false;
		}

		$end = strpos( $head, "\n", $at + 6 );
		$key = substr( $head, $at + 6, false === $end ? null : $end - $at - 6 );

		foreach ( $starts as $start ) {
			if ( 0 === strpos( $key, $start ) ) {
				return true;
			}
		}

		return false;
	}
}
