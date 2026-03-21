<?php

namespace AdvacncedNoticesManager;

/**
 * Custom helper to purge LiteSpeed cache for specific notice-related pages.
 */
function anm_purge_notice_caches($post_id) {
    clean_post_cache($post_id);

    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('posts');
    }
    
    if (!defined('LSCWP_V')) {
        return; // LiteSpeed isn't active, do nothing.
    }

    // 1. Purge the individual post itself
    do_action('litespeed_purge_post', $post_id);

    // 2. Define the specific notice-related URLs to clear
    $pages_to_clear = [
        '/',
        '/notices/',
        '/news/',
        '/events/'
    ];

    foreach ($pages_to_clear as $path) {
        $url = home_url($path);
        do_action('litespeed_purge_url', $url);
    }
}