<?php
/**
 * Plugin Name: Advanced Notices Manager
 * Description: Notice manager designed for Horsham Churches Together
 * Version: 1.0.26
 * Author: Pete Dibdin
 * License: MIT
 * Plugin URI: https://github.com/pjd199/notices-manager
 */

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

// include Sub-modules
if ( is_admin() ) {
    require_once plugin_dir_path(__FILE__) . 'admin/purge-cache.php';
    require_once plugin_dir_path(__FILE__) . 'admin/scheduled-cleanup.php';
    require_once plugin_dir_path(__FILE__) . 'admin/manager-page.php';
}

/* Register menu item */
add_action('admin_menu', function () {
    add_submenu_page('edit.php', 'Notices Manager', 'Notices Manager', 'manage_options', 'notices-manager', __NAMESPACE__ .'\anm_render_page');
});

