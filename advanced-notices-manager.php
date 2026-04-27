<?php
/**
 * Plugin Name: Advanced Notices Manager
 * Description: Notice manager designed for Horsham Churches Together
 * Version: 1.0.58
 * Author: Pete Dibdin
 * License: MIT
 * Plugin URI: https://github.com/pjd199/notices-manager
 */

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

define('ANM_MAIN_FILE', __FILE__);

global $wpdb;
define('ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE', $wpdb->prefix . 'advanced_notices_manager_archive');

/**
 * Retrieve default settings, with fallbacks
 */
function anm_get_settings() {
    static $settings = null;
    
    if ($settings === null) {
        $defaults = [
            'excerpt_min'   => 15,
            'excerpt_max'   => 35,
            'categories'    => ['introduction', 'news', 'events', 'prayer', 'jobs', 'volunteering'],
            'tags'          => ['full' => 'Full post', 'short' => 'Excerpt', 'list' => 'Thumbnail', 'website' => 'Online only'],
            'default_tag'   => 'short',
            'img_w'         => 16,
            'img_h'         => 9,
            'new_days'      => 5,  
            'stale_days'    => 18,
            'cleanup_cron'  => true,
            'cleanup_email' => true,
        ];
        $settings = wp_parse_args(get_option('anm_settings', []), $defaults);
        $settings['suffixes'] = array_map(fn($k) => "-$k", array_keys($settings['tags']));
    }
    return $settings;
}

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
    require_once plugin_dir_path(ANM_MAIN_FILE) . 'admin/settings-page.php';
    require_once plugin_dir_path(ANM_MAIN_FILE) . 'admin/manager-page.php';
    require_once plugin_dir_path(ANM_MAIN_FILE) . 'admin/archive-page.php';
    //require_once plugin_dir_path(ANM_MAIN_FILE) . 'includes/acf-migration.php';
}

add_action('enqueue_block_editor_assets', function() {
    $settings = anm_get_settings();
    $asset_file = include(plugin_dir_path(ANM_MAIN_FILE) . 'build/index.asset.php');
    wp_enqueue_script(
        'anm-editor-js',
        plugin_dir_url(ANM_MAIN_FILE) . 'build/index.js',
        $asset_file['dependencies'],
        $asset_file['version'],
        true
    );

    $encoded = json_encode([
        'excerptMin' => (int) $settings['excerpt_min'],
        'excerptMax' => (int) $settings['excerpt_max'],
        'targetCategories' => $settings['categories'],
        'tagSuffixes' => $settings['suffixes']
    ]);
    
    wp_add_inline_script(
        'anm-editor-js', 
        'window.ANM_SETTINGS = ' . $encoded . ';', 
        'before'
    );
});

// Load MailPoet shortcodes, if MailPoet plugin is installed
add_action('plugins_loaded', function() {
    if (class_exists('MailPoet\API\API')) {
        require_once plugin_dir_path(ANM_MAIN_FILE) . 'includes/mailpoet-integration.php';
    }
});

// Check for latest updates from GitHub
$updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/pjd199/notices-manager/',
	ANM_MAIN_FILE,
	'advanced-notices-manager'
);
$updateChecker->setBranch('main');
$updateChecker->getVcsApi()->enableReleaseAssets('/advanced-notices-manager-\d+\.\d+\.\d+.\.zip($|[?&#])/i');

/* Register menu item */
add_action('admin_menu', function () {
    add_submenu_page('edit.php', 'Notices Manager', 'Notices Manager', 'manage_options', 'notices-manager', __NAMESPACE__ .'\anm_render_page');
});

register_activation_hook(ANM_MAIN_FILE, function() {
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