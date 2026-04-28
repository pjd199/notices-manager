<?php

namespace AdvancedNoticesManager;

function organize_post_data($all_posts) {
    $settings = anm_get_settings();
    $data = array_fill_keys($settings['categories'], []);

    if (empty($all_posts)) return $data;

    // Group posts by category
    foreach ($all_posts as $post) {
        foreach ($settings['categories'] as $cat) {
            if (has_category($cat, $post)) {
                $data[$cat][] = $post;
                break; 
            }
        }
    }
    $data = array_filter($data, fn($posts) => !empty($posts));

    // Sort each group individually, with date sorting for events
    foreach ($data as $cat => &$posts) {
        if ($cat === 'events') {
            usort($posts, function($a, $b) {
                $date_a = get_post_meta($a->ID, 'event_start', true);
                $date_b = get_post_meta($b->ID, 'event_start', true);
        
                // If both are missing, sort by title as a fallback
                if (empty($date_a) && empty($date_b)) {
                    return strcmp($a->post_title, $b->post_title);
                }
        
                // If only one is missing, push the missing one to the end
                if (empty($date_a)) return 1;
                if (empty($date_b)) return -1;
        
                // Both have dates, sort normally
                return strcmp($date_a, $date_b);
            });
        }
    }

    return $data;
}


function plain_text_notices($year, $month, $day, $toc = true) {
    global $wpdb;
    $settings = anm_get_settings();
    $archive = null;

    // Attempt to fetch by specific date if provided
    if ($year && $month && $day) {
        $target_date = sprintf('%04d-%02d-%02d', intval($year), intval($month), intval($day));
        $archive = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE archive_date = %s",
            ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE,
            $target_date
        ));
    }

    // if archive found, get posts, else get current posts
    if ($archive) {
        $all_posts = get_posts([
            'post__in'       => explode(',', $archive->post_ids),
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);
    } else {
        list($year, $month, $day) = explode('-', date('Y-m-d'));
        $tags = array_merge(...array_map(
            fn($cat) => array_map(fn($s) => $cat . $s, $settings['suffixes']),
            $settings['categories']
        ));

        $all_posts = get_posts([
                'tax_query'     => [['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $tags]],
                'posts_per_page' => -1,
                'post_status'    => 'publish'
        ]);      
    }
    $data = organize_post_data($all_posts);
    $creation_date = date('l jS F Y', mktime(0, 0, 0, $month, $day, $year));

    $html = <<<END
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>Notices - $creation_date</title>
        <style>
            body {
                max-width: 800px;
                margin: auto;
                font-family: Helvetica, Arial, sans-serif; 
                font-size: 18px;
                line-height: 1.6;
                padding: 0 8px; /* add this */
                box-sizing: border-box; /* add this */
            }
            h1 {
                text-align: center;
            }
            h2 { 
                background:#f0f0f0;
                padding:5px;
            }
            h1, h2, h3, h4, h5, h6 { 
                break-after: avoid; 
                page-break-after: avoid; 
            }
            hr {
                border: 0; 
                border-top: 1px solid #333333; 
                margin: 20px 0;
            }
            a { 
                overflow-wrap: break-word; 
            }
        </style>
    </head>
    <body>
        <h1>Horsham Churches Together</h1>
        <p style="text-align:center; font-weight:bold; font-style:italic">$creation_date</p>
        <p style="text-align:center">Read the latest news and events online and subscribe to our weekly emails at <a href="https://www.horshamct.org.uk/notices">www.horshamct.org.uk/notices</a>.</p>        
END;

    // Create the TOC
    if ($toc) {
        $html .= '<h2>Contents</h2>';
        foreach ($data as $cat => $posts) {
            $html .= '<h3>' . esc_html(get_category_by_slug($cat)->name) . '</h3>';
            $html .= '<ul>';
            foreach ($posts as $p) {
                $html .= sprintf('<li><a href="#anm-post-%d">%s</a></li>', $p->ID, esc_html($p->post_title));
            }
            $html .= '</ul>';
        }
    }

    $purifier = get_html_purifier();
    foreach($data as $cat => $posts) {
        $html .= sprintf('<h2 id="anm-category-%s">%s</h2>', $cat, esc_html(get_category_by_slug($cat)->name));
        $add_hr = false;
        foreach($posts as $p) {
            if ($add_hr) {
                $html .= '<hr>';
            }
            $html .= sprintf('<h3 id="anm-post-%d">%s</h3>', $p->ID, $p->post_title);
            $event_start_raw = get_post_meta($p->ID, 'event_start_time', true);
            if ($event_start_raw) {
                $date = new \DateTime($event_start_raw);
                $html .= '<i>'. $date->format('l jS F Y \a\t g:ia') . '</i>';
            }
            //$html .= headings_to_bold($purifier->purify(apply_filters('the_content', $p->post_content)));
            $html .= $purifier->purify(apply_filters('the_content', $p->post_content));
            $add_hr = true;
        }
    }
    $signup_url = site_url('signup');
    $html .= <<<END
        <h2>Anything to share?</h2>
        <p>Do you have any news, events, jobs or volunteering opportunties to share 
            from your church or Christian charity? Please email your notices to 
            <a href="mailto:admin@horshamct.org.uk">admin@horshamct.org.uk</a> 
            by noon on Wednesday for publication in next week's HCT Notices.
        </p>
        <p>
            Whilst we've carefully chosen this content because we hope it will be useful
            to you and your church family, we are not directly responsible for the content
            from external groups and organisations.
        </p>
    </body>
</html>
END;

    $html = preg_replace('/^\s*[\r\n]/m', '', $html);
    return $html;
}