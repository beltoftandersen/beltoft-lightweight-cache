<?php
/**
 * The cacheable rules.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc\Tests;

use BeltoftLwc\Policy;

/**
 * Request and response rules.
 */
class PolicyTest extends \WP_UnitTestCase {

	/**
	 * Facts of a plain visit.
	 *
	 * @param array<string,mixed> $change Changes.
	 * @return array<string,mixed>
	 */
	private function facts( array $change = array() ): array {
		return array_merge(
			array(
				'method'  => 'GET',
				'query'   => '',
				'cookies' => array( 'something_else' ),
			),
			$change
		);
	}

	public function test_a_plain_visit_may_be_cached() {
		$this->assertTrue( Policy::request_allows( $this->facts() ) );
		$this->assertTrue( Policy::request_allows( $this->facts( array( 'method' => 'HEAD' ) ) ) );
	}

	public function test_post_and_other_methods_are_never_cached() {
		foreach ( array( 'POST', 'PUT', 'DELETE', '' ) as $method ) {
			$this->assertFalse( Policy::request_allows( $this->facts( array( 'method' => $method ) ) ), $method );
		}
	}

	public function test_each_flag_keeps_a_page_out() {
		foreach ( array( 'signed_in', 'admin', 'ajax', 'rest', 'cron', 'search', 'not_found', 'preview', 'shop_private', 'password', 'blocked' ) as $flag ) {
			$this->assertFalse( Policy::request_allows( $this->facts( array( $flag => true ) ) ), $flag );
		}
	}

	public function test_personal_cookies_keep_a_page_out() {
		foreach ( array( 'wordpress_logged_in_abc', 'wp-postpass_abc', 'comment_author_abc', 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_abc' ) as $cookie ) {
			$this->assertFalse( Policy::request_allows( $this->facts( array( 'cookies' => array( 'a', $cookie ) ) ) ), $cookie );
		}
	}

	public function test_a_query_string_keeps_a_page_out_except_tracking_parameters() {
		$this->assertFalse( Policy::request_allows( $this->facts( array( 'query' => 'p=1' ) ) ) );
		$this->assertFalse( Policy::request_allows( $this->facts( array( 'query' => 'utm_source=a&p=1' ) ) ) );
		$this->assertTrue( Policy::request_allows( $this->facts( array( 'query' => 'utm_source=a&fbclid=b' ) ) ) );
		$this->assertTrue( Policy::tracking_only( 'gclid=x' ) );
		$this->assertFalse( Policy::tracking_only( 'utm=x' ) );
	}

	public function test_a_response_needs_status_200() {
		$ok = array( 'Content-Type: text/html; charset=UTF-8' );

		$this->assertTrue( Policy::response_allows( 200, $ok ) );
		foreach ( array( 301, 302, 404, 500 ) as $status ) {
			$this->assertFalse( Policy::response_allows( $status, $ok ), (string) $status );
		}
	}

	public function test_a_response_that_sets_a_cookie_or_forbids_storing_is_not_cached() {
		$this->assertFalse( Policy::response_allows( 200, array( 'Set-Cookie: a=b' ) ) );
		$this->assertFalse( Policy::response_allows( 200, array( 'Cache-Control: no-store, max-age=0' ) ) );
		$this->assertFalse( Policy::response_allows( 200, array( 'Cache-Control: no-cache, must-revalidate' ) ) );
		$this->assertFalse( Policy::response_allows( 200, array( 'Cache-Control: private' ) ) );
		$this->assertFalse( Policy::response_allows( 200, array( 'Vary: Cookie' ) ) );
		$this->assertFalse( Policy::response_allows( 200, array( 'Content-Disposition: attachment' ) ) );
		$this->assertFalse( Policy::response_allows( 200, array( 'X-Accel-Expires: 5' ) ) );
	}

	public function test_content_types() {
		$this->assertTrue( Policy::response_allows( 200, array() ) );
		$this->assertTrue( Policy::response_allows( 200, array( 'Content-Type: application/rss+xml; charset=UTF-8' ) ) );
		$this->assertTrue( Policy::response_allows( 200, array( 'Vary: Accept-Encoding' ) ) );
		$this->assertFalse( Policy::response_allows( 200, array( 'Content-Type: application/json' ) ) );
		$this->assertFalse( Policy::response_allows( 200, array( 'Content-Type: application/pdf' ) ) );
	}
}
