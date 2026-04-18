<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

define('ANM_EXPIRED_CLEANUP', 'anm_expired_cleanup');

/**
 * Scheduled Task: Remove tags from expired events daily at 12:01
 */
register_activation_hook(ANM_MAIN_FILE, function() {
    if (!wp_next_scheduled(ANM_EXPIRED_CLEANUP)) {
        // Calculate next 00:01 in site's local time
        $timezone = wp_timezone();
        $next_run = new \DateTime('today 00:01', $timezone);
        if ($next_run->getTimestamp() <= time()) {
            $next_run->modify('+1 day');
        }
        wp_schedule_event($next_run->getTimestamp(), 'daily', ANM_EXPIRED_CLEANUP);
    }
});

register_deactivation_hook(ANM_MAIN_FILE, function() {
    wp_clear_scheduled_hook(ANM_EXPIRED_CLEANUP);
});

// The actual cleanup task
add_action(ANM_EXPIRED_CLEANUP, function () {
    $settings = anm_get_settings();
    $today = date('Y-m-d'); // Current date in ISO format for reliable comparison
    $controlled_tags = [];
    foreach ($settings['categories'] as $category) {
        foreach ($settings['suffixes'] as $suffix) {            
            $controlled_tags[] = "{$category}{$suffix}";
        }
    }

    $args = [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'pending', 'draft', 'future', 'private'],
        'tax_query'      => [[
            'taxonomy' => 'post_tag',
            'field'    => 'slug',
            'terms'    => $controlled_tags,
        ]],
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'event_start_time',
                'value'   => $today,
                'compare' => '<',
                'type'    => 'DATE',
            ],
            [
                'key'     => 'expiry_date',
                'value'   => $today,
                'compare' => '<',
                'type'    => 'DATE',
            ],
        ],
    ];

    $query = new \WP_Query($args);

    if (!$query->have_posts()) return;

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();

        // Strip the tags to "Archive" the post from the manager
        wp_remove_object_terms($post_id, $controlled_tags, 'post_tag');

        // Purge caches so the site updates immediately
        anm_purge_notice_caches($post_id);
    }

    wp_reset_postdata();
});