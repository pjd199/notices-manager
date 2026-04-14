<?php

namespace AdvancedNoticesManager;

add_action('init', function () {
    register_block_type(plugin_dir_path(ANM_MAIN_FILE) . 'build/event-date-block', [
        'render_callback' => function (array $attributes, string $content, \WP_Block $block): string {
            $post_id  = $block->context['postId'] ?? get_the_ID();
            $date_raw = get_post_meta($post_id, 'event_start_time', true);
            $hide_zero_minutes = $attributes['hideZeroMinutes'] ?? false;

            $date_format = $attributes['dateFormat'] === 'custom'
                ? ($attributes['customDateFormat'] ?? '')
                : $attributes['dateFormat'];

            $time_format = $attributes['timeFormat'] === 'custom'
                ? ($attributes['customTimeFormat'] ?? '')
                : $attributes['timeFormat'];

            $styles = [];
            if ( ! empty( $attributes['textAlign'] ) ) {
                $styles[] = 'text-align: ' . esc_attr( $attributes['textAlign'] ) . ';';
            }
            if ( ! empty( $attributes['fontWeight'] ) ) {
                $styles[] = 'font-weight: ' . esc_attr( $attributes['fontWeight'] ) . ';';
            }
            if ( ! empty( $attributes['fontStyle'] ) ) {
                $styles[] = 'font-style: ' . esc_attr( $attributes['fontStyle'] ) . ';';
            }

            // Combine manual styles into a single string
            $style_attr = ! empty( $styles ) ? ' style="' . implode( ' ', $styles ) . '"' : '';

            $wrapper_attributes = get_block_wrapper_attributes();
            $formatted = format_event_date_block($date_raw, $date_format, $time_format, $hide_zero_minutes);

            return sprintf(
                '<p %s%s>%s</p>', 
                $wrapper_attributes, 
                $style_attr, 
                esc_html($formatted)
            );
        },
    ]);
});

function format_event_date_block(string $date_raw, string $date_format, string $time_format, bool $hide_zero_minutes = false): string {
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
    
    // Logic to strip :00
    if ($hide_zero_minutes) {
        $time_part = preg_replace('/:00(\s?(am|pm|AM|PM))?/i', '$1', $time_part);
        $time_part = trim($time_part);
    }

    return $date_part . $separator . $time_part;
}