<?php
/**
 * Boots the plugin.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * Pages are marked cacheable, content changes clear them, and the settings screen can clear them by hand.
 */
final class Plugin {

	/**
	 * Hooks every part.
	 */
	public static function boot(): void {
		Marker::register();
		Triggers::register();
		Admin::register();
		Cli::register();

		// Switching the plugin off clears what it let the server store: nothing may keep caching afterwards.
		add_action( 'deactivate_' . plugin_basename( BELTOFT_LWC_FILE ), array( Purge::class, 'now_all' ) );
	}
}
