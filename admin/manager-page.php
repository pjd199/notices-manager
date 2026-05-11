<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

/**
 * HELPER: Renders the internal content of a row.
 * Isolated so it can be called by the initial loop AND the AJAX refresh.
 */
function anm_render_row_content($post_id, $cat_slug, $categories, $is_events = false) {
    $settings = anm_get_settings();
    $post = get_post($post_id);
    if (!$post) return '';

    $status = $post->post_status;
    $pub_date = mysql2date('U', $post->post_date);
    $modified = $post->post_modified; // Used to detect if post data changed
    $today = strtotime('today');
    
    $date_raw = get_post_meta($post_id, ($cat_slug === 'events') ? "event_start_time" : "expiry_date", true);
    $date_ts = $date_raw ? strtotime($date_raw) : false;
    
    $current_tags = wp_get_post_tags($post_id, ['fields' => 'slugs']);
    $cat_specific_tags = [];
    foreach ($settings['suffixes'] as $sfx) { $cat_specific_tags[] = $cat_slug . $sfx; }
    $intersect = array_intersect($current_tags, $cat_specific_tags);
    $active_tag = reset($intersect);

    // Image Ratio Check
    if (has_post_thumbnail($post_id)) {
        $img_data = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'full');
        $ratio = ($img_data && $img_data[2] > 0) ? ($img_data[1] / $img_data[2]) : 0;
        $img_flag = (abs($ratio - ($settings['img_w']/$settings['img_h'])) < 0.02) ? '<span style="color:green;">✔</span>' : '<span style="color:orange;" title="Ratio: '.round($ratio, 2).':1">⚠</span>';
    } else {
        $img_flag = '<span style="color:red;">✘</span>';
    }

    // Excerpt Word Count Check
    if (has_excerpt($post_id)) {
        $word_count = str_word_count(strip_tags(get_the_excerpt($post_id)), 0, '0123456789');
        
        if ($word_count >= $settings['excerpt_min'] && $word_count <= $settings['excerpt_max']) {
            $status_color = 'green';
            $icon = '✔';
            $message = "Ideal length: {$word_count} words.";
        } else {
            $status_color = 'orange';
            $icon = '⚠';
            $diff = ($word_count < $settings['excerpt_min']) ? 'Too short' : 'Too long';
            $message = "{$diff} ({$word_count} words). Aim for {$settings['excerpt_min']}-{$settings['excerpt_max']} words.";
        }
        
        $excerpt_flag = sprintf(
            '<span style="color:%s; cursor:help;" title="%s">%s</span>',
            $status_color,
            esc_attr($message),
            $icon
        );

    } else {
        $excerpt_flag = '<span style="color:red; cursor:help;" title="No excerpt found.">✘</span>';
    }
    
    $status_label = ($status !== 'publish') ? ' — ' . ucfirst($status) : '';
    $is_new = floor(($today - $pub_date) / DAY_IN_SECONDS) < $settings['new_days'];
    $is_stale = ($cat_slug !== 'events' && (($today - $pub_date) > ($settings['stale_days'] * DAY_IN_SECONDS))) || ($date_ts && $date_ts < $today);

    $row_bg = '';
    if ($is_new) $row_bg = '#c5ff99';
    elseif ($is_stale) $row_bg = '#faa0a0';

    $tag_labels = $settings['tags'];

    ob_start(); ?>
    <?php if (!$is_events) : ?>
        <td class="anm-drag-handle" title="Drag to reorder" style="width:5px; text-align:center; cursor:grab; color:#999; font-size:18px; user-select:none;">⠿</td>
    <?php endif; ?>
    <td class="column-title has-row-actions" data-modified="<?php echo $modified; ?>" data-bg="<?php echo $row_bg; ?>">
        <strong style="display: inline-block;">
            <a class="row-title" href="<?=get_edit_post_link($post_id)?>"><?=esc_html($post->post_title)?></a>
            <span style="color:black;"><?=$status_label?></span>
        </strong>
        <div class="row-actions">
            <span class="edit"><a href="<?=get_edit_post_link($post_id)?>">Edit</a> | </span>
            <span class="move"><a href="#" class="anm-panel-trigger" data-target="move" style="color:#2271b1;">Move</a> | </span>
            <span class="clone"><a href="<?=wp_nonce_url(admin_url("admin-ajax.php?action=anm_clone_post&post_id=$post_id"), 'anm_clone_nonce')?>" onclick="return confirm('Clone to new draft?')">Clone</a> | </span>
            <span class="view"><a href="<?=get_permalink($post_id)?>" target="_blank">View</a> | </span>
            <?php if ( $status === 'draft' ) : ?>
                <span class="publish"><a href="#" class="anm-do-publish-post" data-postid="<?=$post_id?>" style="color:#00a32a;">Publish</a> | </span>
            <?php endif; ?>
            <span class="archive"><a href="#" class="anm-do-remove-notice" data-postid="<?=$post_id?>" data-catslug="<?=$cat_slug?>" style="color:#2271b1;">Archive</a> | </span>
            <span class="trash"><a href="#" class="anm-do-trash-post" data-postid="<?=$post_id?>" style="color:#d63638;">Bin</a></span>
        </div>
        <div class="anm-panel anm-move-panel" style="display:none; margin-top:5px; padding:10px; background:#f0f0f1; border-radius:3px; border:1px solid #ccd0d4; position:relative;">
            <a href="#" class="anm-close-panel" style="position:absolute; right:8px; top:5px; text-decoration:none; color:#666; font-weight:bold;">[X]</a>
            <small><strong>Move to Category:</strong></small><br>
            <?php foreach($categories as $dest): if($dest === $cat_slug) continue; ?>
                <button class="button button-small anm-do-move" data-postid="<?=$post_id?>" data-dest="<?=$dest?>" style="margin-top:4px;"><?=ucfirst($dest)?></button>
            <?php endforeach; ?>
        </div>
    </td>
    <td><strong><?= $date_ts ? date('d/m/Y', $date_ts) : '—'; ?></strong></td>
    <td><?= get_the_date('d/m/Y', $post_id) ?></td>
    <td style="text-align:center;"><?= $img_flag ?></td>
    <td style="text-align:center;"><?= $excerpt_flag ?></td>
    <td>
        <select class="notice-tag-changer" data-postid="<?= $post_id ?>" data-catslug="<?= $cat_slug ?>">
            <?php foreach (array_keys($settings['tags']) as $sfx) : $tag_value = $cat_slug . '-' . $sfx; ?>
                <option value="<?= $tag_value ?>" <?= selected($active_tag, $tag_value) ?>>
                    <?= isset($settings['tags'][$sfx]) ? $settings['tags'][$sfx] : ucfirst($sfx) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="spinner" style="float:none;"></span>
    </td>
    <?php
    return ob_get_clean();
}

