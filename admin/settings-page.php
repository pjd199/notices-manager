<?php
namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

/**
 * Register settings and menu page
 */
add_action('admin_menu', function() {
    add_options_page(
        'Advanced Notices Manager Settings',
        'Notices Manager',
        'manage_options',
        'anm-settings',
        __NAMESPACE__ . '\anm_render_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('anm_settings_group', 'anm_settings', __NAMESPACE__ . '\anm_sanitize_settings');
});

add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'anm-settings') !== false) {
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style('dashicons');
    }
});


/**
 * Sanitize incoming settings before saving
 */
function anm_sanitize_settings($input) {
    $sanitized = [];
    $sanitized['excerpt_min'] = absint($input['excerpt_min'] ?? 15);
    $sanitized['excerpt_max'] = absint($input['excerpt_max'] ?? 35);
    $sanitized['img_w']       = absint($input['img_w'] ?? 16);
    $sanitized['img_h']       = absint($input['img_h'] ?? 9);
    $sanitized['new_days']    = absint($input['new_days'] ?? 5);
    $sanitized['stale_days']  = absint($input['stale_days'] ?? 18);
    
    // Sanitize Categories (Checkbox array from sortable list)
    $sanitized['categories'] = [];
    if (!empty($input['categories']) && is_array($input['categories'])) {
        foreach ($input['categories'] as $cat) {
            $sanitized['categories'][] = sanitize_text_field($cat);
        }
    }

    // Sanitize Tags (Key => Value arrays)
    $sanitized['tags'] = [];
    if (!empty($input['tag_keys']) && is_array($input['tag_keys'])) {
        foreach ($input['tag_keys'] as $index => $key) {
            $clean_key = sanitize_title($key);
            $clean_val = sanitize_text_field($input['tag_vals'][$index] ?? '');
            if (!empty($clean_key) && !empty($clean_val)) {
                $sanitized['tags'][$clean_key] = $clean_val;
            }
        }
    }

    $sanitized['default_tag'] = $input['default_tag'] ?? "short";

    return $sanitized;
}

