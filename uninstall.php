<?php
/**
 * Removes the plugin's option.
 *
 * @package BeltoftLwc
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'beltoft_lwc_settings' );
