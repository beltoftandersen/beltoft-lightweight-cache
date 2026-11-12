<?php
/**
 * Plugin Name:          Beltoft Lightweight Cache
 * Plugin URI:           https://beltoft.net/
 * Description:          Page cache controls for a server that caches pages in nginx: marks the pages that may be cached, clears them when content changes, and adds a Clear cache button.
 * Version:              0.1.0
 * Requires at least:    6.5
 * Requires PHP:         8.0
 * Author:               beltoft.net
 * Author URI:           https://beltoft.net/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          beltoft-lightweight-cache
 * Domain Path:          /languages
 *
 * @package BeltoftLwc
 */

defined( 'ABSPATH' ) || exit;

define( 'BELTOFT_LWC_FILE', __FILE__ );
define( 'BELTOFT_LWC_PATH', plugin_dir_path( __FILE__ ) );
define( 'BELTOFT_LWC_VERSION', '0.1.0' );

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'BeltoftLwc\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) );
		$file     = BELTOFT_LWC_PATH . 'src/' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

add_action( 'init', 'beltoft_lightweight_cache_load_textdomain' );

/**
 * Loads the bundled translations.
 */
function beltoft_lightweight_cache_load_textdomain() {
	load_plugin_textdomain( 'beltoft-lightweight-cache', false, dirname( plugin_basename( BELTOFT_LWC_FILE ) ) . '/languages' );
}

\BeltoftLwc\Plugin::boot();
