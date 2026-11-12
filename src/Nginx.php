<?php
/**
 * The nginx configuration a server needs for this plugin.
 *
 * @package BeltoftLwc
 */

namespace BeltoftLwc;

defined( 'ABSPATH' ) || exit;

/**
 * The same text is in readme.txt.
 */
final class Nginx {

	/**
	 * The snippet: stores only responses that carry X-Accel-Expires, and only a 200.
	 */
	public static function snippet(): string {
		return <<<'CONF'
# http context
fastcgi_cache_path /var/cache/nginx/site levels=1:2 keys_zone=site:10m max_size=256m inactive=60m use_temp_path=off;

map $upstream_status $lwc_not_200 {
    200 0;
    default 1;
}

# server context
set $skip_cache 0;
if ($request_method = POST) { set $skip_cache 1; }
if ($query_string != "") { set $skip_cache 1; }
if ($request_uri ~* "/wp-admin/|/wp-login.php|/xmlrpc.php") { set $skip_cache 1; }
if ($http_cookie ~* "wordpress_logged_in_|wp-postpass_|comment_author_|woocommerce_items_in_cart|wp_woocommerce_session_") { set $skip_cache 1; }

# in the location that passes .php to PHP-FPM
fastcgi_cache site;
fastcgi_cache_key "https$request_method$host$request_uri";
fastcgi_ignore_headers Cache-Control Expires;
fastcgi_cache_bypass $skip_cache;
fastcgi_no_cache $skip_cache $lwc_not_200;
add_header X-Cache $upstream_cache_status always;
# no fastcgi_cache_valid: a page is stored only for the time in its X-Accel-Expires header
CONF;
	}
}
