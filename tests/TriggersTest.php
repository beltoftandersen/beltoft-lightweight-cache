<?php
/**
 * What a change clears.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc\Tests;

use BeltoftLwc\Purge;
use BeltoftLwc\Settings;

/**
 * Content changes queue the right addresses.
 */
class TriggersTest extends \WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Purge::reset();
	}

	public function tear_down(): void {
		Purge::reset();
		parent::tear_down();
	}

	public function test_publishing_queues_the_post_the_front_page_and_its_archives() {
		$term = self::factory()->category->create( array( 'name' => 'News' ) );
		Purge::reset();
		$id = self::factory()->post->create(
			array(
				'post_title'    => 'Hello',
				'post_status'   => 'publish',
				'post_category' => array( $term ),
			)
		);
		$post = get_post( $id );
		$q    = Purge::queued();

		$this->assertContains( wp_parse_url( get_permalink( $post ), PHP_URL_PATH ), $q['uris'] );
		$this->assertContains( '/', $q['uris'] );
		$this->assertContains( wp_parse_url( get_category_link( $term ), PHP_URL_PATH ), $q['prefixes'] );
		$this->assertContains( '/page/', $q['prefixes'] );
		$this->assertFalse( $q['all'] );
	}

	public function test_a_draft_clears_nothing_but_trashing_a_published_post_does() {
		$draft = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$this->assertSame( array(), Purge::queued()['uris'] );

		$id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		Purge::reset();
		wp_trash_post( $id );

		$this->assertNotSame( array(), Purge::queued()['uris'] );
		unset( $draft );
	}

	public function test_a_new_comment_clears_its_post() {
		$id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		Purge::reset();
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $id,
				'comment_approved' => 1,
			)
		);

		$this->assertContains( wp_parse_url( get_permalink( $id ), PHP_URL_PATH ), Purge::queued()['uris'] );
	}

	public function test_site_wide_changes_clear_everything() {
		foreach ( array( 'blogname', 'sidebars_widgets', 'widget_text', 'theme_mods_twentytwentyfive' ) as $option ) {
			Purge::reset();
			update_option( $option, 'x' . wp_rand() );
			$this->assertTrue( Purge::queued()['all'], $option );
		}

		Purge::reset();
		do_action( 'switch_theme' );
		$this->assertTrue( Purge::queued()['all'] );

		Purge::reset();
		update_option( 'some_unrelated_option', 'x' );
		$this->assertFalse( Purge::queued()['all'] );
	}

	public function test_a_menu_item_clears_everything() {
		Purge::reset();
		wp_update_nav_menu_object( 0, array( 'menu-name' => 'Main' ) );

		$this->assertTrue( Purge::queued()['all'] );
	}

	public function test_the_filter_changes_the_list() {
		add_filter(
			'beltoft_lwc_purge_urls',
			static function ( $list ) {
				$list['uris'][] = '/extra/';

				return $list;
			}
		);
		$id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->assertContains( '/extra/', Purge::queued()['uris'] );
		unset( $id );
	}

	public function test_switching_the_cache_off_clears_everything() {
		update_option( Settings::OPTION, array( 'enabled' => 1 ) );
		Purge::reset();
		update_option( Settings::OPTION, array( 'enabled' => 0 ) );

		$this->assertTrue( Purge::queued()['all'] );
	}

	public function test_settings_defaults_and_sanitising() {
		delete_option( Settings::OPTION );
		$this->assertSame(
			array(
				'enabled' => true,
				'ttl'     => 600,
			),
			Settings::get()
		);
		$this->assertSame(
			array(
				'enabled' => 0,
				'ttl'     => 600,
			),
			Settings::sanitize( array( 'ttl' => '7' ) )
		);
		$this->assertSame( 86400, Settings::sanitize( array( 'ttl' => '86400' ) )['ttl'] );

		add_filter(
			'beltoft_lwc_ttl',
			static function () {
				return 42;
			}
		);
		$this->assertSame( 42, Settings::ttl() );
	}
}
