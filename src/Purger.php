<?php
/**
 * A way to remove pages from the server's cache.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * Shipped: SpoolPurger (a request folder another process reads) and FilePurger (deletes cache files).
 */
interface Purger {

	/**
	 * Whether this method is set up on this server.
	 */
	public function available(): bool;

	/**
	 * A short name for the settings page.
	 */
	public function name(): string;

	/**
	 * Removes pages from the cache.
	 *
	 * @param string[] $uris     Exact request paths, for example /about/.
	 * @param string[] $prefixes Request path prefixes: everything below them, for example /category/news/.
	 * @param bool     $all      Whether to remove everything (the lists are then ignored).
	 * @return bool Whether the request was handed over.
	 */
	public function purge( array $uris, array $prefixes, bool $all ): bool;
}
