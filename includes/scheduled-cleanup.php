<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

define('ANM_EXPIRED_CLEANUP', 'anm_expired_cleanup');

/**
 * Scheduled Task: Remove tags from expired events daily at 00:15
 */
register_activation_hook(ANM_MAIN_FILE, function() {
    if (!wp_next_scheduled(ANM_EXPIRED_CLEANUP)) {
        // Calculate next 00:01 in site's local time
        $timezone = wp_timezone();
        $next_run = new \DateTime('today 00:15', $timezone);
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

    if (!$settings['cleanup_cron']) {
        return;
    }

    $today = current_time('Y-m-d');
    $controlled_tags = [];
    foreach ($settings['categories'] as $category) {
        foreach ($settings['suffixes'] as $suffix) {            
            $controlled_tags[] = "{$category}{$suffix}";
        }
    }

    $args = [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'post_status'    => ['publish'],
        'tax_query'      => [[
            'taxonomy' => 'post_tag',
            'field'    => 'slug',
            'terms'    => $controlled_tags,
        ]],
        'meta_query' => [
            'relation' => 'OR',
            [
                'relation' => 'AND',
                [
                    'key'     => 'event_start_time',
                    'value'   => '',
                    'compare' => '!=',
                ],
                [
                    'key'     => 'event_start_time',
                    'value'   => $today,
                    'compare' => '<',
                    'type'    => 'CHAR',
                ],
            ],
            [
                'relation' => 'AND',
                [
                    'key'     => 'expiry_date',
                    'value'   => '',
                    'compare' => '!=',
                ],
                [
                    'key'     => 'expiry_date',
                    'value'   => $today,
                    'compare' => '<',
                    'type'    => 'CHAR',
                ],
            ],
        ],
    ];

    $query = new \WP_Query($args);
    if (!$query->have_posts()) return;

    $cleaned_posts = [];

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();

        $expiry     = get_post_meta($post_id, 'expiry_date', true);
        $start_time = get_post_meta($post_id, 'event_start_time', true);

        $meta_parts = [];
        if ($expiry)     $meta_parts[] = 'expiry: ' . $expiry;
        if ($start_time) $meta_parts[] = 'start time: ' . $start_time;
        $meta_str = $meta_parts ? ' (' . implode(', ', $meta_parts) . ')' : '';

        $cleaned_posts[] = sprintf(
            '<li><a href="%s">%s</a>%s</li>',
            get_permalink($post_id),
            esc_html(get_the_title($post_id)),
            $meta_str
        );

        // Strip the tags to "Archive" the post from the manager
        wp_remove_object_terms($post_id, $controlled_tags, 'post_tag');

        // Purge caches so the site updates immediately
        anm_purge_notice_caches($post_id);
    }

    wp_reset_postdata();

    $count = count($cleaned_posts);
    if ($settings['cleanup_email'] && !empty($cleaned_posts)) {
        $subject = sprintf(
            '[%s] %d expired %s cleaned up',
            get_bloginfo('name'),
            $count,
            $count === 1 ? 'notice' : 'notices'
        );
        $message = sprintf(
            '<p>The following %s been archived by the Notices Manager on <strong>%s</strong>:</p><ul>%s</ul><p>This is an automated message from the Advanced Notices Manager plugin.</p>',
            $count === 1 ? 'post has' : 'posts have',
            get_bloginfo('name'),
            implode('', $cleaned_posts)
        );
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail(get_bloginfo('admin_email'), $subject, $message, $headers);
    }
});