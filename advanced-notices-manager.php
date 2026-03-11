<?php
/**
 * Plugin Name: Advanced Notices Manager
 * Description: Notice manager designed for Horsham Churches Together
 * Version: 1.0.8
 * Author: Pete Dibdin
 * License: MIT
 * Plugin URI: https://github.com/pjd199/notices-manager
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'anm_register_menu');
function anm_register_menu() {
    add_submenu_page('edit.php', 'Notices Manager', 'Notices Manager', 'manage_options', 'notices-manager', 'anm_render_page');
}

function anm_render_page() {
    $categories = ['introduction', 'news', 'events', 'prayer', 'jobs', 'volunteering'];
    $suffixes = ['full', 'short', 'list', 'parked'];
    $today = strtotime('today');
    
    echo '<style>
        /* Hide LiteSpeed Success/Purge notices only on this page */
        .anm-wrap ~ .litespeed_icon.notice-success,
        .litespeed_icon.notice-success { 
            display: none !important; 
        }
    </style>';
    echo '<div class="anm-wrap"><h1 class="wp-heading-inline">Notices Manager</h1>';
    echo '<a href="' . home_url('/notices') . '" class="page-title-action" target="_blank">View Notices Page</a>';
    echo '<hr class="wp-header-end">';

    foreach ($categories as $cat_slug) {
        $cat_obj = get_category_by_slug($cat_slug);
        if (!$cat_obj) continue;

        $cat_name = esc_html($cat_obj->name);
        $cat_suffixes = ($cat_slug === 'introduction') ? ['full', 'parked'] : $suffixes;
        $cat_specific_tags = [];
        foreach ($cat_suffixes as $sfx) { $cat_specific_tags[] = $cat_slug . '-' . $sfx; }

        $args = [
            'post_type'      => 'post',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'pending', 'draft', 'future', 'private'],
            'category_name'  => $cat_slug,
            'tax_query'      => [['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $cat_specific_tags]],
        ];

        if ($cat_slug === 'events') {
            $args['meta_key'] = 'event_start';
            $args['orderby']  = 'meta_value';
            $args['order']    = 'ASC';
        }

        $query = new WP_Query($args);
        echo "<h2>$cat_name <span class='count' style='font-weight:normal; color:#666;'>({$query->found_posts})</span></h2>";
        
        $default_tag = ($cat_slug === 'introduction') ? 'introduction-full' : "{$cat_slug}-short";
        $new_post_url = admin_url("post-new.php?pre_cat={$cat_obj->term_id}&pre_tag={$default_tag}");
        echo "<a href='$new_post_url' class='button action' style='margin-bottom:15px;'>+ Add New $cat_name Post</a>";

        ?>
        <table class="wp-list-table widefat fixed striped posts" style="margin-bottom: 40px;">
            <thead>
                <tr>
                    <th style="width: 25%;">Title</th>
                    <?php if($cat_slug === 'events'): ?><th style="width: 120px;">Event Date</th><?php endif; ?>
                    <?php if($cat_slug !== 'events'): ?><th style="width: 120px;">Expiry Date</th><?php endif; ?>
                    <th style="width: 120px;">Published</th>
                    <?php if($cat_slug !== 'introduction'): ?>
                        <th style="width: 70px; text-align:center;">Image</th>
                        <th style="width: 70px; text-align:center;">Excerpt</th>
                    <?php endif; ?>
                    <th style="width: 180px;">Display Style</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post(); 
                    $post_id = get_the_ID();
                    $status = get_post_status($post_id);
                    $pub_date = get_the_date('U');
                    $e_date_raw = get_post_meta($post_id, 'event_start', true);
                    $e_date_ts = $e_date_raw ? strtotime($e_date_raw) : false;
                    $expires_raw = get_post_meta($post_id, 'expires', true);
                    $expires_ts  = $expires_raw ? strtotime($expires_raw) : false;
                    $current_tags = wp_get_post_tags($post_id, ['fields' => 'slugs']);
                    $active_tag = reset(array_intersect($current_tags, $cat_specific_tags));
                    
                    $is_stale = ($cat_slug !== 'events' && (($today - $pub_date) > (18 * DAY_IN_SECONDS))) || 
                        ($cat_slug === 'events' && $e_date_ts && $e_date_ts < $today) ||
                        ($expires_ts && $expires_ts < $today);

                    $is_parked = (strpos($active_tag, '-parked') !== false);
                    $row_style = $is_parked ? 'opacity: 0.7; background-color: #f6f7f7;' : ($is_stale ? 'background-color: #f59b78;' : '');
                ?>
                <tr style="<?php echo $row_style; ?>" class="anm-row" id="post-row-<?php echo $post_id; ?>">
                    <td class="column-title has-row-actions">
                        <strong><a class="row-title" href="<?php echo get_edit_post_link(); ?>"><?php the_title(); ?></a></strong>
                        <?php 
                            if ($status !== 'publish') {
                                $status_labels = ['draft' => 'Draft', 'future' => 'Scheduled', 'pending' => 'Pending', 'private' => 'Private'];
                                $label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);
                                echo " — <span style='color:#2271b1; font-weight:600; font-size:11px;'>$label</span>";
                            }
                        ?>
                        <div class="row-actions">
                            <span class="edit"><a href="<?php echo get_edit_post_link(); ?>">Edit</a> | </span>
                            <span class="move"><a href="#" class="anm-panel-trigger" data-target="move" style="color:#2271b1;">Move</a> | </span>
                            <span class="clone"><a href="<?php echo wp_nonce_url(admin_url("admin-ajax.php?action=anm_clone_post&post_id=$post_id"), 'anm_clone_nonce'); ?>" onclick="return confirm('Clone to new draft?')">Clone</a> | </span>
                            <span class="view"><a href="<?php the_permalink(); ?>" target="_blank">View</a> | </span>
                            <span class="archive"><a href="#" class="anm-do-remove-notice" data-postid="<?php echo $post_id; ?>" data-catslug="<?php echo $cat_slug; ?>" style="color:#2271b1;">Archive</a> | </span>
                            <span class="trash"><a href="#" class="anm-do-trash-post" data-postid="<?php echo $post_id; ?>" style="color:#d63638;">Bin</a></span>
                        </div>

                        <div class="anm-panel anm-move-panel" style="display:none; margin-top:5px; padding:10px; background:#f0f0f1; border-radius:3px; border:1px solid #ccd0d4; position:relative;">
                            <a href="#" class="anm-close-panel" style="position:absolute; right:8px; top:5px; text-decoration:none; color:#666; font-weight:bold;">[X]</a>
                            <small><strong>Move to Category:</strong></small><br>
                            <?php foreach($categories as $dest): if($dest === $cat_slug) continue; ?>
                                <button class="button button-small anm-do-move" data-postid="<?php echo $post_id; ?>" data-dest="<?php echo $dest; ?>" style="margin-top:4px;"><?php echo ucfirst($dest); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <?php if($cat_slug === 'events'): ?><td><strong><?php echo $e_date_raw ? date('d/m/Y', $e_date_ts) : '—'; ?></strong></td><?php endif; ?>
                    <?php if($cat_slug !== 'events'): ?><td><strong><?php echo $expires_raw ? date('d/m/Y', $expires_ts) : '—'; ?></strong></td><?php endif; ?>
                    <td><?php echo get_the_date('d/m/Y'); ?></td>
                    <?php if($cat_slug !== 'introduction'): ?>
                        <td style="text-align:center;">
                            <?php 
                                if (has_post_thumbnail()) {
                                    $img_data = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'full');
                                    if ($img_data) {
                                        $w = $img_data[1];
                                        $h = $img_data[2];
                                        $ratio = ($h > 0) ? ($w / $h) : 0;
                                        // Check for 16:9 (1.777) with small tolerance
                                        if (abs($ratio - (16/9)) < 0.02) {
                                            echo '<span style="color:green;">✔</span>';
                                        } else {
                                            echo '<span style="color:orange;" title="Wrong Ratio: ' . round($ratio, 2) . ':1">⚠️</span>';
                                        }
                                    }
                                } else {
                                    echo '<span style="color:red;">✘</span>';
                                }
                            ?>
                        </td>
                        <td style="text-align:center;"><?php echo has_excerpt() ? '✔' : '<span style="color:red;">✘</span>'; ?></td>
                    <?php endif; ?>
                    <td>
                        <select class="notice-tag-changer" data-postid="<?php the_ID(); ?>" data-catslug="<?php echo $cat_slug; ?>">
                            <option value="none">— Remove Tag —</option>
                            <?php foreach ($cat_suffixes as $sfx) : $tag_value = $cat_slug . '-' . $sfx; ?>
                                <option value="<?php echo $tag_value; ?>" <?php selected($active_tag, $tag_value); ?>><?php echo ucfirst($sfx); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="spinner" style="float:none;"></span>
                    </td>
                </tr>
                <?php endwhile; wp_reset_postdata(); else : ?>
                <tr><td colspan="7">No notices found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    echo '</div>';
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Toggle Panels
        $('.anm-panel-trigger').on('click', function(e){ 
            e.preventDefault(); 
            var type = $(this).data('target');
            $(this).closest('td').find('.anm-panel').hide(); // Hide others
            $(this).closest('td').find('.anm-' + type + '-panel').slideDown(100); 
        });

        // Close Panels
        $('.anm-close-panel').on('click', function(e){ e.preventDefault(); $(this).closest('.anm-panel').slideUp(100); });
        
        // Execute Move
        $('.anm-do-move').on('click', function(){
            var btn = $(this); btn.prop('disabled', true).text('...');
            $.post(ajaxurl, { action: 'anm_move_post', post_id: btn.data('postid'), dest_cat: btn.data('dest'), nonce: '<?php echo wp_create_nonce("anm_nonce"); ?>' }, function(res) { if(res.success) { location.reload(); } });
        });

        // Execute Archive (Remove Tag)
        $('.anm-do-remove-notice').on('click', function(e){
            e.preventDefault();
            var btn = $(this); 
            var pid = btn.data('postid'); 
            btn.css('opacity', '0.5').text('Archiving...');
            
            $.post(ajaxurl, { 
                action: 'anm_update_tag', 
                post_id: pid, 
                tag: 'none', 
                cat_slug: btn.data('catslug'), 
                nonce: '<?php echo wp_create_nonce("anm_nonce"); ?>' 
            }, function(res) { 
                if(res.success) { $('#post-row-'+pid).fadeOut(); } 
            });
        });

        // Execute Trash Post (The double-check)
        $('.anm-do-trash-post').on('click', function(e){
            e.preventDefault();
            if(!confirm("Are you sure you want to move this post to the TRASH? This removes it from the website entirely.")) return;
            
            var btn = $(this); 
            var pid = btn.data('postid'); 
            btn.css('opacity', '0.5').text('Trashing...');
            
            $.post(ajaxurl, { 
                action: 'anm_trash_post', 
                post_id: pid, 
                nonce: '<?php echo wp_create_nonce("anm_nonce"); ?>' 
            }, function(res) { 
                if(res.success) { $('#post-row-'+pid).fadeOut(); } 
            });
        });

        // Tag Switcher
        $('.notice-tag-changer').on('change', function() {
            var $select = $(this), $spinner = $select.next('.spinner');
            $spinner.addClass('is-active');
            $.post(ajaxurl, { action: 'anm_update_tag', post_id: $select.data('postid'), tag: $select.val(), cat_slug: $select.data('catslug'), nonce: '<?php echo wp_create_nonce("anm_nonce"); ?>' }, function(res) { if(res.success) { location.reload(); } });
        });
    });
    </script>
    <?php
}

