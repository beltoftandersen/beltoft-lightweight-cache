# Beltoft Lightweight Cache

A WordPress plugin for sites behind an nginx FastCGI page cache. It marks the pages that may be cached
(`X-Accel-Expires`), clears them when content changes, and adds a Clear cache button and an on/off switch.

The description, the nginx configuration, the two ways of clearing and the filters are in [readme.txt](readme.txt).

## Tests

    WP_TESTS_DIR=/path/to/wordpress-tests-lib phpunit

License: GPL-2.0-or-later.
