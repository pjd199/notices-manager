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

add_action('save_post', function($post_id) {
    if ( ! in_array(get_post_type($post_id), ['post', 'page']) ) {
        return;
    }
    if (get_post_meta($post_id, 'footnotes')) {
        delete_post_meta($post_id, 'footnotes');
    }
}, 20);


add_action('enqueue_block_editor_assets', function() {
    $asset_file = include(plugin_dir_path(ANM_MAIN_FILE) . 'build/index.asset.php');
    wp_enqueue_script(
        'anm-editor-js',
        plugin_dir_url(ANM_MAIN_FILE) . 'build/index.js',
        $asset_file['dependencies'],
        $asset_file['version']
    );
});