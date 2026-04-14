<?php

namespace AdvancedNoticesManager;

function render_event_date_block(array $attributes, string $content, \WP_Block $block): string {
    $post_id  = $block->context['postId'] ?? get_the_ID();
    $date_raw = get_post_meta($post_id, 'event_start_time', true);

    $date_format = $attributes['dateFormat'] === 'custom'
        ? ($attributes['customDateFormat'] ?? '')
        : $attributes['dateFormat'];

    $time_format = $attributes['timeFormat'] === 'custom'
        ? ($attributes['customTimeFormat'] ?? '')
        : $attributes['timeFormat'];

    $wrapper_attributes = get_block_wrapper_attributes();
    $formatted = format_event_date_block($date_raw, $date_format, $time_format);

    return sprintf('<p %s>%s</p>', $wrapper_attributes, esc_html($formatted));
}

function format_event_date_block(string $date_raw, string $date_format, string $time_format): string {
    if (!$date_raw) return '';

    try {
        $date = new \DateTime($date_raw);
    } catch (\Exception $e) {
        return '';
    }

    $date_part = $date->format($date_format);

    if (!$time_format || $time_format === 'none') return $date_part;

    // Separator: comma after a long-style date, space otherwise
    $separator = str_contains($date_format, 'l') ? ', ' : ' ';

    $time_part = $date->format($time_format);

    return $date_part . $separator . $time_part;
}

add_action('init', function () {
    register_block_type(plugin_dir_path(ANM_MAIN_FILE) . 'build/event-date-block', [
        'render_callback' => __NAMESPACE__ . '\\render_event_date_block',
    ]);
});

add_action('init', function() {
    $path = plugin_dir_path(ANM_MAIN_FILE) . 'build';
    error_log('Block path: ' . $path);
    error_log('block.json exists: ' . (file_exists($path . '/block.json') ? 'yes' : 'no'));
    error_log('event-date-block/block.json exists: ' . (file_exists($path . '/event-date-block/block.json') ? 'yes' : 'no'));
}, 20);