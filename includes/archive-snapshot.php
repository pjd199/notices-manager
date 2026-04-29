<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

function create_archive_snapshot() {
    global $wpdb;
    $settings = anm_get_settings();

    $tags = array_merge(...array_map(
        fn($cat) => array_map(fn($s) => $cat . $s, $settings['suffixes']),
        $settings['categories']
    ));

    $ids = array_unique(get_posts([
        'tax_query'      => [['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $tags]],
        'fields'         => 'ids',
        'posts_per_page' => -1
    ]));
    sort($ids);
    
    $new_post_ids_string = implode(',', $ids);

    // 1. Check if an archive for TODAY already exists
    $today = date('Y-m-d');
    $existing_today = $wpdb->get_row($wpdb->prepare(
        "SELECT id, post_ids FROM " . ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE . " WHERE archive_date = %s",
        $today
    ));

    // 2. Determine if we actually need to save
    if ($existing_today) {
        // If it exists for today, only update if the IDs changed
        if ($existing_today->post_ids !== $new_post_ids_string) {
            $wpdb->update(
                ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE, 
                ['post_ids' => $new_post_ids_string], 
                ['id' => $existing_today->id]
            );
            return true;
        }
    } else {
        // If no entry exists for today, check against the absolute LATEST archive
        $last_archive = $wpdb->get_row("SELECT post_ids FROM " . ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE . " ORDER BY archive_date DESC LIMIT 1");
        
        if (!$last_archive || $last_archive->post_ids !== $new_post_ids_string) {
            $wpdb->insert(ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE, [
                'archive_date' => $today, 
                'post_ids'     => $new_post_ids_string
            ]);
            return true;
        }
    }

    return false;
}
