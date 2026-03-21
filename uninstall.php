<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

if (get_option('notices_manager_clean_on_uninstall')) {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}advanced_notices_manager_archive");
    delete_option('notices_manager_db_version');
    delete_option('notices_manager_clean_on_uninstall');
}