function anm_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    
    $settings = anm_get_settings();
    $all_wp_categories = get_categories(['hide_empty' => false]);
    
    // Sort categories logic
    $sorted_cats = [];
    foreach ($settings['categories'] as $saved_slug) {
        foreach ($all_wp_categories as $key => $wp_cat) {
            if ($wp_cat->slug === $saved_slug) {
                $sorted_cats[] = $wp_cat;
                unset($all_wp_categories[$key]);
            }
        }
    }
    $sorted_cats = array_merge($sorted_cats, $all_wp_categories);

    // Header info
    $metadata = array(
		'Name'            => 'Plugin Name',
		'PluginURI'       => 'Plugin URI',
		'Version'         => 'Version',
		'Author'          => 'Author',
        'License'         => 'License'
	);
	$plugin_data = get_file_data( ANM_MAIN_FILE, $metadata, 'plugin' );


    ?>
    <div class="wrap">
        <h1>Advanced Notices Manager Settings</h1>
        
        <form action="options.php" method="post" style="margin-top: 20px;">
            <?php settings_fields('anm_settings_group'); ?>
            
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label>Excerpt Word Count</label></th>
                    <td>
                        <input name="anm_settings[excerpt_min]" type="number" value="<?= esc_attr($settings['excerpt_min']) ?>" class="small-text"> 
                        to 
                        <input name="anm_settings[excerpt_max]" type="number" value="<?= esc_attr($settings['excerpt_max']) ?>" class="small-text"> 
                        words.
                        <p class="description">Defines the "ideal" range for notice excerpts.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>Featured Image Ratio</label></th>
                    <td>
                        <input name="anm_settings[img_w]" type="number" value="<?= esc_attr($settings['img_w']) ?>" class="small-text"> 
                        : 
                        <input name="anm_settings[img_h]" type="number" value="<?= esc_attr($settings['img_h']) ?>" class="small-text">
                        <p class="description">Target aspect ratio for notice images (e.g. 16:9).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>Post Highlighting</label></th>
                    <td>
                        <input name="anm_settings[new_days]" type="number" value="<?= esc_attr($settings['new_days']) ?>" class="small-text">  
                        <p class="description">Days to highlight a post as new.</p>
                        <input name="anm_settings[stale_days]" type="number" value="<?= esc_attr($settings['stale_days']) ?>" class="small-text">
                        <p class="description">Days until post is highlighted as stale.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row" style="vertical-align: top;">Managed Categories</th>
                    <td>
                        <div id="anm-cat-list" style="max-width: 400px;">
                            <?php foreach ($sorted_cats as $cat) : 
                                $checked = in_array($cat->slug, $settings['categories']) ? 'checked' : '';
                            ?>
                                <div class="anm-sort-item" style="background:#fff; border:1px solid #c3c4c7; margin-bottom:5px; padding:8px 10px; display:flex; align-items:center; cursor:move; border-radius:3px;">
                                    <span class="dashicons dashicons-menu" style="color:#a7aaad; margin-right:10px;"></span>
                                    <label>
                                        <input type="checkbox" name="anm_settings[categories][]" value="<?= esc_attr($cat->slug) ?>" <?= $checked ?>>
                                        <?= esc_html($cat->name) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="description">Select and order categories to display in the Notices Manager.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row" style="vertical-align: top;">Tag Suffixes & Labels</th>
                    <td>
                        <table class="widefat fixed" id="anm-tags-table" style="max-width: 500px;">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Suffix (Key)</th>
                                    <th>Display Label</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody class="ui-sortable">
                                <?php foreach ($settings['tags'] as $key => $val) : ?>
                                    <tr>
                                        <td><input type="text" name="anm_settings[tag_keys][]" value="<?= esc_attr($key) ?>" class="regular-text" style="width: 95%;"></td>
                                        <td><input type="text" name="anm_settings[tag_vals][]" value="<?= esc_attr($val) ?>" class="regular-text" style="width: 95%;"></td>
                                        <td><a href="#" class="anm-remove-tag" style="color:#d63638; text-decoration:none; line-height:30px;"><span class="dashicons dashicons-no-alt"></span></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top: 10px;">
                            <button type="button" class="button" id="anm-add-tag-row">Add New Tag Suffix</button>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>Default tag</label></th>
                    <td>
                        <input name="anm_settings[default_tag]" type="text" value="<?= esc_attr($settings['default_tag']) ?>" class="small-text"> 
                        <p class="description">Default tag suffix.</p>
                    </td>
                </tr>

            </table>

            <?php submit_button(); ?>
        </form>

        <div class="notice notice-info inline" style="background: #fff; font-size: 13px; color: #646970;">
            <p><strong>Plugin:</strong> <?= esc_html($plugin_data['Name'] ?? 'Unknown') ?></p>
            <p><strong>Version:</strong> <?= esc_html($plugin_data['Version'] ?? 'Unknown') ?></p>
            <p><strong>Author:</strong> <?= wp_kses_post($plugin_data['Author'] ?? 'Unknown') ?></p>
            <p><strong>License:</strong> <?= esc_html($plugin_data['License'] ?? 'Unknown') ?></p>
            <p><a href="<?= esc_url($plugin_data['PluginURI'] ?? '#') ?>" target="_blank">Visit Plugin Website</a></p>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Fix: Enable Sortable Categories
        $('#anm-cat-list').sortable({
            axis: 'y',
            cursor: 'move',
            opacity: 0.7
        });

        // Fix: Add Tag Row Logic
        $('#anm-add-tag-row').on('click', function() {
            var row = '<tr>' +
                '<td><input type="text" name="anm_settings[tag_keys][]" class="regular-text" style="width: 95%;"></td>' +
                '<td><input type="text" name="anm_settings[tag_vals][]" class="regular-text" style="width: 95%;"></td>' +
                '<td><a href="#" class="anm-remove-tag" style="color:#d63638; text-decoration:none; line-height:30px;"><span class="dashicons dashicons-no-alt"></span></a></td>' +
                '</tr>';
            $('#anm-tags-table tbody').append(row);
        });

        // Remove Tag Row
        $(document).on('click', '.anm-remove-tag', function(e) {
            e.preventDefault();
            $(this).closest('tr').remove();
        });
    });
    </script>
    <?php
}