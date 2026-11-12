=== Beltoft Lightweight Cache ===
Contributors: beltoft
Tags: cache, page cache, nginx, fastcgi, performance
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Controls for a page cache in nginx: marks the pages that may be cached, clears them when content changes, and adds a Clear cache button.

== Description ==

For a site behind an nginx FastCGI cache. The plugin does three things.

**It decides what may be cached.** A page is sent with the response header `X-Accel-Expires` only when it may be cached: a visitor who is not signed in, a GET or HEAD request, status 200, no cookie set, no cart, checkout or account page, no search, preview or error page, no query string (tracking parameters such as utm_source and fbclid excepted), and no `Cache-Control` that forbids storing. The server is set up to store only responses that carry the header. With the plugin deactivated, or the page cache switched off in its settings, nothing is stored.

**It clears pages when content changes.** Per address where the affected pages are known, everything otherwise:

* A post or page is published, updated, trashed or deleted: its address, the home page, the page that lists posts, the main feed, the sitemaps, and the archives of its categories, tags, author, year and post type.
* A comment is approved, edited or removed: the address of its post.
* A menu, widget, theme, plugin, the permalink structure or the site title changes, or WordPress, a theme or a plugin is updated: everything.
* WooCommerce, when it is active: a product is saved, its stock or stock status changes, or a sale starts or ends: the product, the shop page, its categories and tags, the home page.

Requests are collected during a page load and sent once at the end, so a bulk edit sends one request.

**It gives a person a button.** Settings, Lightweight Cache has a Clear cache button, a switch for the page cache and the time a page stays cached (5 minutes to 24 hours, default 10 minutes). Administrators also get Clear cache in the admin bar. The button also calls `wp_cache_flush()`, so an object cache plugin is flushed with it.

= How clearing reaches the server =

PHP usually cannot delete the files nginx keeps. The plugin has two ways, and takes the first that is set up:

1. A request folder. Define `BELTOFT_LWC_PURGE_SPOOL` as a folder that PHP can add files to. Each request is one small JSON file, `{"v":1,"site":"...","all":false,"uris":["/about/"],"prefixes":["/news/"]}`, written under a temporary name and renamed. A process with the right to delete reads the folder, removes the cache files of those addresses and deletes the request file. `BELTOFT_LWC_SITE_ID`, when defined, is sent as `site`.
2. Direct deletion. Define `BELTOFT_LWC_CACHE_PATH` as the `fastcgi_cache_path` folder of the site when PHP may delete in it. `BELTOFT_LWC_CACHE_LEVELS` is the `levels` parameter (default `1:2`). A cache file is named by the md5 of the cache key, in folders taken from the end of the name. The default key is `https$request_method$host$request_uri`; the filter `beltoft_lwc_cache_key` changes it.

With neither set up the plugin still marks pages, and its settings screen says that automatic clearing is not set up on this server.

= The nginx configuration =

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

Without `fastcgi_cache_valid`, nginx stores a response only when it carries `X-Accel-Expires` with a positive number; a response with `Set-Cookie` is not stored. `X-Accel-Expires` is not passed on to the visitor. The `map` keeps nginx from storing any status but 200, because `X-Accel-Expires` would otherwise store an error page too. Do not use `fastcgi_cache_lock` with this setup: a page that is not stored would make requests for the same address wait for each other.

= Filters =

* `beltoft_lwc_cacheable` (bool, facts array): whether the current request may be cached.
* `beltoft_lwc_ttl` (int): the lifetime in seconds.
* `beltoft_lwc_purge_urls` (array with `uris` and `prefixes`, object): the addresses cleared for a post or product.
* `beltoft_lwc_purger` (Purger or null): the method that clears pages. An object that implements `BeltoftLwc\Purger`.
* `beltoft_lwc_cache_key` and `beltoft_lwc_hosts`: the key format and host names used by direct deletion.

A page can also opt out for one request with `define( 'DONOTCACHEPAGE', true );`.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` and activate it.
2. Set up the nginx configuration above, and one of the two ways of clearing.
3. Open Settings, Lightweight Cache.

== Frequently Asked Questions ==

= Can I use another caching plugin? =

Yes. Switch the page cache off in Settings, Lightweight Cache, or deactivate or delete the plugin. Pages it let the server store are cleared when it is deactivated.

= Does it cache logged-in visitors? =

No. A signed-in visitor, a visitor with items in a cart and a visitor who left a comment always get a fresh page.

== Changelog ==

= 0.1.0 =
* First release.
