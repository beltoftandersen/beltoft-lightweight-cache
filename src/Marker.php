<?php
/**
 * Marks the pages that may be cached with the response header X-Accel-Expires.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * The server stores only what carries the header: without this plugin, or with the cache switched off, nothing is stored.
 */
final class Marker {

	/**
	 * Hooks the decision at the end of template_redirect.
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'start' ), PHP_INT_MAX );
	}

	/**
	 * The facts about the current request, and the verdict of the rules and the filter under the key "allowed".
	 *
	 * @return array<string,mixed>
	 */
	public static function facts(): array {
		$facts = array(
			'method'       => isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '',
			'query'        => isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared with a fixed pattern.
			'cookies'      => array_map( 'strval', array_keys( $_COOKIE ) ),
			'signed_in'    => is_user_logged_in(),
			'admin'        => is_admin(),
			'ajax'         => wp_doing_ajax(),
			'rest'         => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'cron'         => wp_doing_cron(),
			'search'       => is_search(),
			'not_found'    => is_404(),
			'preview'      => is_preview() || is_customize_preview(),
			'shop_private' => self::shop_private(),
			'password'     => is_singular() && post_password_required(),
			'blocked'      => defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE,
		);

		/**
		 * Filters whether the current request may be cached. Return false to keep a page out of the cache.
		 *
		 * @param bool                $cacheable Whether the rules allow it.
		 * @param array<string,mixed> $facts     The facts the rules looked at.
		 */
		$facts['allowed'] = (bool) apply_filters( 'beltoft_lwc_cacheable', Policy::request_allows( $facts ), $facts );

		return $facts;
	}

	/**
	 * Cart, checkout and account pages of WooCommerce, when WooCommerce is active.
	 */
	private static function shop_private(): bool {
		return ( function_exists( 'is_cart' ) && is_cart() )
			|| ( function_exists( 'is_checkout' ) && is_checkout() )
			|| ( function_exists( 'is_account_page' ) && is_account_page() )
			|| ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() );
	}

	/**
	 * Starts output buffering when the request may be cached; the buffer callback adds the header once the
	 * final status and headers are known.
	 */
	public static function start(): void {
		if ( ! Settings::enabled() ) {
			return;
		}

		if ( ! self::facts()['allowed'] ) {
			return;
		}

		ob_start( array( __CLASS__, 'finish' ) );
	}

	/**
	 * Output callback: adds X-Accel-Expires when the finished response may be cached.
	 *
	 * @param string $buffer The page.
	 * @return string
	 */
	public static function finish( $buffer ) {
		if ( headers_sent() || ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) ) {
			return $buffer;
		}

		if ( Policy::response_allows( (int) http_response_code(), headers_list() ) ) {
			header( 'X-Accel-Expires: ' . Settings::ttl() );
		}

		return $buffer;
	}
}
