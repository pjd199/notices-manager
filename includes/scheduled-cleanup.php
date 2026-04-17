<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

/**
 * Scheduled Task: Remove tags from expired events daily at 12:01
 */
register_activation_hook(ANM_MAIN_FILE, function() {
    if (!wp_next_scheduled('anm_expired_cleanup')) {
        // Calculate next 00:01 in site's local time
        $timezone = wp_timezone();
        $next_run = new \DateTime('today 00:01', $timezone);
        if ($next_run->getTimestamp() <= time()) {
            $next_run->modify('+1 day');
        }
        wp_schedule_event($next_run->getTimestamp(), 'daily', 'anm_expired_cleanup');
    }
});

register_deactivation_hook(ANM_MAIN_FILE, function() {
    wp_clear_scheduled_hook('anm_expired_cleanup');
});

// The actual cleanup task
add_action('anm_expired_cleanup', function () {
    $today = date('Y-m-d'); // Current date in ISO format for reliable comparison
    $controlled_tags = [
        'introduction-full',
        'news-full', 'news-short', 'news-list', 'news-website',
        'events-full', 'events-short', 'events-list', 'events-website',
        'prayer-full', 'prayer-short', 'prayer-list', 'prayer-website',
        'jobs-full', 'jobs-short', 'jobs-list', 'jobs-website',
        'volunteering-full', 'volunteering-short', 'volunteering-list', 'volunteering-website'
    ];

    $args = [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'pending', 'draft', 'future', 'private'],
        // We remove 'category_name' => 'events' so it checks ALL categories for the 'expires' field
        'tax_query'      => [[
            'taxonomy' => 'post_tag',
            'field'    => 'slug',
            'terms'    => $controlled_tags,
        ]],
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'event_start',
                'value'   => $today,
                'compare' => '<',
                'type'    => 'DATE',
            ],
            [
                'key'     => 'expire',
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