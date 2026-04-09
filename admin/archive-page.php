<?php

namespace AdvancedNoticesManager;

add_action('admin_menu', function() {
    add_submenu_page('edit.php', 'Notices Archive', 'Notices Archive', 'manage_options', 'notices-archive', __NAMESPACE__ .'\render_notices_archive_page');
});

/**
 * 1. SCHEDULE CRON ON ACTIVATION
 */
register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('advanced_notices_daily_archive')) {
        $timestamp = strtotime('23:00:00');
        if ($timestamp < time()) {
            $timestamp += DAY_IN_SECONDS;
        }
        wp_schedule_event($timestamp, 'daily', 'advanced_notices_daily_archive');
    }
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('advanced_notices_daily_archive');
});

/**
 * 2. CORE ARCHIVE LOGIC
 */
function create_automated_snapshot() {
    global $wpdb;
    
    $base_cats = ['introduction', 'news', 'events', 'jobs', 'prayer', 'volunteering'];
    $suffixes  = ['-full', '-short', '-list', '-website'];
    $all_ids   = [];

    foreach ($base_cats as $cat) {
        $tags = array_map(fn($s) => $cat . $s, $suffixes);
        $p_ids = get_posts([
            'category_name' => $cat,
            'tax_query'     => [['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $tags]],
            'fields'        => 'ids', 
            'posts_per_page' => -1
        ]);
        $all_ids = array_merge($all_ids, $p_ids);
    }
    
    $unique_ids = array_unique($all_ids);
    sort($unique_ids); 
    $new_post_ids_string = implode(',', $unique_ids);

    $last_archive = $wpdb->get_row("SELECT post_ids FROM " . ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE . " ORDER BY archive_date DESC LIMIT 1");

    if (!$last_archive || $last_archive->post_ids !== $new_post_ids_string) {
        $wpdb->replace(ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE, [
            'archive_date' => date('Y-m-d'), 
            'post_ids'     => $new_post_ids_string
        ]);
        return true; 
    }
    return false; 
}

add_action('advanced_notices_daily_archive', __NAMESPACE__ . '\create_automated_snapshot');

/**
 * 3. ACTIONS HANDLER (Export, Import, Delete)
 */
add_action('admin_init', function() {
    global $wpdb;

    if (!isset($_GET['page']) || $_GET['page'] !== 'notices-archive') {
        return;
    }

    if (isset($_GET['export_notices'])) {
        $data = $wpdb->get_results("SELECT * FROM " . ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE, ARRAY_A);
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="notices-archive-dump-'.date('Y-m-d').'.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        check_admin_referer('delete_notice_' . $_GET['id']);
        $wpdb->delete(ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE, ['id' => intval($_GET['id'])]);
        wp_redirect(admin_url('edit.php?page=notices-archive&deleted=1'));
        exit;
    }

    if (isset($_POST['process_import']) && !empty($_FILES['import_file']['tmp_name'])) {
        $json = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($json, true);
        if (is_array($import_data)) {
            foreach ($import_data as $row) {
                $wpdb->replace(ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE, [
                    'id'           => $row['id'] ?? null,
                    'archive_date' => $row['archive_date'], 
                    'post_ids'     => $row['post_ids'], 
                    'created_at'   => $row['created_at']
                ]);
            }
            wp_redirect(admin_url('edit.php?page=notices-archive&import_success=1'));
            exit;
        }
    }
});

/**
 * 4. UI RENDER
 */
function render_notices_archive_page() {
    global $wpdb;    

    if (isset($_POST['create_snapshot'])) {
        $created = \AdvancedNoticesManager\create_automated_snapshot();
        if ($created) {
            echo '<div class="notice notice-success is-dismissible"><p>New snapshot created.</p></div>';
        } else {
            echo '<div class="notice notice-info is-dismissible"><p>No changes detected; snapshot not required.</p></div>';
        }
    }

    $archives = $wpdb->get_results("SELECT * FROM " . ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE . " ORDER BY archive_date DESC");
    ?>
    <div class="wrap">
        <h1>Notices Archive</h1>

        <?php if (isset($_GET['import_success'])): ?>
            <div class="notice notice-success is-dismissible"><p>Import Successful.</p></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="notice notice-warning is-dismissible"><p>Archive entry deleted.</p></div>
        <?php endif; ?>

        <form method="post" style="margin: 20px 0;">
            <input type="submit" name="create_snapshot" class="button button-primary" value="Run Snapshot Check Now">
            <p class="description">Snapshots are automatically created daily at 11:00 PM only if the content has changed.</p>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Date</th><th>Posts</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($archives as $row): ?>
                <?php 
                    // Prepare date parts for query arguments
                    $timestamp = strtotime($row->archive_date);
                    $args = [
                        'notice_archive_year'  => date('Y', $timestamp),
                        'notice_archive_month' => date('m', $timestamp),
                        'notice_archive_day'   => date('d', $timestamp)
                    ];
                ?>
                <tr>
                    <td><strong><?php echo date('jS M Y', $timestamp); ?></strong></td>
                    <td><?php echo count(explode(',', $row->post_ids)); ?></td>
                    <td>
                        <a href="<?php echo add_query_arg(array_merge(['notice_archive_html' => '1'], $args), home_url('/')); ?>" target="_blank">View HTML</a> |
                        <a href="<?php echo add_query_arg(array_merge(['notice_archive_docx' => '1'], $args), home_url('/')); ?>">DOCX</a> |
                        <a href="<?php echo add_query_arg(array_merge(['notice_archive_pdf' => '1'], $args), home_url('/')); ?>">PDF</a> |
                        <a href="<?php echo wp_nonce_url(admin_url('edit.php?page=notices-archive&action=delete&id=' . $row->id), 'delete_notice_' . $row->id); ?>" 
                           style="color: #b32d2e;" 
                           onclick="return confirm('Are you sure you want to delete this archive?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="card" style="margin-top: 30px;">
            <h3>Tools</h3>
            <a href="<?php echo admin_url('edit.php?page=notices-archive&export_notices=1'); ?>" class="button">Export JSON Dump</a>
            <hr>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="import_file" accept=".json">
                <input type="submit" name="process_import" class="button" value="Import JSON Dump">
            </form>
        </div>
    </div>
    <?php
}