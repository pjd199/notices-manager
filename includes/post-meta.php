<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

/**
 * Ensure Meta is registered with strict types and sanitization
 */
add_action('init', function() {
    foreach (['event_start_time', 'expiry_date'] as $key) {
        register_post_meta('post', $key, [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
            'default'      => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { return current_user_can('edit_posts'); }
        ]);
    }
}, 20);

add_filter('rest_pre_insert_post', function($prepared_post, $request) {
    // Access the meta directly from the request object
    $meta = $request->get_param('meta');
    error_log("START");
    error_log(print_r($meta, true));
    return $prepared_post;
}, 10, 2);

/*
add_filter('rest_pre_insert_post', function($prepared_post, $request) {
    $meta = $request->get_param('meta');

    if (is_array($meta)) {
        foreach ($meta as $key => $value) {
            if ($value === '' || $value === null) {
                unset($meta[$key]);
            }
        }
        $request->set_param('meta', $meta);
    }

    return $prepared_post;
}, 10, 2);
*/

/*
add_filter('rest_pre_insert_post', function($prepared_post, $request) {
    // Access the meta directly from the request object
    $meta = $request->get_param('meta');
    error_log("START");
    error_log(print_r($meta, true));
    
    
    if (is_array($meta)) {
        // 1. Clean up Footnotes
        if (isset($meta['footnotes']) && empty($meta['footnotes'])) {
            unset($meta['footnotes']);
        }

        // 2. Clean up your custom dates
        if (isset($meta['event_start_time']) && empty($meta['event_start_time'])) {
            unset($meta['event_start_time']);
        }

        if (isset($meta['expiry_date']) && empty($meta['expiry_date'])) {
            unset($meta['expiry_date']);
        }

        // Re-set the cleaned meta back into the request
        error_log(print_r($meta, true));
        $request->set_param('meta', $meta);
    }
    error_log("DONE");
    return $prepared_post;
}, 10, 2);
*/

add_action('enqueue_block_editor_assets', function() {
    $asset_file = include(plugin_dir_path(ANM_MAIN_FILE) . 'build/index.asset.php');
    wp_enqueue_script(
        'anm-editor-js',
        plugin_dir_url(ANM_MAIN_FILE) . 'build/index.js',
        $asset_file['dependencies'],
        $asset_file['version']
    );
});