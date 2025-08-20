
== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ceb-block-cache/` directory, or install directly from the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. The plugin will automatically start caching server-side rendered blocks. No configuration is required.
4. Optionally, set `define( 'CEB_BLOCK_CACHE_DEBUG', true );` in the plugin file (or via a must-use plugin) to log cache activity for debugging.

== Frequently Asked Questions ==

= Does this work with static blocks? =
No. The plugin only caches **dynamic (server-side rendered)** blocks. Static `core/*` blocks are skipped.

= Will this break live preview in the block editor? =
No. The plugin skips caching in the WordPress admin, block editor, preview requests, and REST API contexts.

= What cache backend does it use? =
- If an external object cache is enabled (`wp_using_ext_object_cache()` returns true), it uses the configured backend (e.g., Redis, Memcached).  
- Otherwise, it falls back to using WordPress transients stored in the database.

= How long are blocks cached? =
By default, one hour (`HOUR_IN_SECONDS`). You can change this by modifying the `wp_cache_set()` or `set_transient()` expiration in the plugin code.

== Screenshots ==

1. Example debug log entry showing a cache hit/miss.
2. WP-CLI flushing the block cache.

== Changelog ==

= 0.1 =
* Initial release with block cache, auto-flushing hooks, and WP-CLI command.

== Upgrade Notice ==

= 0.1 =
First public release.

== License ==

MIT License  
https://opensource.org/licenses/MIT
