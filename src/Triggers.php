<?php
/**
 * Clears the cache when content changes.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * Per address where the affected pages are known, everything otherwise.
 */
final class Triggers {

	/**
	 * Post types that change many pages when they change (menus, block templates, shared blocks).
	 */
	public const SITEWIDE_TYPES = array( 'nav_menu_item', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_block' );

	/**
	 * Options that change the look of many pages. Names that start with one of the prefixes count too.
	 */
	public const SITEWIDE_OPTIONS  = array(
		'sidebars_widgets',
		'permalink_structure',
		'category_base',
		'tag_base',
		'blogname',
		'blogdescription',
		'home',
		'siteurl',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'posts_per_page',
		'stylesheet',
		'template',
		'active_plugins',
	);
	public const SITEWIDE_PREFIXES = array( 'widget_', 'theme_mods_' );

	/**
	 * Hooks everything.
	 */
	public static function register(): void {
		add_action( 'transition_post_status', array( __CLASS__, 'post_status' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'post_deleted' ) );

		foreach ( array( 'wp_insert_comment', 'edit_comment', 'deleted_comment', 'trashed_comment', 'untrashed_comment', 'spammed_comment', 'unspammed_comment' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'comment' ) );
		}
		add_action( 'transition_comment_status', array( __CLASS__, 'comment_status' ), 10, 3 );

		foreach ( array( 'wp_update_nav_menu', 'wp_delete_nav_menu', 'customize_save_after', 'switch_theme', 'activated_plugin', 'deactivated_plugin', 'upgrader_process_complete', '_core_updated_successfully', 'created_term', 'edited_term', 'delete_term' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'everything' ) );
		}

		add_filter( 'widget_update_callback', array( __CLASS__, 'widget_saved' ) );
		add_action( 'updated_option', array( __CLASS__, 'option_changed' ) );
		add_action( 'added_option', array( __CLASS__, 'option_changed' ) );

		// WooCommerce: product saves, stock and stock status.
		foreach ( array( 'woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_update_product_variation', 'woocommerce_new_product_variation' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'product_id' ) );
		}
		foreach ( array( 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'product_object' ) );
		}
		foreach ( array( 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'product_id' ) );
		}
		add_action( 'woocommerce_settings_saved', array( __CLASS__, 'everything' ) );
	}

	/**
	 * Queues the addresses of a list.
	 *
	 * @param array{uris:string[],prefixes:string[]} $list The list.
	 */
	private static function queue( array $list ): void {
		Purge::add( $list['uris'], $list['prefixes'] );
	}

	/**
	 * A post changed status (or was saved with the same status).
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       The post.
	 */
	public static function post_status( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		self::post( $post, 'publish' === $new_status || 'publish' === $old_status );
	}

	/**
	 * A post is about to be deleted for good.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function post_deleted( $post_id ): void {
		$post = get_post( (int) $post_id );

		if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
			self::post( $post, true );
		}
	}

	/**
	 * Queues what a post change affects.
	 *
	 * @param \WP_Post $post    The post.
	 * @param bool     $visible Whether the change is visible (the post is or was public).
	 */
	private static function post( \WP_Post $post, bool $visible ): void {
		if ( in_array( $post->post_type, self::SITEWIDE_TYPES, true ) ) {
			Purge::add_all();

			return;
		}

		if ( ! $visible || ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		self::queue( Urls::for_post( $post ) );
	}

	/**
	 * A comment changed.
	 *
	 * @param int|string $comment_id Comment ID.
	 */
	public static function comment( $comment_id ): void {
		$comment = get_comment( (int) $comment_id );

		if ( $comment instanceof \WP_Comment ) {
			self::queue( Urls::for_comments_of( (int) $comment->comment_post_ID ) );
		}
	}

	/**
	 * A comment changed status.
	 *
	 * @param string      $new_status New status.
	 * @param string      $old_status Old status.
	 * @param \WP_Comment $comment    The comment.
	 */
	public static function comment_status( $new_status, $old_status, $comment ): void {
		if ( $comment instanceof \WP_Comment && $new_status !== $old_status ) {
			self::queue( Urls::for_comments_of( (int) $comment->comment_post_ID ) );
		}
	}

	/**
	 * Something changed that every page shows.
	 */
	public static function everything(): void {
		Purge::add_all();
	}

	/**
	 * A widget was saved (a filter: the value goes on unchanged).
	 *
	 * @param mixed $instance The widget settings.
	 * @return mixed
	 */
	public static function widget_saved( $instance ) {
		Purge::add_all();

		return $instance;
	}

	/**
	 * An option changed.
	 *
	 * @param string $option Option name.
	 */
	public static function option_changed( $option ): void {
		$option = (string) $option;

		if ( in_array( $option, self::SITEWIDE_OPTIONS, true ) ) {
			Purge::add_all();

			return;
		}

		foreach ( self::SITEWIDE_PREFIXES as $prefix ) {
			if ( 0 === strpos( $option, $prefix ) ) {
				Purge::add_all();

				return;
			}
		}
	}

	/**
	 * A WooCommerce product or variation changed, by ID.
	 *
	 * @param int|string $product_id Product or variation ID.
	 */
	public static function product_id( $product_id ): void {
		self::queue( Urls::for_product( (int) $product_id ) );
	}

	/**
	 * A WooCommerce product or variation changed, by object.
	 *
	 * @param mixed $product The product.
	 */
	public static function product_object( $product ): void {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			self::queue( Urls::for_product( (int) $product->get_id() ) );
		}
	}
}
