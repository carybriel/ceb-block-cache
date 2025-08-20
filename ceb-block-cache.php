<?php
/**
 * Plugin Name: CEB Block Cache
 * Description: Caches server-side rendered Gutenberg blocks and flushes the cache when relevant content is updated.
 * Version: 0.1
 * Text Domain: ceb-block-cache
 * Author: Cary Briel
*/

define( 'CEB_BLOCK_CACHE_DEBUG', false ); // Set to false in production

// Core cache flush handler
function flush_block_cache() {
    if ( wp_using_ext_object_cache() && function_exists( 'wp_cache_flush_group' ) ) {
        wp_cache_flush_group( 'blocks_group' );
    } else {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_block_cache_%'
            )
        );
    }
}

// 1. Flush on post/page/template save
add_action( 'save_post', 'flush_block_cache', 10, 2 );

// 2. Flush on ACF field save
add_action( 'acf/save_post', 'flush_block_cache', 10, 1 );

// 3. Flush on nav menu changes
add_action( 'wp_update_nav_menu', 'flush_block_cache', 10, 2 );
add_action( 'wp_create_nav_menu', 'flush_block_cache', 10, 1 );
add_action( 'wp_delete_nav_menu', 'flush_block_cache', 10, 1 );

// 4. Flush on Customizer save
add_action( 'customize_save_after', 'flush_block_cache' );

// 5. Flush on general option or theme mod updates
//add_action( 'updated_option', 'flush_block_cache', 10, 3 );
add_action( 'update_theme_mods_' . get_stylesheet(), 'flush_block_cache' );

// 6. Flush on taxonomy changes
add_action( 'created_term', 'flush_block_cache' );
add_action( 'edited_term', 'flush_block_cache' );
add_action( 'delete_term', 'flush_block_cache' );

// 7. Flush on widget/sidebars updates
add_action( 'sidebar_admin_setup', 'flush_block_cache' );

// Render block filter with checksum caching
add_filter( 'render_block', 'ceb_cache_render_block', 9, 2 );

function ceb_cache_render_block( $html, $block ) {
    // Skip caching in admin and editor requests
    if (
        is_admin() || is_404() || ! is_main_query() ||
        defined( 'REST_REQUEST' ) ||
        ( isset( $_GET['context'] ) && $_GET['context'] === 'edit' ) ||
        ( isset( $_GET['preview'] ) && $_GET['preview'] === 'true' )
    ) {
        return $html;
    }

    $block_name = $block['blockName'] ?? 'unnamed';

    if ( $block_name === 'unnamed' || strpos( $block_name, 'core/' ) === 0 ) {
        return $html;
    }

    $attrs         = $block['attrs'] ?? [];
    $inner_content = $block['innerContent'] ?? [];
    ksort( $attrs );

    $checksum_input = serialize([
        'block'   => $block_name,
        'attrs'   => $attrs,
        'content' => $inner_content,
    ]);

    $checksum   = md5( $checksum_input );
    $cache_key  = "block_cache_{$checksum}";
    $group      = 'blocks_group';
    $using_obj = wp_using_ext_object_cache();

    $source = $using_obj ? 'object cache' : 'transient';
    $cached = $using_obj
        ? wp_cache_get( $cache_key, $group )
        : get_transient( 'block_cache_' . $checksum );

    if ( CEB_BLOCK_CACHE_DEBUG ) {
        error_log( "[CEB Block Cache] {$block_name} => {$cache_key} => " . ( $cached !== false && $cached !== '' ? 'HIT' : 'MISS' ) . " via {$source}" );
    }

    if ( $cached !== false && $cached !== '' ) {
        return $cached;
    }

    remove_filter( 'render_block', 'ceb_cache_render_block', 9 );
    $rendered = render_block( $block );
    add_filter( 'render_block', 'ceb_cache_render_block', 9, 2 );

    if ( $using_obj ) {
        wp_cache_set( $cache_key, $rendered, $group, HOUR_IN_SECONDS );
    } else {
        set_transient( 'block_cache_' . $checksum, $rendered, HOUR_IN_SECONDS );
    }

    return $rendered;
}

// WP-CLI command to flush block cache
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'blocks flush-cache', function () {
        flush_block_cache();
        WP_CLI::success( 'Block cache group flushed.' );
    });
}
