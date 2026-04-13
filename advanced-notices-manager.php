<?php
/**
 * Plugin Name: Advanced Notices Manager
 * Description: Notice manager designed for Horsham Churches Together
 * Version: 1.0.40
 * Author: Pete Dibdin
 * License: MIT
 * Plugin URI: https://github.com/pjd199/notices-manager
 */

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

define('ANM_MAIN_FILE', __FILE__);

global $wpdb;
define('ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE', $wpdb->prefix . 'advanced_notices_manager_archive');

// Load Composer Autoloader (GitHub Actions will build this)
if (file_exists(plugin_dir_path(__FILE__) . 'vendor/autoload.php')) {
    require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
}

// include Sub-modules
require_once plugin_dir_path(__FILE__) . 'includes/purge-cache.php';
require_once plugin_dir_path(__FILE__) . 'includes/scheduled-cleanup.php';
require_once plugin_dir_path(__FILE__) . 'includes/download-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/post-meta.php';

if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/excerpt-word-count.php';
    require_once plugin_dir_path(__FILE__) . 'admin/manager-page.php';
    require_once plugin_dir_path(__FILE__) . 'admin/archive-page.php';
}

if (class_exists('MailPoet\API\API')) {
    require_once plugin_dir_path(__FILE__) . 'includes/mailpoet-integration.php';
}

// Check for latest updates from GitHub
$updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/pjd199/notices-manager/',
	__FILE__,
	'advanced-notices-manager'
);
$updateChecker->setBranch('main');
$updateChecker->getVcsApi()->enableReleaseAssets('/advanced-notices-manager-\d+\.\d+\.\d+.\.zip($|[?&#])/i');

/* Register menu item */
add_action('admin_menu', function () {
    add_submenu_page('edit.php', 'Notices Manager', 'Notices Manager', 'manage_options', 'notices-manager', __NAMESPACE__ .'\anm_render_page');
});

// Register cleanup tasks
register_activation_hook(__FILE__, __NAMESPACE__.'\register_expired_cleanup_task');
register_deactivation_hook(__FILE__, __NAMESPACE__.'\deregister_expired_cleanup_task');

register_activation_hook(__FILE__, function() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE ". ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE . " (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        archive_date date NOT NULL,
        post_ids text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY archive_date (archive_date)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    update_option('notices_manager_db_version', '1.1');
});