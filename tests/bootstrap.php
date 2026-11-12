<?php
/**
 * PHPUnit bootstrap: runs against the WordPress test suite's own throwaway install (WP_TESTS_DIR).
 *
 * @package BeltoftLwc
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$beltoft_lwc_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';

require_once $beltoft_lwc_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/beltoft-lightweight-cache.php';
	}
);

require $beltoft_lwc_tests_dir . '/includes/bootstrap.php';
