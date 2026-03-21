<?php

namespace AdvancedNoticesManager;

add_action('admin_menu', function() {
    add_submenu_page('edit.php', 'Notices Archive', 'Notices Archive', 'manage_options', 'notices-archive', __NAMESPACE__ .'\render_notices_archive_page');
});

function render_notices_archive_page() {
    global $wpdb;    
    
    // 1. Handle Export
    if (isset($_GET['export_notices'])) {
        $data = $wpdb->get_results("SELECT * FROM " . ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE);
        $export = ['version' => '1.1', 'plugin' => 'advanced-notices-manager', 'data' => $data];
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="notices-archive-'.date('Y-m-d').'.json"');
        echo json_encode($export);
        exit;
    }

    // 2. Handle Import
    if (isset($_POST['process_import']) && !empty($_FILES['import_file']['tmp_name'])) {
        $json = file_get_contents($_FILES['import_file']['tmp_name']);
        $import = json_decode($json, true);
        if ($import && isset($import['data'])) {
            foreach ($import['data'] as $row) {
                $wpdb->replace(ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE, [
                    'archive_date' => $row['archive_date'], 
                    'post_ids' => $row['post_ids'], 
                    'created_at' => $row['created_at']
                ]);
            }
            echo '<div class="notice notice-success"><p>Import Successful.</p></div>';
        }
    }

    // 3. Handle Snapshot Creation
    if (isset($_POST['create_snapshot'])) {
        $date = $_POST['archive_date'];
        $base_cats = ['introduction', 'news', 'events', 'jobs', 'prayer', 'volunteering'];
        $suffixes = ['-full', '-short', '-list', '-website'];
        $all_ids = [];

        foreach ($base_cats as $cat) {
            $tags = array_map(fn($s) => $cat . $s, $suffixes);
            $p_ids = get_posts([
                'category_name' => $cat,
                'tax_query' => [['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $tags]],
                'fields' => 'ids', 'posts_per_page' => -1
            ]);
            $all_ids = array_merge($all_ids, $p_ids);
        }
        $wpdb->replace(ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE, ['archive_date' => $date, 'post_ids' => implode(',', array_unique($all_ids))]);
    }
    

    $archives = $wpdb->get_results("SELECT * FROM " . ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE . " ORDER BY archive_date DESC");
    ?>
        <h1>Notices Archive</h1>
        <form method="post" style="margin: 20px 0;">
            <input type="date" name="archive_date" value="<?php echo date('Y-m-d'); ?>">
            <input type="submit" name="create_snapshot" class="button button-primary" value="Create Snapshot">
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Date</th><th>Posts</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($archives as $row): ?>
                <tr>
                    <td><strong><?php echo date('jS M Y', strtotime($row->archive_date)); ?></strong></td>
                    <td><?php echo count(explode(',', $row->post_ids)); ?></td>
                    <td>
                        <a href="<?php echo add_query_arg('notice_archive_html', $row->id, home_url('/')); ?>" target="_blank">View HTML</a> |
                        <a href="<?php echo add_query_arg('notice_archive_docx', $row->id, home_url('/')); ?>">DOCX</a> |
                        <a href="<?php echo add_query_arg('notice_archive_pdf', $row->id, home_url('/')); ?>">PDF</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="card" style="margin-top: 30px;">
            <h3>Tools</h3>
            <a href="?page=notices-archive&export_notices=1" class="button">Export JSON</a>
            <hr>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="import_file" accept=".json">
                <input type="submit" name="process_import" class="button" value="Import JSON">
            </form>
        </div>
    </div>
    <?php
}