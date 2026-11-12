<?php
/**
 * The settings screen, the Clear cache button and the admin bar item.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * Settings, Lightweight Cache. Needs manage_options.
 */
final class Admin {

	public const SLUG   = 'beltoft-lightweight-cache';
	public const ACTION = 'beltoft_lwc_clear';

	/**
	 * Hooks the screens.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_clear' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'bar' ), 90 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'update_option_' . Settings::OPTION, array( __CLASS__, 'settings_changed' ), 10, 2 );
		add_action( 'add_option_' . Settings::OPTION, array( __CLASS__, 'settings_added' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( BELTOFT_LWC_FILE ), array( __CLASS__, 'links' ) );
	}

	/**
	 * Adds the screen under Settings.
	 */
	public static function menu(): void {
		add_options_page(
			__( 'Lightweight Cache', 'beltoft-lightweight-cache' ),
			__( 'Lightweight Cache', 'beltoft-lightweight-cache' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Registers the option.
	 */
	public static function settings(): void {
		register_setting(
			'beltoft_lwc',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * A Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function links( $links ): array {
		$links   = (array) $links;
		$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Settings', 'beltoft-lightweight-cache' ) . '</a>';

		return $links;
	}

	/**
	 * The address that clears the cache (a nonce protects it).
	 */
	public static function clear_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
	}

	/**
	 * Admin bar item for administrators.
	 *
	 * @param \WP_Admin_Bar $bar The admin bar.
	 */
	public static function bar( $bar ): void {
		if ( ! current_user_can( 'manage_options' ) || ! $bar instanceof \WP_Admin_Bar ) {
			return;
		}

		$bar->add_node(
			array(
				'id'    => 'beltoft-lwc-clear',
				'title' => esc_html__( 'Clear cache', 'beltoft-lightweight-cache' ),
				'href'  => self::clear_url(),
			)
		);
	}

	/**
	 * Clears the page cache and the object cache, then goes back.
	 */
	public static function handle_clear(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'beltoft-lightweight-cache' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION );

		$handed = Purge::now_all();
		wp_cache_flush();

		$back = wp_get_referer();
		$back = $back ? $back : admin_url( 'options-general.php?page=' . self::SLUG );

		wp_safe_redirect( add_query_arg( 'beltoft_lwc_cleared', $handed ? '1' : '0', $back ) );
		exit;
	}

	/**
	 * Clears everything when the cache is switched on or off, so no page stays that the new setting would not allow.
	 *
	 * @param mixed $old Old value.
	 * @param mixed $new New value.
	 */
	public static function settings_changed( $old, $new ): void {
		$old = is_array( $old ) ? $old : array();
		$new = is_array( $new ) ? $new : array();

		if ( ( $old['enabled'] ?? 1 ) !== ( $new['enabled'] ?? 1 ) ) {
			Purge::add_all();
		}
	}

	/**
	 * The option was saved for the first time (the defaults count as the old value).
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 */
	public static function settings_added( $option, $value ): void {
		unset( $option );
		self::settings_changed( array(), $value );
	}

	/**
	 * The result of a clear, shown once.
	 */
	public static function notice(): void {
		// A redirect flag, not a form: nothing is changed by reading it.
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['beltoft_lwc_cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( '1' === sanitize_text_field( wp_unslash( $_GET['beltoft_lwc_cleared'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cache cleared.', 'beltoft-lightweight-cache' ) . '</p></div>';

			return;
		}

		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'The object cache was cleared. The page cache was not: clearing is not set up on this server.', 'beltoft-lightweight-cache' ) . '</p></div>';
	}

	/**
	 * The settings screen.
	 */
	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::get();
		$purger   = Purge::purger();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Lightweight Cache', 'beltoft-lightweight-cache' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'beltoft_lwc' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Page cache', 'beltoft-lightweight-cache' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?> />
								<?php esc_html_e( 'Let the server cache pages for visitors who are not signed in', 'beltoft-lightweight-cache' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="beltoft-lwc-ttl"><?php esc_html_e( 'Keep a page cached for', 'beltoft-lightweight-cache' ); ?></label></th>
						<td>
							<select id="beltoft-lwc-ttl" name="<?php echo esc_attr( Settings::OPTION ); ?>[ttl]">
								<?php foreach ( Settings::lifetimes() as $seconds ) : ?>
									<option value="<?php echo esc_attr( (string) $seconds ); ?>" <?php selected( $settings['ttl'], $seconds ); ?>><?php echo esc_html( self::lifetime_label( $seconds ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Clear cache', 'beltoft-lightweight-cache' ); ?></h2>
			<p><?php esc_html_e( 'Removes every cached page and flushes the object cache.', 'beltoft-lightweight-cache' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( self::clear_url() ); ?>"><?php esc_html_e( 'Clear cache', 'beltoft-lightweight-cache' ); ?></a></p>

			<h2><?php esc_html_e( 'Status', 'beltoft-lightweight-cache' ); ?></h2>
			<ul>
				<li>
					<?php
					echo esc_html(
						$settings['enabled']
							? __( 'Page cache: on.', 'beltoft-lightweight-cache' )
							: __( 'Page cache: off. No page is marked for caching.', 'beltoft-lightweight-cache' )
					);
					?>
				</li>
				<li>
					<?php
					if ( null === $purger ) {
						esc_html_e( 'Automatic clearing: not set up on this server.', 'beltoft-lightweight-cache' );
					} else {
						/* translators: %s: the name of the clearing method. */
						echo esc_html( sprintf( __( 'Automatic clearing: on, by %s.', 'beltoft-lightweight-cache' ), $purger->name() ) );
					}
					?>
				</li>
				<li>
					<?php
					echo esc_html(
						wp_using_ext_object_cache()
							? __( 'Object cache: in use.', 'beltoft-lightweight-cache' )
							: __( 'Object cache: not in use.', 'beltoft-lightweight-cache' )
					);
					?>
				</li>
			</ul>

			<h2><?php esc_html_e( 'What is cached', 'beltoft-lightweight-cache' ); ?></h2>
			<p><?php esc_html_e( 'Cached: pages for visitors who are not signed in. A cached page is cleared when you change the content it shows.', 'beltoft-lightweight-cache' ); ?></p>
			<p><?php esc_html_e( 'Never cached: signed-in users, the admin area, carts, checkout and account pages, searches, previews, error pages, pages that set a cookie, and requests with a query string (tracking parameters excepted).', 'beltoft-lightweight-cache' ); ?></p>
			<p><?php esc_html_e( 'To use another caching plugin, switch the page cache off here or deactivate this plugin.', 'beltoft-lightweight-cache' ); ?></p>

			<?php if ( null === $purger ) : ?>
				<h2><?php esc_html_e( 'Automatic clearing', 'beltoft-lightweight-cache' ); ?></h2>
				<p><?php esc_html_e( 'Pages are marked for caching, but this server has no way set up to clear them when content changes, so a changed page can stay cached until its time is up. The server needs this configuration, and one of the constants BELTOFT_LWC_PURGE_SPOOL (a folder that a process with the right to delete reads) or BELTOFT_LWC_CACHE_PATH (the cache folder, when PHP may delete in it) in wp-config.php.', 'beltoft-lightweight-cache' ); ?></p>
				<pre><?php echo esc_html( Nginx::snippet() ); ?></pre>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * A lifetime as text.
	 *
	 * @param int $seconds Seconds.
	 */
	private static function lifetime_label( int $seconds ): string {
		$labels = array(
			300   => __( '5 minutes', 'beltoft-lightweight-cache' ),
			600   => __( '10 minutes', 'beltoft-lightweight-cache' ),
			1800  => __( '30 minutes', 'beltoft-lightweight-cache' ),
			3600  => __( '1 hour', 'beltoft-lightweight-cache' ),
			21600 => __( '6 hours', 'beltoft-lightweight-cache' ),
			43200 => __( '12 hours', 'beltoft-lightweight-cache' ),
			86400 => __( '24 hours', 'beltoft-lightweight-cache' ),
		);

		return $labels[ $seconds ] ?? (string) $seconds;
	}
}