/**
 * Custom helper to purge LiteSpeed cache for specific notice-related pages.
 */
function anm_purge_notice_caches($post_id) {
    clean_post_cache($post_id);

    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('posts');
    }
    
    if (!defined('LSCWP_V')) {
        return; // LiteSpeed isn't active, do nothing.
    }

    // 1. Purge the individual post itself
    do_action('litespeed_purge_post', $post_id);

    // 2. Define the specific notice-related URLs to clear
    $pages_to_clear = [
        '/',
        '/notices/',
        '/news/',
        '/events/'
    ];

    foreach ($pages_to_clear as $path) {
        $url = home_url($path);
        do_action('litespeed_purge_url', $url);
    }
}
add_action('wp_insert_post', function($post_id, $post, $update) {
    if ($update) return; 
    if (isset($_REQUEST['pre_cat'])) wp_set_post_categories($post_id, [intval($_REQUEST['pre_cat'])]);
    if (isset($_REQUEST['pre_tag'])) wp_set_post_tags($post_id, sanitize_text_field($_REQUEST['pre_tag']), true);
}, 10, 3);

// AJAX handlers (unchanged from 3.4 logic)
add_action('wp_ajax_anm_move_post', function() {
    check_ajax_referer('anm_nonce', 'nonce');
    $post_id = intval($_POST['post_id']);
    $dest_slug = sanitize_text_field($_POST['dest_cat']);
    if (!current_user_can('edit_post', $post_id)) wp_send_json_error();
    $cat = get_category_by_slug($dest_slug);
    if ($cat) wp_set_post_categories($post_id, [$cat->term_id]);
    $all_slugs = ['introduction', 'news', 'events', 'prayer', 'jobs', 'volunteering'];
    $current_tags = wp_get_post_tags($post_id);
    foreach($current_tags as $tag) {
        foreach($all_slugs as $slug) {
            if(strpos($tag->slug, $slug . '-') === 0) {
                $suffix = str_replace($slug . '-', '', $tag->slug);
                wp_remove_object_terms($post_id, $tag->term_id, 'post_tag');
                wp_set_post_terms($post_id, $dest_slug . '-' . $suffix, 'post_tag', true);
            }
        }
    }
    anm_purge_notice_caches($post_id);
    wp_send_json_success();
});