function anm_render_page() {
    $settings = anm_get_settings();
    ?>
    <style>
        .anm-wrap ~ .litespeed_icon.notice-success, .litespeed_icon.notice-success { display: none !important; }
        .anm-row { transition: background-color 0.4s ease; }
        .anm-drag-handle { cursor: grab; }
        .anm-drag-handle:active { cursor: grabbing; }
        .anm-row.ui-sortable-helper { box-shadow: 0 4px 12px rgba(0,0,0,0.15); opacity: 0.95; }
        .anm-row.ui-sortable-placeholder { background: #e5f3ff !important; border: 2px dashed #72aee6 !important; visibility: visible !important; }
        .anm-drag-handle.saved { color: #00a32a !important; transition: color 0.2s ease; }
    </style>
    <div class="anm-wrap">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 5px;">
            <h1 class="wp-heading-inline">Notices Manager</h1>
            <a href="<?=home_url('/notices')?>" class="button" target="_blank">View Notices</a>
            <a href="<?=home_url('/news')?>" class="button" target="_blank">View News</a>
            <a href="<?=home_url('/events')?>" class="button" target="_blank">View Events</a>
            <a href="<?=home_url('/?plain_text=html&toc=false')?>" class="button" target="_blank">View Plain Text</a>
            <a href="<?=home_url('/?plain_text=pdf')?>" class="button" target="_blank">Download PDF</a>
            <a href="<?=home_url('/?plain_text=docx')?>" class="button" target="_blank">Download DOCX</a>
        </div>
        <hr class="wp-header-end">
    
    <?php
    foreach ($settings['categories'] as $cat_slug) {
        $cat_obj = get_category_by_slug($cat_slug);
        if (!$cat_obj) continue;

        $is_events = ($cat_slug === 'events');

        $cat_specific_tags = [];
        foreach ($settings['suffixes'] as $sfx) { $cat_specific_tags[] = $cat_slug . $sfx; }

        if ( $is_events ) {
            // Posts with event_start_time - ordered by date ASC
            $args = [
                'post_type'      => 'post',
                'posts_per_page' => -1,
                'post_status'    => [ 'publish', 'pending', 'draft', 'future', 'private' ],
                'category_name'  => $cat_slug,
                'tax_query'      => [ [ 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $cat_specific_tags ] ],
                'orderby'        => 'meta_value',
                'meta_key'       => 'event_start_time',
                'meta_type'      => 'DATETIME',
                'order'          => 'ASC',
                'meta_query'     => [ [
                    'key'     => 'event_start_time',
                    'compare' => 'EXISTS',
                ] ],
            ];
            $query = new \WP_Query( $args );
            $dated_ids = wp_list_pluck( $query->posts, 'ID' );

            // Posts missing event_start_time - shown at top as they need attention
            $undated_args = [
                'post_type'      => 'post',
                'posts_per_page' => -1,
                'post_status'    => [ 'publish', 'pending', 'draft', 'future', 'private' ],
                'category_name'  => $cat_slug,
                'tax_query'      => [ [ 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $cat_specific_tags ] ],
                'meta_query'     => [ [
                    'key'     => 'event_start_time',
                    'compare' => 'NOT EXISTS',
                ] ],
                'orderby'        => 'date',
                'order'          => 'DESC',
            ];
            $undated_query = new \WP_Query( $undated_args );
        } else {
            $args = [
                'post_type'      => 'post',
                'posts_per_page' => -1,
                'post_status'    => [ 'publish', 'pending', 'draft', 'future', 'private' ],
                'category_name'  => $cat_slug,
                'tax_query'      => [ [ 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $cat_specific_tags ] ],
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
            ];
            $query = new \WP_Query( $args );
            $undated_query = null;
        }
        $total_count = $query->found_posts + ( $undated_query ? $undated_query->found_posts : 0 );
        $default_tag = $cat_slug .'-' . $settings['default_tag'];
        $new_post_url = admin_url("post-new.php?pre_cat={$cat_obj->term_id}&pre_tag={$default_tag}");
        ?>
        
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 5px;">
            <h2><?=esc_html($cat_obj->name)?><span class="count" style="font-weight:normal; color:#666;">(<?=$total_count?>)</span></h2>
            <a href="<?=$new_post_url?>" class="button action">Add New Post</a>

            <?php if ( !$is_events && $total_count > 1 ) : ?>
                <button type="button" 
                    class="button button-secondary anm-reset-cat" 
                    data-cat="<?php echo esc_html($cat_slug); ?>">
                    Reset Order
                </button>
                <span class="spinner"></span>
            <?php endif; ?>
        </div>
        <table class="wp-list-table widefat fixed striped posts" style="margin-bottom: 40px;">
            <thead>
                <tr>
                    <?php if ( ! $is_events ) : ?>
                        <th style="width:5px;"></th>
                    <?php endif; ?>
                    <th style="width: 40%;">Title</th>
                    <th style="width: 100px;"><?= $is_events ? "Event Date" : "Expiry Date" ?></th>
                    <th style="width: 100px;">Published</th>
                    <th style="width: 60px; text-align:center;">Image</th>
                    <th style="width: 60px; text-align:center;">Excerpt</th>
                    <th style="width: 160px;">Display Style</th>
                </tr>
            </thead>
            <tbody <?= ! $is_events ? 'class="anm-sortable" data-catslug="' . esc_attr($cat_slug) . '"' : '' ?>>
                <?php 
                $has_posts = false;

                // Undated events first - they need attention
                if ( $undated_query && $undated_query->have_posts() ) :
                    while ( $undated_query->have_posts() ) : $undated_query->the_post();
                        $has_posts = true;
                        $pid     = get_the_ID();
                        $content = anm_render_row_content( $pid, $cat_slug, $settings['categories'], $is_events);
                        preg_match( '/data-bg="([^"]*)"/', $content, $m );
                        $bg_style = "background-color:#fff3cd;"; // Yellow to highlight missing date
                        ?>
                        <tr style="<?=$bg_style?>" class="anm-row" id="post-row-<?=$pid?>" data-postid="<?=$pid?>" data-catslug="<?=$cat_slug?>">
                            <?=$content?>
                        </tr>
                    <?php endwhile; wp_reset_postdata();
                endif;

                // Dated events (or all non-event posts)
                if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post();
                    $has_posts = true;
                    $pid     = get_the_ID();
                    $content = anm_render_row_content( $pid, $cat_slug, $settings['categories'], $is_events);
                    preg_match( '/data-bg="([^"]*)"/', $content, $m );
                    $bg_style = ! empty( $m[1] ) ? "background-color:{$m[1]};" : "";
                    ?>
                    <tr style="<?=$bg_style?>" class="anm-row" id="post-row-<?=$pid?>" data-postid="<?=$pid?>" data-catslug="<?=$cat_slug?>">
                        <?=$content?>
                    </tr>
                <?php endwhile; wp_reset_postdata(); endif;

                if ( ! $has_posts ) : ?>
                    <tr><td colspan="<?= $is_events ? 6 : 7 ?>">No posts found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php } ?>
    </div>

    <script>
    jQuery(document).ready(function($) {

        // ── Drag-and-drop ordering ─────────────────────────────────────────────
        $('.anm-sortable').sortable({
            items:       '> tr.anm-row',
            handle:      '.anm-drag-handle',
            axis:        'y',
            placeholder: 'anm-row ui-sortable-placeholder',
            // Keep the placeholder the same height as the dragged row
            start: function(e, ui) {
                ui.placeholder.height(ui.item.outerHeight());
                // Force the helper to maintain its column widths
                ui.helper.find('td').each(function() {
                    $(this).width($(this).width());
                });
            },
            stop: function(e, ui) {
                var $tbody   = $(this);
                var catSlug  = $tbody.data('catslug');

                // Collect ordered post IDs from this tbody
                var ordered = [];
                $tbody.find('tr.anm-row').each(function() {
                    ordered.push($(this).data('postid'));
                });

                // Save via AJAX
                $.post(ajaxurl, {
                    action:   'anm_save_order',
                    nonce:    '<?php echo wp_create_nonce("anm_nonce"); ?>',
                    post_ids: ordered,
                    cat_slug: catSlug
                }, function(res) {
                    if (res.success) {
                        // Flash all drag handles in this tbody green briefly
                        var $handles = $tbody.find('.anm-drag-handle');
                        $handles.addClass('saved');
                        setTimeout(function() { $handles.removeClass('saved'); }, 1200);
                    } else {
                        alert('Order save failed. Please try again.');
                    }
                });
            }
        });

        jQuery(document).ready(function($) {
            $('.anm-reset-cat').on('click', function() {
                const $button = $(this);
                const catSlug = $button.data('cat');
                const $spinner = $button.next('.spinner');

                if (!confirm(`Reset order for ${catSlug} by date?`)) return;

                $button.prop('disabled', true);
                $spinner.addClass('is-active');

                $.post(ajaxurl, {
                    action: 'anm_reset_category_order',
                    category: catSlug
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });
        });

        // ── Sync check (tab focus) ─────────────────────────────────────────────
        function checkForUpdates() {
            var postData = [];
            $('.anm-row').each(function() {
                var $row = $(this);
                postData.push({
                    id:       $row.data('postid'),
                    cat:      $row.data('catslug'),
                    modified: $row.find('.column-title').data('modified')
                });
            });

            $.post(ajaxurl, {
                action: 'anm_sync_check',
                nonce:  '<?php echo wp_create_nonce("anm_nonce"); ?>',
                posts:  postData
            }, function(res) {
                if (res.success && res.data.updates) {
                    res.data.updates.forEach(function(u) {
                        var $row = $('#post-row-' + u.id);
                        $row.html(u.html);
                        var newBg = $row.find('.column-title').data('bg');
                        $row.css('background-color', newBg ? newBg : '');
                    });
                }
            });
        }

        $(window).on('focus', function() {
            checkForUpdates();
        });

        // ── Delegated row actions ──────────────────────────────────────────────
        $(document).on('click', '.anm-panel-trigger', function(e){ 
            e.preventDefault(); 
            $(this).closest('td').find('.anm-panel').slideDown(100); 
        });

        $(document).on('click', '.anm-close-panel', function(e){ 
            e.preventDefault(); 
            $(this).closest('.anm-panel').slideUp(100); 
        });
        
        $(document).on('click', '.anm-do-move', function(){
            var btn = $(this); btn.prop('disabled', true).text('...');
            $.post(ajaxurl, { action: 'anm_move_post', post_id: btn.data('postid'), dest_cat: btn.data('dest'), nonce: '<?=wp_create_nonce("anm_nonce")?>' }, function(res) { if(res.success) { location.reload(); } });
        });

        $(document).on('click', '.anm-do-remove-notice', function(e){
            e.preventDefault();
            var btn = $(this), pid = btn.data('postid'); 
            btn.css('opacity', '0.5');
            $.post(ajaxurl, { action: 'anm_update_tag', post_id: pid, tag: 'none', cat_slug: btn.data('catslug'), nonce: '<?=wp_create_nonce("anm_nonce")?>' }, function(res) { if(res.success) { $('#post-row-'+pid).fadeOut(); } });
        });

        $(document).on('click', '.anm-do-trash-post', function(e){
            e.preventDefault();
            if(!confirm("Move to TRASH?")) return;
            var btn = $(this), pid = btn.data('postid');
            $.post(ajaxurl, { action: 'anm_trash_post', post_id: pid, nonce: '<?=wp_create_nonce("anm_nonce")?>' }, function(res) { if(res.success) { $('#post-row-'+pid).fadeOut(); } });
        });

        $(document).on('change', '.notice-tag-changer', function() {
            var $select = $(this), $spinner = $select.next('.spinner');
            $spinner.addClass('is-active');
            $.post(ajaxurl, { 
                action:   'anm_update_tag', 
                post_id:  $select.data('postid'), 
                tag:      $select.val(), 
                cat_slug: $select.data('catslug'), 
                nonce:    '<?=wp_create_nonce("anm_nonce")?>' 
            }, function(res) { 
                $spinner.removeClass('is-active');
                if(!res.success) alert('Save failed');
            });
        });

        $(document).on('click', '.anm-do-publish-post', function(e){
            e.preventDefault();
            if(!confirm("Publish this post?")) return;
            var btn = $(this), pid = btn.data('postid');
            btn.css('opacity', '0.5');
            $.post(ajaxurl, { 
                action:  'anm_publish_post', 
                post_id: pid, 
                nonce:   '<?=wp_create_nonce("anm_nonce")?>' 
            }, function(res) { 
                if(res.success) {
                    $.post(ajaxurl, {
                        action: 'anm_sync_check',
                        nonce:  '<?php echo wp_create_nonce("anm_nonce"); ?>',
                        posts:  [{ id: pid, cat: $('#post-row-' + pid).data('catslug'), modified: '' }]
                    }, function(res) {
                        if(res.success && res.data.updates.length) {
                            var u    = res.data.updates[0];
                            var $row = $('#post-row-' + u.id);
                            $row.html(u.html);
                            var newBg = $row.find('.column-title').data('bg');
                            $row.css('background-color', newBg ? newBg : '');
                        }
                    });
                }
            });
        });
    });
    </script>
    <?php
}


// ── AJAX: Save drag-and-drop order ────────────────────────────────────────────
add_action('wp_ajax_anm_save_order', function() {
    check_ajax_referer('anm_nonce', 'nonce');

    $post_ids = isset($_POST['post_ids']) ? array_map('intval', (array) $_POST['post_ids']) : [];

    if (empty($post_ids)) {
        wp_send_json_error('No post IDs provided.');
    }

    foreach ($post_ids as $index => $post_id) {
        error_log($post_id);
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied.');
        }
        wp_update_post([
            'ID'         => $post_id,
            'menu_order' => ($index + 1) * 10,
        ]);
    }

    wp_send_json_success();
});

add_action('wp_ajax_anm_reset_category_order', function() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized');
    }

    $cat_slug = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    $settings = anm_get_settings();

    if (empty($cat_slug) || $cat_slug === 'events') {
        wp_send_json_error('Invalid category or category protected from reset.');
    }

    // 1. Get all posts for this category
    $posts = get_posts([
        'category_name'  => $cat_slug,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    if (empty($posts)) {
        wp_send_json_success("No posts found to reset.");
    }

    // 2. Sort posts: Suffix Priority first, then Date
    usort($posts, function($a, $b) use ($cat_slug, $settings) {
        $suffixes = $settings['suffixes']; // e.g., ['-full', '-short']
        
        // Find which suffix each post has
        $get_suffix_priority = function($post_id) use ($cat_slug, $suffixes) {
            $post_tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'slugs']);
            foreach ($suffixes as $index => $suffix) {
                if (in_array($cat_slug . $suffix, $post_tags)) {
                    return $index; // Return the position in the suffixes array as priority
                }
            }
            return 999; // No matching suffix found
        };

        $priority_a = $get_suffix_priority($a->ID);
        $priority_b = $get_suffix_priority($b->ID);

        // If priorities differ, sort by display style (suffix index)
        if ($priority_a !== $priority_b) {
            return $priority_a - $priority_b;
        }

        // If priorities are the same, sort by date DESC (newest first)
        return strtotime($b->post_date) - strtotime($a->post_date);
    });

    // 3. Save the new order
    foreach ($posts as $index => $post_obj) {
        wp_update_post([
            'ID'         => $post_obj->ID,
            'menu_order' => $index + 1
        ]);
    }

    wp_send_json_success("Order for $cat_slug reset by display style and date.");
});

// ── AJAX: Sync check ──────────────────────────────────────────────────────────
add_action('wp_ajax_anm_sync_check', function() {
    check_ajax_referer('anm_nonce', 'nonce');
    $incoming = $_POST['posts'] ?? [];
    $updates = [];
    $settings = anm_get_settings();

    foreach ($incoming as $item) {
        $pid = intval($item['id']);
        $post = get_post($pid);
        $cat_slug = sanitize_text_field($item['cat']);
        $is_events = ($cat_slug === 'events');
        if ($post && $post->post_modified !== $item['modified']) {
            $updates[] = [
                'id'   => $pid,
                'html' => anm_render_row_content($pid, $cat_slug, $settings['categories'], $is_events)
            ];
        }
    }
    wp_send_json_success(['updates' => $updates]);
});

add_action('wp_insert_post', function($post_id, $post, $update) {
    if ($update) return; 
    if (isset($_REQUEST['pre_cat'])) wp_set_post_categories($post_id, [intval($_REQUEST['pre_cat'])]);
    if (isset($_REQUEST['pre_tag'])) wp_set_post_tags($post_id, sanitize_text_field($_REQUEST['pre_tag']), true);
}, 10, 3);

add_action('wp_ajax_anm_move_post', function() {
    check_ajax_referer('anm_nonce', 'nonce');
    $settings = anm_get_settings();
    $post_id = intval($_POST['post_id']);
    $dest_slug = sanitize_text_field($_POST['dest_cat']);
    if (!current_user_can('edit_post', $post_id)) wp_send_json_error();
    $cat = get_category_by_slug($dest_slug);
    if ($cat) wp_set_post_categories($post_id, [$cat->term_id]);
    $current_tags = wp_get_post_tags($post_id);
    foreach($current_tags as $tag) {
        foreach($settings['categories'] as $slug) {
            if(strpos($tag->slug, $slug . '-') === 0) {
                $suffix = str_replace($slug, '', $tag->slug);
                wp_remove_object_terms($post_id, $tag->term_id, 'post_tag');
                wp_set_post_terms($post_id, $dest_slug . $suffix, 'post_tag', true);
            }
        }
    }
    anm_purge_notice_caches($post_id);
    wp_send_json_success();
});

add_action('wp_ajax_anm_update_tag', function() {
    check_ajax_referer('anm_nonce', 'nonce');
    $settings = anm_get_settings();
    $post_id = intval($_POST['post_id']);
    $cat_slug = sanitize_text_field($_POST['cat_slug']);
    $new_tag = sanitize_text_field($_POST['tag']);
    $tags_to_strip = []; foreach($settings['suffixes'] as $s) { $tags_to_strip[] = $cat_slug . $s; }
    if (current_user_can('edit_post', $post_id)) {
        $current_post_tags = wp_get_object_terms($post_id, 'post_tag', ['fields' => 'slugs']);
        if (!is_wp_error($current_post_tags) && !empty($current_post_tags)) {
            $actual_removals = array_intersect($tags_to_strip, $current_post_tags);
            if (!empty($actual_removals)) {
                wp_remove_object_terms($post_id, $actual_removals, 'post_tag');
            }
        }
        if ($new_tag !== 'none') {
            if (!term_exists($new_tag, 'post_tag')) {
                wp_insert_term($new_tag, 'post_tag');
            }
            wp_set_post_terms($post_id, $new_tag, 'post_tag', true);
        }
        anm_purge_notice_caches($post_id);
        wp_send_json_success();
    }
    wp_send_json_error();
});

add_action('wp_ajax_anm_trash_post', function() {
    check_ajax_referer('anm_nonce', 'nonce');
    $post_id = intval($_POST['post_id']);
    if (current_user_can('delete_post', $post_id)) {
        wp_trash_post($post_id); 
        anm_purge_notice_caches($post_id);
        wp_send_json_success(); 
    }
    wp_send_json_error();
});

add_action('wp_ajax_anm_clone_post', function() {
    check_admin_referer('anm_clone_nonce');
    $post_id = intval($_GET['post_id']);
    $post = get_post($post_id);
    $new_id = wp_insert_post(['post_title'=>$post->post_title.' (Copy)','post_content'=>$post->post_content,'post_excerpt'=>$post->post_excerpt,'post_status'=>'draft','post_type'=>$post->post_type]);
    $taxonomies = get_object_taxonomies($post->post_type);
    foreach ($taxonomies as $tax) { $terms = wp_get_object_terms($post_id, $tax, ['fields' => 'ids']); wp_set_object_terms($new_id, $terms, $tax); }
    $meta = get_post_custom($post_id);
    foreach ($meta as $key => $values) { foreach ($values as $value) add_post_meta($new_id, $key, maybe_unserialize($value)); }
    anm_purge_notice_caches($post_id);
    wp_redirect(admin_url('post.php?action=edit&post=' . $new_id));
    exit;
});

add_action( 'wp_ajax_anm_publish_post', function() {
    check_ajax_referer( 'anm_nonce', 'nonce' );
    $post_id = intval( $_POST['post_id'] );
    if ( current_user_can( 'publish_post', $post_id ) ) {
        wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
        anm_purge_notice_caches( $post_id );
        wp_send_json_success();
    }
    wp_send_json_error();
} );