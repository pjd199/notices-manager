<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

add_action('init', function () {
    register_block_type(plugin_dir_path(ANM_MAIN_FILE) . 'build/event-date-block', [
        'render_callback' => function (array $attributes, string $content, \WP_Block $block): string {
            $post_id  = $block->context['postId'] ?? get_the_ID();
            
            $date_raw = get_post_meta($post_id, 'event_start_time', true);
            if ( empty($date_raw) || !is_string($date_raw)) {
                return ''; 
            }

            $format = $attributes['format'] ?? 'l jS F Y, g:i a';
            $format = isset($attributes['format']) && is_string($attributes['format']) 
                        ? $attributes['format'] 
                        : 'l jS F Y, g:i a';                        
            $hide_zero_minutes = $attributes['hideZeroMinutes'] ?? false;
            
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
            try {
                $date = new \DateTime($date_raw);
                $formatted = $date->format($format);
                
                if ($hide_zero_minutes) {
                    $formatted = preg_replace('/:00(\s?(am|pm|AM|PM))?/i', '$1', $formatted);
                }
                return sprintf(
                    '<p %s%s>%s</p>', 
                    $wrapper_attributes, 
                    $style_attr, 
                    esc_html($formatted)
                );
            } catch (\Exception $e) {
                return '';
            }
        },
    ]);
});