add_action('wp_ajax_anm_update_tag', function() {
    check_ajax_referer('anm_nonce', 'nonce');
    $post_id = intval($_POST['post_id']);
    $cat_slug = sanitize_text_field($_POST['cat_slug']);
    $new_tag = sanitize_text_field($_POST['tag']);
    $all_suffixes = ['full', 'short', 'list', 'parked'];
    $tags_to_strip = []; foreach($all_suffixes as $s) { $tags_to_strip[] = $cat_slug . '-' . $s; }
    if (current_user_can('edit_post', $post_id)) {
        wp_remove_object_terms($post_id, $tags_to_strip, 'post_tag');
        if ($new_tag !== 'none') wp_set_post_terms($post_id, $new_tag, 'post_tag', true);
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

/**
 * Scheduled Task: Remove tags from expired events daily at 12:01
 */

// Register a custom daily schedule at 12:01
add_filter('cron_schedules', function($schedules) {
    return $schedules; // We'll use WP's built-in 'daily', offset via wp_schedule_event
});

// Hook to run on activation
register_activation_hook(__FILE__, 'anm_schedule_expired_cleanup');
function anm_schedule_expired_cleanup() {
    if (!wp_next_scheduled('anm_expired_cleanup')) {
        // Calculate next 00:01 in site's local time
        $timezone = wp_timezone();
        $next_run = new DateTime('today 00:01', $timezone);
        if ($next_run->getTimestamp() <= time()) {
            $next_run->modify('+1 day');
        }
        wp_schedule_event($next_run->getTimestamp(), 'daily', 'anm_expired_events_cleanup');
    }
}

// Hook to clear on deactivation
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('anm_expired_cleanup');
});

// The actual cleanup task
add_action('anm_expired_cleanup', 'anm_do_expired_cleanup');
function anm_do_expired_cleanup() {
    $today = date('Y-m-d'); // Current date in ISO format for reliable comparison
    $controlled_tags = [
        'introduction-full', 'introduction-parked',
        'news-full', 'news-short', 'news-list', 'news-parked',
        'events-full', 'events-short', 'events-list', 'events-parked',
        'prayer-full', 'prayer-short', 'prayer-list', 'prayer-parked',
        'jobs-full', 'jobs-short', 'jobs-list', 'jobs-parked',
        'volunteering-full', 'volunteering-short', 'volunteering-list', 'volunteering-parked'
    ];

    $args = [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'pending', 'draft', 'future', 'private'],
        // We remove 'category_name' => 'events' so it checks ALL categories for the 'expires' field
        'tax_query'      => [[
            'taxonomy' => 'post_tag',
            'field'    => 'slug',
            'terms'    => $controlled_tags,
        ]],
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'event_start',
                'value'   => $today,
                'compare' => '<',
                'type'    => 'DATE',
            ],
            [
                'key'     => 'expires',
                'value'   => $today,
                'compare' => '<',
                'type'    => 'DATE',
            ],
        ],
    ];

    $query = new WP_Query($args);

    if (!$query->have_posts()) return;

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();

        // Strip the tags to "Archive" the post from the manager
        wp_remove_object_terms($post_id, $controlled_tags, 'post_tag');

        // Purge caches so the site updates immediately
        anm_purge_notice_caches($post_id);
    }

    wp_reset_postdata();
}

