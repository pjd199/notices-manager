<?php
/**
 * Plugin Name: Advanced Notices Manager
 * Description: Notice manager designed for Horsham Churches Together
 * Version: 1.0.43
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
if (file_exists(plugin_dir_path(ANM_MAIN_FILE) . 'vendor/autoload.php')) {
    require_once plugin_dir_path(ANM_MAIN_FILE) . 'vendor/autoload.php';
}

// include Sub-modules
require_once plugin_dir_path(ANM_MAIN_FILE) . 'includes/purge-cache.php';
require_once plugin_dir_path(ANM_MAIN_FILE) . 'includes/scheduled-cleanup.php';
require_once plugin_dir_path(ANM_MAIN_FILE) . 'includes/download-generator.php';
require_once plugin_dir_path(ANM_MAIN_FILE) . 'includes/post-meta.php';
require_once plugin_dir_path(ANM_MAIN_FILE) . 'includes/event-date-block.php';

// add admin pages
if (is_admin()) {
    //require_once plugin_dir_path(ANM_MAIN_FILE) . 'includes/acf-migration.php';
    require_once plugin_dir_path(ANM_MAIN_FILE) . 'admin/excerpt-word-count.php';
    require_once plugin_dir_path(ANM_MAIN_FILE) . 'admin/manager-page.php';
    require_once plugin_dir_path(ANM_MAIN_FILE) . 'admin/archive-page.php';
}

add_action('enqueue_block_editor_assets', function() {
    $asset_file = include(plugin_dir_path(ANM_MAIN_FILE) . 'build/index.asset.php');
    wp_enqueue_script(
        'anm-editor-js',
        plugin_dir_url(ANM_MAIN_FILE) . 'build/index.js',
        $asset_file['dependencies'],
        $asset_file['version']
    );
});

// Load MailPoet shortcodes, if MailPoet plugin is installed
add_action('plugins_loaded', function() {
    if (class_exists('MailPoet\API\API')) {
        require_once plugin_dir_path(__FILE__) . 'includes/mailpoet-integration.php';
    }
});

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