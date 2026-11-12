<?php
/**
 * The addresses that show a given post, product or comment.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * Every list is {uris: exact paths, prefixes: paths whose whole subtree is cleared}. The filter
 * beltoft_lwc_purge_urls changes a list before it is queued.
 */
final class Urls {

	/**
	 * The host names a cached page can have, lower case.
	 *
	 * @return string[]
	 */
	public static function hosts(): array {
		$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$hosts = '' === $host ? array() : array( strtolower( $host ) );

		/**
		 * Filters the host names the page cache holds pages for (used when files are deleted directly).
		 *
		 * @param string[] $hosts Host names.
		 */
		return array_values( array_unique( array_map( 'strval', (array) apply_filters( 'beltoft_lwc_hosts', $hosts ) ) ) );
	}

	/**
	 * The request path of a URL, as nginx sees it: path only, percent-encoded, starting with a slash.
	 * Returns '' for a URL that is not on this site.
	 *
	 * @param string $url A URL or a path.
	 */
	public static function path( string $url ): string {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) ) {
			return '';
		}

		if ( isset( $parts['host'] ) && ! in_array( strtolower( $parts['host'] ), self::hosts(), true ) ) {
			return '';
		}

		return self::clean( $parts['path'] ?? '/' );
	}

	/**
	 * A path as the purger takes it: starts with a slash, no query, only printable ASCII (the rest percent-encoded).
	 *
	 * @param string $path Path.
	 */
	public static function clean( string $path ): string {
		$path = (string) strtok( $path, '?#' );

		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		$path = preg_replace_callback(
			'/[^\x21-\x7e]/',
			static function ( array $m ): string {
				return rawurlencode( $m[0] );
			},
			$path
		);

		return strlen( (string) $path ) > 1000 ? '' : (string) $path;
	}

	/**
	 * A list from paths, skipping empty ones.
	 *
	 * @param string[] $uris     Exact paths.
	 * @param string[] $prefixes Prefixes.
	 * @return array{uris:string[],prefixes:string[]}
	 */
	private static function list( array $uris, array $prefixes ): array {
		return array(
			'uris'     => array_values( array_unique( array_filter( $uris ) ) ),
			// A prefix of "/" would be every page: never from a list, a filter that wants that clears everything.
			'prefixes' => array_values( array_diff( array_unique( array_filter( $prefixes ) ), array( '/' ) ) ),
		);
	}

	/**
	 * Paths of the term archives of a post's public taxonomies, and of their parents.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return string[]
	 */
	private static function term_paths( int $post_id, string $post_type ): array {
		$paths = array();

		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->public ) {
				continue;
			}

			$terms = get_the_terms( $post_id, $taxonomy->name );

			if ( ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$ids = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, $taxonomy->name, 'taxonomy' ) );

				foreach ( $ids as $id ) {
					$link = get_term_link( (int) $id, $taxonomy->name );

					if ( is_string( $link ) ) {
						$paths[] = self::path( $link );
					}
				}
			}
		}

		return $paths;
	}

	/**
	 * Home page, the page that lists posts, the main feed and the sitemaps.
	 *
	 * @return array{uris:string[],prefixes:string[]}
	 */
	public static function front(): array {
		$uris     = array( '/', '/feed/' );
		$prefixes = array( '/page/', '/wp-sitemap', '/sitemap' );
		$posts    = (int) get_option( 'page_for_posts' );

		if ( 'page' === get_option( 'show_on_front' ) && $posts > 0 ) {
			$path = self::path( (string) get_permalink( $posts ) );
			$uris[] = $path;
			$prefixes[] = $path;
		}

		return self::list( $uris, $prefixes );
	}

	/**
	 * The addresses that show a post: its own address, the front pages, its term, author, date and post type archives.
	 *
	 * @param \WP_Post $post The post.
	 * @return array{uris:string[],prefixes:string[]}
	 */
	public static function for_post( \WP_Post $post ): array {
		$front    = self::front();
		$uris     = $front['uris'];
		$prefixes = $front['prefixes'];
		$own      = self::path( (string) get_permalink( $post ) );
		$uris[]   = $own;

		if ( '/' !== $own ) {
			$prefixes[] = $own;
		}

		$prefixes = array_merge( $prefixes, self::term_paths( (int) $post->ID, $post->post_type ) );

		if ( '' !== (string) get_the_author_meta( 'user_nicename', (int) $post->post_author ) ) {
			$prefixes[] = self::path( get_author_posts_url( (int) $post->post_author ) );
		}

		$time       = strtotime( $post->post_date );
		$prefixes[] = self::path( get_year_link( (int) gmdate( 'Y', $time ) ) );

		$archive = 'post' === $post->post_type ? false : get_post_type_archive_link( $post->post_type );

		if ( is_string( $archive ) ) {
			$prefixes[] = self::path( $archive );
		}

		$list = self::list( $uris, $prefixes );

		/**
		 * Filters the addresses cleared for a post.
		 *
		 * @param array{uris:string[],prefixes:string[]} $list The addresses.
		 * @param \WP_Post                               $post The post.
		 */
		return apply_filters( 'beltoft_lwc_purge_urls', $list, $post );
	}

	/**
	 * The addresses that show a WooCommerce product: the product (a variation counts as its parent), the shop page,
	 * its categories and tags, the front pages.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return array{uris:string[],prefixes:string[]}
	 */
	public static function for_product( int $product_id ): array {
		$parent = (int) wp_get_post_parent_id( $product_id );
		$id     = 'product_variation' === get_post_type( $product_id ) && $parent > 0 ? $parent : $product_id;
		$post   = get_post( $id );

		if ( ! $post instanceof \WP_Post ) {
			return array(
				'uris'     => array(),
				'prefixes' => array(),
			);
		}

		$list = self::for_post( $post );

		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop = (int) wc_get_page_id( 'shop' );

			if ( $shop > 0 ) {
				$list['prefixes'][] = self::path( (string) get_permalink( $shop ) );
			}
		}

		$list = self::list( $list['uris'], $list['prefixes'] );

		/** This filter is documented in src/Urls.php. */
		return apply_filters( 'beltoft_lwc_purge_urls', $list, $post );
	}

	/**
	 * The addresses that show the comments of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{uris:string[],prefixes:string[]}
	 */
	public static function for_comments_of( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return array(
				'uris'     => array(),
				'prefixes' => array(),
			);
		}

		$own  = self::path( (string) get_permalink( $post ) );
		$list = self::list( array( $own ), '/' === $own ? array() : array( $own ) );

		/** This filter is documented in src/Urls.php. */
		return apply_filters( 'beltoft_lwc_purge_urls', $list, $post );
	}
}
