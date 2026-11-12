<?php
/**
 * The queue, the purger choice and the two shipped purgers.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc\Tests;

use BeltoftLwc\FilePurger;
use BeltoftLwc\Purge;
use BeltoftLwc\Purger;
use BeltoftLwc\SpoolPurger;
use BeltoftLwc\Urls;

/**
 * Collects once, sends once.
 */
class PurgeTest extends \WP_UnitTestCase {

	/**
	 * A purger that records what it gets.
	 *
	 * @var object
	 */
	private $fake;

	public function set_up(): void {
		parent::set_up();
		Purge::reset();

		$this->fake = new class() implements Purger {
			/**
			 * Calls received.
			 *
			 * @var array<int,array>
			 */
			public array $calls = array();

			public function available(): bool {
				return true;
			}

			public function name(): string {
				return 'fake';
			}

			public function purge( array $uris, array $prefixes, bool $all ): bool {
				$this->calls[] = array( $uris, $prefixes, $all );

				return true;
			}
		};
		add_filter(
			'beltoft_lwc_purger',
			function () {
				return $this->fake;
			}
		);
	}

	public function tear_down(): void {
		Purge::reset();
		parent::tear_down();
	}

	public function test_requests_are_merged_and_sent_once() {
		Purge::add( array( '/a/', '/a/', '/b/' ), array( '/c/' ) );
		Purge::add( array( '/b/' ), array( '/c/', '/d/' ) );

		$this->assertTrue( Purge::flush() );
		$this->assertCount( 1, $this->fake->calls );
		$this->assertSame( array( array( '/a/', '/b/' ), array( '/c/', '/d/' ), false ), $this->fake->calls[0] );
		$this->assertFalse( Purge::flush(), 'An empty queue sends nothing.' );
		$this->assertCount( 1, $this->fake->calls );
	}

	public function test_too_many_addresses_clear_everything() {
		$uris = array();
		for ( $i = 0; $i <= Purge::MAX_URIS; $i++ ) {
			$uris[] = '/p' . $i . '/';
		}
		Purge::add( $uris );
		Purge::flush();

		$this->assertTrue( $this->fake->calls[0][2] );
	}

	public function test_clean_makes_a_safe_path() {
		$this->assertSame( '/a%20b/', Urls::clean( '/a b/?x=1' ) );
		$this->assertSame( '/x', Urls::clean( 'x#frag' ) );
		$this->assertSame( '', Urls::clean( '/' . str_repeat( 'a', 1200 ) ) );
		$this->assertSame( '/%C3%A6', Urls::clean( "/\xC3\xA6" ) );
	}

	public function test_no_purger_means_nothing_is_sent() {
		remove_all_filters( 'beltoft_lwc_purger' );
		add_filter( 'beltoft_lwc_purger', '__return_null' );
		Purge::add_all();

		$this->assertFalse( Purge::flush() );
	}

	public function test_spool_purger_writes_one_complete_request_file() {
		$dir = trailingslashit( get_temp_dir() ) . 'lwc-spool-' . wp_generate_password( 8, false );
		mkdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		define( 'BELTOFT_LWC_PURGE_SPOOL', $dir );
		define( 'BELTOFT_LWC_SITE_ID', 'shop' );

		$purger = new SpoolPurger();
		$this->assertTrue( $purger->available() );
		$this->assertTrue( $purger->purge( array( '/a/' ), array( '/b/' ), false ) );
		$this->assertTrue( $purger->purge( array( '/ignored/' ), array(), true ) );

		$files = glob( $dir . '/*' );
		$this->assertCount( 2, $files );
		$requests = array();
		foreach ( $files as $file ) {
			$this->assertStringEndsWith( '.req', $file, 'No temporary file is left.' );
			$requests[] = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		usort(
			$requests,
			static function ( $a, $b ) {
				return (int) $a['all'] <=> (int) $b['all'];
			}
		);
		$this->assertSame(
			array(
				'v'        => 1,
				'all'      => false,
				'uris'     => array( '/a/' ),
				'prefixes' => array( '/b/' ),
				'site'     => 'shop',
			),
			$requests[0]
		);
		$this->assertSame( array(), $requests[1]['uris'] );
		$this->assertTrue( $requests[1]['all'] );

		array_map( 'unlink', $files );
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	public function test_file_purger_finds_the_cache_file_of_a_key() {
		// Verified against nginx 1.26: key "httpsGET127.0.0.1/ttl.php?utm_source=a" with levels 1:2.
		$key = 'httpsGET127.0.0.1/exp.php?m=ttl';
		$this->assertSame( '/c/6/40/190ecdf3cefa596d61a53b3ea83ed406', FilePurger::file_of( '/c', $key ) );
		$this->assertSame( '/c/06/190ecdf3cefa596d61a53b3ea83ed406', FilePurger::file_of( '/c', $key, '2' ) );
	}

	public function test_file_purger_deletes_exact_prefix_and_all() {
		$dir = trailingslashit( get_temp_dir() ) . 'lwc-cache-' . wp_generate_password( 8, false );
		mkdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		define( 'BELTOFT_LWC_CACHE_PATH', $dir );

		$host  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$write = function ( string $key ) use ( $dir ): string {
			$file = FilePurger::file_of( $dir, $key );
			wp_mkdir_p( dirname( $file ) );
			file_put_contents( $file, "\x05\x00\x00\x00\nKEY: " . $key . "\n\nbody" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return $file;
		};
		$home   = $write( 'httpsGET' . $host . '/' );
		$home_h = $write( 'httpsHEAD' . $host . '/' );
		$page   = $write( 'httpsGET' . $host . '/news/page/2/' );
		$feed   = $write( 'httpsGET' . $host . '/news/feed/' );
		$other  = $write( 'httpsGET' . $host . '/about/' );

		$purger = new FilePurger();
		$this->assertTrue( $purger->available() );
		$purger->purge( array( '/' ), array( '/news/' ), false );

		$this->assertFileDoesNotExist( $home );
		$this->assertFileDoesNotExist( $home_h );
		$this->assertFileDoesNotExist( $page );
		$this->assertFileDoesNotExist( $feed );
		$this->assertFileExists( $other, 'An address that was not asked for stays.' );

		$purger->purge( array(), array(), true );
		$this->assertFileDoesNotExist( $other );

		$this->remove_tree( $dir );
	}

	/**
	 * Removes a folder and what is in it.
	 *
	 * @param string $dir Folder.
	 */
	private function remove_tree( string $dir ): void {
		$items = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );

		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : wp_delete_file( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
