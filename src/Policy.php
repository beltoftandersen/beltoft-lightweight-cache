<?php
/**
 * The rules for when a page may be cached. Pure functions over plain facts, so they can be tested without a request.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * Cacheable or not.
 */
final class Policy {

	/**
	 * Cookies that mean the visitor sees something personal. Matched at the start of the cookie name.
	 */
	public const COOKIE_PREFIXES = array(
		'wordpress_logged_in_',
		'wp-postpass_',
		'comment_author_',
		'woocommerce_items_in_cart',
		'woocommerce_cart_hash',
		'wp_woocommerce_session_',
	);

	/**
	 * Content types of a response that may be cached.
	 */
	public const CONTENT_TYPES = array( 'text/html', 'text/xml', 'application/xml', 'application/rss+xml', 'application/atom+xml' );

	/**
	 * Whether the request may lead to a cacheable page. Facts:
	 * method, query (raw query string), cookies (names), and the booleans signed_in, admin, ajax, rest, cron, search,
	 * not_found, preview, shop_private (cart, checkout, account), password, blocked (DONOTCACHEPAGE or a filter).
	 *
	 * @param array<string,mixed> $facts The facts.
	 */
	public static function request_allows( array $facts ): bool {
		if ( ! in_array( $facts['method'] ?? '', array( 'GET', 'HEAD' ), true ) ) {
			return false;
		}

		foreach ( array( 'signed_in', 'admin', 'ajax', 'rest', 'cron', 'search', 'not_found', 'preview', 'shop_private', 'password', 'blocked' ) as $flag ) {
			if ( ! empty( $facts[ $flag ] ) ) {
				return false;
			}
		}

		if ( '' !== (string) ( $facts['query'] ?? '' ) && ! self::tracking_only( (string) $facts['query'] ) ) {
			return false;
		}

		foreach ( (array) ( $facts['cookies'] ?? array() ) as $name ) {
			foreach ( self::COOKIE_PREFIXES as $prefix ) {
				if ( 0 === strpos( (string) $name, $prefix ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Whether a query string only has tracking parameters (utm_*, fbclid, gclid).
	 *
	 * @param string $query The raw query string.
	 */
	public static function tracking_only( string $query ): bool {
		return 1 === preg_match( '/^(?:(?:utm_[a-z_]+|fbclid|gclid)=[^&]*(?:&|$))+$/i', $query );
	}

	/**
	 * Whether the response may be cached: status 200, no cookie set, no cache-control that forbids storing,
	 * no download, a page content type.
	 *
	 * @param int      $status  HTTP status code.
	 * @param string[] $headers Header lines as headers_list() gives them.
	 */
	public static function response_allows( int $status, array $headers ): bool {
		if ( 200 !== $status ) {
			return false;
		}

		$type = 'text/html'; // PHP's default when the page sets none.

		foreach ( $headers as $line ) {
			$parts = explode( ':', (string) $line, 2 );
			$name  = strtolower( trim( $parts[0] ) );
			$value = strtolower( trim( $parts[1] ?? '' ) );

			if ( in_array( $name, array( 'set-cookie', 'content-disposition', 'x-accel-expires', 'x-accel-redirect' ), true ) ) {
				return false;
			}

			if ( 'cache-control' === $name && 1 === preg_match( '/no-store|no-cache|private/', $value ) ) {
				return false;
			}

			if ( 'vary' === $name && '' !== trim( str_ireplace( 'accept-encoding', '', $value ), ' ,' ) ) {
				return false;
			}

			if ( 'content-type' === $name ) {
				$type = trim( explode( ';', $value )[0] );
			}
		}

		return in_array( $type, self::CONTENT_TYPES, true );
	}
}
