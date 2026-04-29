<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

/**
 * 1. SCHEDULE CRON ON ACTIVATION
 */
register_activation_hook(ANM_MAIN_FILE, function() {
    if (!wp_next_scheduled('advanced_notices_daily_archive')) {
        $timestamp = strtotime('23:00:00');
        if ($timestamp < time()) {
            $timestamp += DAY_IN_SECONDS;
        }
        wp_schedule_event($timestamp, 'daily', 'advanced_notices_daily_archive');
    }
});

add_action('advanced_notices_daily_archive', __NAMESPACE__ . '\create_archive_snapshot');

register_deactivation_hook(ANM_MAIN_FILE, function() {
    wp_clear_scheduled_hook('advanced_notices_daily_archive');
});