<?php
/**
 * The screen, the admin bar and the translations loader.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc\Tests;

use BeltoftLwc\Admin;

/**
 * Capability and output.
 */
class AdminTest extends \WP_UnitTestCase {

	public function test_the_loader_is_hooked_on_init() {
		$this->assertSame( 10, has_action( 'init', 'beltoft_lightweight_cache_load_textdomain' ) );
	}

	public function test_the_admin_bar_item_is_for_administrators_only() {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$bar = new \WP_Admin_Bar();
		Admin::bar( $bar );
		$this->assertNull( $bar->get_node( 'beltoft-lwc-clear' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$bar = new \WP_Admin_Bar();
		Admin::bar( $bar );
		$node = $bar->get_node( 'beltoft-lwc-clear' );
		$this->assertNotNull( $node );
		$this->assertStringContainsString( '_wpnonce=', $node->href );
	}

	public function test_the_screen_shows_the_snippet_when_no_clearing_is_set_up() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		Admin::page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Automatic clearing: not set up on this server.', $html );
		$this->assertStringContainsString( 'fastcgi_ignore_headers Cache-Control Expires;', $html );
	}

	public function test_the_screen_prints_nothing_for_a_user_without_the_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		Admin::page();

		$this->assertSame( '', ob_get_clean() );
	}

	public function test_the_cli_state_reports_the_settings() {
		update_option( \BeltoftLwc\Settings::OPTION, array( 'enabled' => 0, 'ttl' => 1800 ) );
		$state = \BeltoftLwc\Cli::state();

		$this->assertFalse( $state['enabled'] );
		$this->assertSame( 1800, $state['ttl'] );
		$this->assertSame( '', $state['clearing'] );
		$this->assertFalse( $state['object_cache'] );
	}
}
