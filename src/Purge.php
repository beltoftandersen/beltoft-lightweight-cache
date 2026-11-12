<?php
/**
 * Collects the addresses to clear during a request and hands them to the purger once, at the end.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * A bulk edit asks for hundreds of addresses; they leave in one request.
 */
final class Purge {

	/**
	 * Most exact addresses and prefixes of one request; more than that clears everything.
	 */
	public const MAX_URIS     = 300;
	public const MAX_PREFIXES = 60;

	/**
	 * Exact paths waiting to be cleared.
	 *
	 * @var array<string,true>
	 */
	private static array $uris = array();

	/**
	 * Path prefixes waiting to be cleared.
	 *
	 * @var array<string,true>
	 */
	private static array $prefixes = array();

	/**
	 * Whether everything is to be cleared.
	 *
	 * @var bool
	 */
	private static bool $all = false;

	/**
	 * Whether the shutdown hook is set.
	 *
	 * @var bool
	 */
	private static bool $armed = false;

	/**
	 * The purger in use: the filter beltoft_lwc_purger decides; by default the request folder when it is set up,
	 * else direct deletion when the cache folder is set up.
	 */
	public static function purger(): ?Purger {
		$spool  = new SpoolPurger();
		$files  = new FilePurger();
		$chosen = $spool->available() ? $spool : ( $files->available() ? $files : null );

		/**
		 * Filters the purger. Return an object that implements BeltoftLwc\Purger, or null for none.
		 *
		 * @param Purger|null $purger The purger chosen from the constants.
		 */
		$chosen = apply_filters( 'beltoft_lwc_purger', $chosen );

		return $chosen instanceof Purger ? $chosen : null;
	}

	/**
	 * Queues exact paths and prefixes.
	 *
	 * @param string[] $uris     Exact paths.
	 * @param string[] $prefixes Path prefixes.
	 */
	public static function add( array $uris, array $prefixes = array() ): void {
		foreach ( $uris as $uri ) {
			$uri = Urls::clean( (string) $uri );

			if ( '' !== $uri ) {
				self::$uris[ $uri ] = true;
			}
		}

		foreach ( $prefixes as $prefix ) {
			$prefix = Urls::clean( (string) $prefix );

			if ( '/' === $prefix ) {
				self::$all = true; // A prefix of every address.
			} elseif ( '' !== $prefix ) {
				self::$prefixes[ $prefix ] = true;
			}
		}

		self::arm();
	}

	/**
	 * Queues a clear of everything.
	 */
	public static function add_all(): void {
		self::$all = true;
		self::arm();
	}

	/**
	 * Clears now, without waiting for the end of the request. Used by the button.
	 *
	 * @return bool Whether the request was handed to a purger.
	 */
	public static function now_all(): bool {
		self::add_all();

		return self::flush();
	}

	/**
	 * What is queued: exact paths, prefixes, all.
	 *
	 * @return array{uris:string[],prefixes:string[],all:bool}
	 */
	public static function queued(): array {
		return array(
			'uris'     => array_keys( self::$uris ),
			'prefixes' => array_keys( self::$prefixes ),
			'all'      => self::$all,
		);
	}

	/**
	 * Forgets the queue (tests).
	 */
	public static function reset(): void {
		self::$uris     = array();
		self::$prefixes = array();
		self::$all      = false;
	}

	/**
	 * Hooks the send at the end of the request, once.
	 */
	private static function arm(): void {
		if ( ! self::$armed ) {
			self::$armed = true;
			add_action( 'shutdown', array( __CLASS__, 'flush' ), 1000 );
		}
	}

	/**
	 * Sends the queue to the purger and empties it.
	 *
	 * @return bool Whether a purger took the request. False when nothing is queued or no purger is set up.
	 */
	public static function flush(): bool {
		$queue = self::queued();
		self::reset();

		if ( ! $queue['all'] && array() === $queue['uris'] && array() === $queue['prefixes'] ) {
			return false;
		}

		if ( count( $queue['uris'] ) > self::MAX_URIS || count( $queue['prefixes'] ) > self::MAX_PREFIXES ) {
			$queue['all'] = true;
		}

		$purger = self::purger();

		if ( null === $purger ) {
			return false;
		}

		return $purger->purge( $queue['uris'], $queue['prefixes'], $queue['all'] );
	}
}
