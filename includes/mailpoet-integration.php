<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

add_filter('mailpoet_newsletter_shortcode', function ($shortcode, $newsletter, $subscriber, $queue, $newsletter_body, $arguments) {
    if (strpos($shortcode, '[custom:download_plain_text') !== 0) return $shortcode;

    $date_args = '&year=' . date('Y') . '&month=' . date('m') . '&day=' . date('d');
    return 'Also available in text only versions:
                <a href="' . home_url('/?plain_text=html&toc=false') . $date_args . '" target="_blank">online</a>, 
                <a href="' . home_url('/?plain_text=pdf') . $date_args . '" target="_blank">PDF</a>, 
                <a href="' . home_url('/?plain_text=docx') . $date_args . '" target="_blank">DOCX</a>';
}, 10, 6);

add_filter('mailpoet_newsletter_shortcode', function ($shortcode, $newsletter, $subscriber, $queue, $newsletter_body, $arguments) {
    if (strpos($shortcode, '[custom:post_query') !== 0) return $shortcode;

    $tags_arg   = isset($arguments['tags']) ? explode(',', $arguments['tags']) : [];
    $cat_arg    = isset($arguments['categories']) ? explode(',', $arguments['categories']) : [];
	$empty      = isset($arguments['empty']) ? esc_html($arguments['empty']) : "";
	$hr         = isset($arguments['hr']) ? filter_var($arguments['hr'], FILTER_VALIDATE_BOOLEAN) : false;
	$show_image = isset($arguments['image']) ? filter_var($arguments['image'], FILTER_VALIDATE_BOOLEAN) : true;
    $use_content= isset($arguments['content']) ? filter_var($arguments['content'], FILTER_VALIDATE_BOOLEAN) : false;
    $post_limit = isset($arguments['limit']) ? intval($arguments['limit']) : 12;    
	$read_more  = isset($arguments['more']) ? esc_html($arguments['more']) : "Read More";
    $is_event_query = isset($arguments['event']) ? filter_var($arguments['event'], FILTER_VALIDATE_BOOLEAN) : false;

    $args = ['post_type' => 'post', 'posts_per_page' => $post_limit, 'post_status' => 'publish'];
    if ($is_event_query) { 
        $args['meta_key'] = 'event_start_time'; 
        $args['orderby'] = 'meta_value'; 
        $args['meta_type'] = 'DATETIME';
        $args['order'] = 'ASC'; 
    } else { 
        $args['orderby'] = 'menu_order date'; // Sorts by menu order first, then date
        $args['order'] = 'ASC';
    }

    if (!empty($tags_arg)) $args['tag_slug__in'] = array_map('trim', $tags_arg);
    if (!empty($cat_arg)) $args['category_name'] = implode(',', array_map('trim', $cat_arg));

    $query = new \WP_Query($args);
    
    if (!$query->have_posts()) return $empty;

    $output = '<table width="100%" style="width:100%; max-width:600px">';

    while ($query->have_posts()) {
        $query->the_post();
        $permalink = get_permalink();
        $thumbnail_url = get_the_post_thumbnail_url(get_the_ID(), 'medium_large');
        $thumbnail_alt_text = get_thumbnail_alt_text(get_the_ID());
        
        $post_tags = get_the_tags();
        $has_short_tag = false;
        if ($post_tags) {
            foreach ($post_tags as $tag) {
                if (str_ends_with($tag->slug, '-short')) {
                    $has_short_tag = true;
                    break;
                }
            }
        }
        
        $text = $use_content ? get_the_content() : get_the_excerpt();
        $text = trim($text);
		
        $formatted_date = '';
        if ($is_event_query) {
            $event_raw = get_post_meta(get_the_ID(), 'event_start_time', true);
            if ($event_raw) {
                $timestamp = strtotime($event_raw);
                $time_part = (date('i', $timestamp) !== '00') ? date('g:ia', $timestamp) : date('ga', $timestamp);
                $formatted_date = date('jS F Y', $timestamp) . ' ' . $time_part;
            }
        }

        $output .= '
        <tr>
            <td style="padding-bottom:30px;">
                <table style="width: 100%;">';

        if ($show_image && $thumbnail_url) {
            $output .= '                
                    <tr>
                        <td>
                            <img src="'.esc_url($thumbnail_url).'" alt="' . $thumbnail_alt_text . '" width="600" style="width:100%;margin-bottom:10px">
                        </td>
                    </tr>';
        }
        $output .= '
                    <tr>
                        <td style="padding-left:10px; padding-right:10px; font-family:Arial, sans-serif">
                            <a href="'.esc_url($permalink).'" style="text-decoration:none; color:#333333;">
                                <span style="font-size:22px; font-weight:bold; line-height: 28px;">'.get_the_title().'</span>
                            </a>';
                
        if ($is_event_query && !empty($formatted_date)) {
            $output .= '
                            <div style="line-height:8px; font-size:8px;">&nbsp;</div>
                            <span style="font-size:14px; color:#777777;">'.esc_html($formatted_date).'</span>';
        }
        $output .= '
                            <div style="line-height:15px; font-size:15px;">&nbsp;</div>
                            <div style="font-size:16px; line-height:24px;">'.$text.'</div>';

        if (!empty($read_more)) {
            $output .= '
                            <div style="text-align: right; width: 100%;">
                                <a href="'.esc_url($permalink).'" target="_blank" style="color: #0073aa; text-decoration: underline; font-size: 16px; font-weight: bold;">
                                    <span>'.$read_more.'</span>
                                </a>
                            </div>';
        }
        $output .= '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>';
        if ($hr) {
            $output .= '
        <tr>
            <td style="padding: 0 0 30px 0;">
                <hr style="border: 0; border-top: 2px solid #eeeeee; margin: 0;">
            </td>
        </tr>';
        }
    }
    wp_reset_postdata();
    $output .= '</table>';
    
    return $output;
}, 10, 6);

add_filter('mailpoet_newsletter_shortcode', function ($shortcode, $newsletter, $subscriber, $queue, $newsletter_body, $arguments) {
    if (strpos($shortcode, '[custom:post_list') !== 0) return $shortcode;
	
    $tags_arg   = isset($arguments['tags']) ? explode(',', $arguments['tags']) : [];
    $cat_arg    = isset($arguments['categories']) ? explode(',', $arguments['categories']) : [];
	$empty      = isset($arguments['empty']) ? esc_html($arguments['empty']) : "";
	$show_image = isset($arguments['image']) ? filter_var($arguments['image'], FILTER_VALIDATE_BOOLEAN) : true;
	$zigzag	    = isset($arguments['zigzag']) ? filter_var($arguments['zigzag'], FILTER_VALIDATE_BOOLEAN) : true;
    $post_limit = isset($arguments['limit']) ? intval($arguments['limit']) : 12;
	$read_more  = isset($arguments['read_more']) ? esc_html($arguments['read_more']) : "Read More";
	$is_event_query = isset($arguments['event']) ? filter_var($arguments['event'], FILTER_VALIDATE_BOOLEAN) : false;

    $args = ['post_type' => 'post', 'posts_per_page' => $post_limit, 'post_status' => 'publish'];
    if ($is_event_query) { 
        $args['meta_key'] = 'event_start_time'; 
        $args['orderby'] = 'meta_value';
        $args['meta_type'] = 'DATETIME'; 
        $args['order'] = 'ASC'; 
    } else { 
        $args['orderby'] = 'menu_order date'; // Sorts by menu order first, then date
        $args['order'] = 'ASC';
    }

    if (!empty($tags_arg)) $args['tag_slug__in'] = array_map('trim', $tags_arg);
    if (!empty($cat_arg)) $args['category_name'] = implode(',', array_map('trim', $cat_arg));

    $query = new \WP_Query($args);
    
    if (!$query->have_posts()) return $empty;    

	$output = '';
    while ($query->have_posts()) {
        $query->the_post();
        $permalink = get_permalink();
        $thumbnail_url = get_the_post_thumbnail_url(get_the_ID(), 'medium_large');
        $thumbnail_alt_text = get_thumbnail_alt_text(get_the_ID());
        
        $post_tags = get_the_tags();
        $has_short_tag = false;
        if ($post_tags) {
            foreach ($post_tags as $tag) {
                if (str_ends_with($tag->slug, '-short')) {
                    $has_short_tag = true;
                    break;
                }
            }
        }
        
        $text = $has_short_tag ? get_the_excerpt() : get_the_content();
        $text = trim($text);
		
        $formatted_date = '';
        if ($is_event_query) {
            $event_raw = get_post_meta(get_the_ID(), 'event_start_time', true);
            if ($event_raw) {
                $timestamp = strtotime($event_raw);
                $time_part = (date('i', $timestamp) !== '00') ? date('g:ia', $timestamp) : date('ga', $timestamp);
                $formatted_date = date('jS F Y', $timestamp) . ' ' . $time_part;
            }
        }

		if (!$zigzag || $query->current_post % 2 == 0) {
			/* Image Left, Text Right */
			$output .= '
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" align="center" style="width:100%; max-width:600px; margin:0 auto; border-collapse: separate;">
    <tr>
        <td style="font-size:0;width: 600px;" align="left" valign="top">
<!--[if (gte mso 9)|(IE)]>
            <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%;">
                <tr>
                    <td style="font-size:0; text-align:center;" align="center" valign="top">
<![endif]-->
                        <div style="display:inline-block; vertical-align:top; width:100%; max-width:300px;">
                            <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%;">
                                <tr>
                                    <td align="left" valign="top" style="padding: 0px; margin: 0px;">
                                        <img src="'.esc_url($thumbnail_url).'" width="300" alt="' . $thumbnail_alt_text .'" style="display:block; width:100%; min-width:100%; height:auto;margin-bottom:10px" />
                                    </td>
                                </tr>
                            </table>
                        </div>
<!--[if (gte mso 9)|(IE)]>
                    </td>
                    <td valign="top" style="width:300px;">
<![endif]-->
                        <div style="display:inline-block; vertical-align:top; width:100%; max-width:300px;">
                            <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="width:100%;">
                                <tr>
                                    <td align="left" valign="top" style="padding-left:10px; padding-right:10px">
                                            <span style="font-size:22px; font-weight:bold; line-height: 28px;">'.get_the_title().'</span>';
            
                if ($is_event_query && !empty($formatted_date)) {
                    $output .= '
                                        <div style="line-height:8px; font-size:8px;">&nbsp;</div>
                                        <span style="font-size:14px; color:#777777;">'.esc_html($formatted_date).'</span>';
                }

                $output .= '
                                        <div style="line-height:15px; font-size:15px;">&nbsp;</div>
                                        <div style="font-size:16px; line-height:24px; color:#444444; text-align:left;">'.$text.'</div>
                                        <div style="text-align: right; width: 100%;"><a href="' . esc_url($permalink) . '" target="_blank" style="color: #0073aa; text-decoration: underline; font-size: 16px; font-weight: bold;"><span>'.$read_more.'</span></a></div>
                                    </td>
                                </tr>
                            </table>
                        </div>
<!--[if (gte mso 9)|(IE)]>
                    </td>
                </tr>
        </table>
<![endif]-->
        </td>
    </tr>
</table>';
		} else {
		/* Text Left, Image Right */
 	$output .= '
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" align="center" style="width:100%; max-width:600px; margin:0 auto; border-collapse: separate;">
    <tr>
        <td style="font-size:0;width: 600px;" align="left" valign="top" dir="rtl">
<!--[if (gte mso 9)|(IE)]>
            <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%;">
                <tr>
                    <td style="font-size:0; text-align:center;" align="center" valign="top">
<![endif]-->
                        <div style="display:inline-block; vertical-align:top; width:100%; max-width:300px;">
                            <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%;" dir="ltr">
                                <tr>
                                    <td align="left" valign="top" style="padding: 0px; margin: 0px;">
                                        <img src="'.esc_url($thumbnail_url).'" width="300" alt="' . $thumbnail_alt_text . '" style="display:block; width:100%; min-width:100%; height:auto;margin-bottom:10px" />
                                    </td> 
                                </tr>
                            </table>
                        </div>
<!--[if (gte mso 9)|(IE)]>
                    </td>
                    <td valign="top" style="width:300px;">
<![endif]-->
                        <div style="display:inline-block; vertical-align:top; width:100%; max-width:300px;">
                            <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="width:100%;" dir="ltr">
                                <tr>
                                    <td align="left" valign="top" style="padding-left:10px; padding-right:10px">
                                        <span style="font-size:22px; font-weight:bold; line-height: 28px;">'.get_the_title().'</span>';

                if ($is_event_query && !empty($formatted_date)) {
                    $output .= '
                                        <div style="line-height:8px; font-size:8px;">&nbsp;</div>
                                        <span style="font-size:14px; color:#777777;">'.esc_html($formatted_date).'</span>';
                }

                $output .= '
                                        <div style="line-height:15px; font-size:15px;">&nbsp;</div>
                                        <div style="font-size:16px; line-height:24px; color:#444444; text-align:left;">'.$text.'</div>
                                        <div style="text-align: right; width: 100%;"><a href="' . esc_url($permalink) . '" target="_blank" style="color: #0073aa; text-decoration: underline; font-size: 16px; font-weight: bold;"><span>'.$read_more.'</span></a></div>
                                    </td>
                                </tr>
                            </table>
                        </div>
<!--[if (gte mso 9)|(IE)]>
                    </td>
                </tr>
            </table>
<![endif]-->
        </td>
    </tr>
</table>
';
		}
		
		$output .= '
<table>
    <tr>
        <td style="width: 600px;">
            <hr style="border: 0; border-top: 2px solid #eeeeee; margin: 0;">
        </td>
    </tr>
</table>';
	}
    wp_reset_postdata();
	
	return $output;
}, 10, 6);

add_filter('mailpoet_newsletter_shortcode', function($shortcode, $newsletter, $subscriber, $queue, $newsletter_body, $arguments) {
    if (strpos($shortcode, '[custom:post_grid') !== 0) {
        return $shortcode;
    }

    $tags_arg   = isset($arguments['tags']) ? explode(',', $arguments['tags']) : [];
    $cat_arg    = isset($arguments['categories']) ? explode(',', $arguments['categories']) : [];
    $post_limit = isset($arguments['limit']) ? intval($arguments['limit']) : 12;
	$empty      = isset($arguments['empty']) ? esc_html($arguments['empty']) : "";
    $is_event_query = isset($arguments['event']) ? filter_var($arguments['event'], FILTER_VALIDATE_BOOLEAN) : false;

    $args = ['post_type' => 'post', 'posts_per_page' => $post_limit, 'post_status' => 'publish'];
    if ($is_event_query) {
        $args['meta_key'] = 'event_start_time';
        $args['orderby'] = 'meta_value';
        $args['meta_type'] = 'DATETIME';
        $args['order'] = 'ASC';
    } else {
        $args['orderby'] = 'menu_order date'; // Sorts by menu order first, then date
        $args['order'] = 'ASC';
    }

    if (!empty($tags_arg)) $args['tag_slug__in'] = array_map('trim', $tags_arg);
    if (!empty($cat_arg)) $args['category_name'] = implode(',', array_map('trim', $cat_arg));

    $query = new \WP_Query($args);
	
	// Logic: If no posts found, return nothing immediately
    if (!$query->have_posts()) return $empty;
    
    // Use border-collapse: separate to prevent border bleeding
    $output = '
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" align="center" style="width:100%; max-width:600px; margin:0 auto; border-collapse: separate;">';
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            
            $permalink = get_permalink();
            $formatted_date = '';
            if ($is_event_query) {
                $event_raw = get_post_meta(get_the_ID(), 'event_start_time', true);
                if ($event_raw) {
                    $timestamp = strtotime($event_raw);
                    $time_part = (date('i', $timestamp) !== '00') ? date('g:ia', $timestamp) : date('ga', $timestamp);
                    $formatted_date = date('jS F Y', $timestamp) . ' ' . $time_part;
                }
            }
            
            $thumbnail_url = get_the_post_thumbnail_url(get_the_ID(), 'medium') ?: '';
            $thumbnail_alt_text = get_thumbnail_alt_text(get_the_ID());

            if ($query->current_post % 2 == 0) {
                $output .= '
    <tr>';
            }

            // Outer TD now has NO border and NO background
            $output .= '
        <td width="49%" valign="top" style="width: 49%; padding: 0;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #ffffff; border: 1px solid #eeeeee; border-collapse: collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;">
                <tr>
                    <td valign="top" align="left" style="padding: 0; margin: 0; line-height: 10px; font-size: 10px;">
                        <img src="' . esc_url($thumbnail_url) . '" alt="' . $thumbnail_alt_text . '" width="292" border="0" style="display: block; width: 100%; height: auto; border: 0;">
                    </td>
                </tr>
                <tr>
                    <td valign="top" style="padding: 10px; font-family: Arial, sans-serif; line-height: 1.2; mso-line-height-rule: exactly;">
                        <a href="' . esc_url($permalink) . '" target="_blank" style="text-decoration: none; color: #333333; display: block; border: 0;">
                            <span style="font-size: 14px; line-height: 16px; font-weight: bold; color: #333333; text-decoration: none;">' . get_the_title() . '</span>
                        </a>';
                            
            if ($is_event_query && !empty($formatted_date)) {
                $output .= '
                        <div style="line-height: 4px; font-size: 4px;">&nbsp;</div>
                        <span style="font-size: 11px; color: #777777; line-height: 13px;">' . esc_html($formatted_date) . '</span>';
            }

            $output .= '
                    </td>
                </tr>
            </table>
        </td>';

            // Add vertical spacer logic
            if ($query->current_post % 2 == 0) {
                $output .= '
        <td width="5" style="width: 5px; font-size: 1px; line-height: 1px; border:none; background:none;">&nbsp;</td>';
            }

            if ($query->current_post % 2 == 1) {
                // Add horizontal spacer
                $output .= '
    </tr>
    <tr>
        <td colspan="3" height="10" style="height: 10px; line-height: 10px; font-size: 1px; border:none; background:none;">
            &nbsp;
        </td>
    </tr>';
            }
        }
        
        if ($query->current_post % 2 == 0) {
            $output .= '
        <td width="49%" style="width: 49%;">&nbsp;</td>
    </tr>';
        }
        wp_reset_postdata();
    }
    $output .= '
</table>';
    return $output;
}, 10, 6);

add_filter('mailpoet_newsletter_shortcode', function ($shortcode, $newsletter, $subscriber, $queue, $newsletter_body, $arguments) {
    if (strpos($shortcode, '[custom:post_titles_list') !== 0) return $shortcode;

    $tags_arg       = isset($arguments['tags']) ? explode(',', $arguments['tags']) : [];
    $cat_arg        = isset($arguments['categories']) ? explode(',', $arguments['categories']) : [];
    $post_limit     = isset($arguments['limit']) ? intval($arguments['limit']) : 5;
    $empty_msg      = isset($arguments['empty']) ? esc_html($arguments['empty']) : "";
    $is_event_query = isset($arguments['event']) ? filter_var($arguments['event'], FILTER_VALIDATE_BOOLEAN) : false;

    // Default arguments
    $args = [
        'post_type'      => 'post',
        'posts_per_page' => $post_limit,
        'post_status'    => 'publish',
    ];

    // Handle Conditional Sorting Logic
    if ($is_event_query) {
        $args['meta_key']  = 'event_start_time';
        $args['orderby']   = 'meta_value';
        $args['meta_type'] = 'DATETIME'; // Ensures chronological sorting
        $args['order']     = 'ASC';      // Show nearest upcoming events first
    } else {
        // Sorts by Menu Order (Page Attributes) first, then by Date
        $args['orderby'] = 'menu_order date'; 
        $args['order']   = 'ASC';
    }

    if (!empty($tags_arg)) $args['tag_slug__in'] = array_map('trim', $tags_arg);
    if (!empty($cat_arg))  $args['category_name'] = implode(',', array_map('trim', $cat_arg));

    $query = new \WP_Query($args);

    if (!$query->have_posts()) return $empty_msg;

    $output = '<ul style="font-family: Arial, sans-serif; font-size: 16px; line-height: 24px; color: #333333;">';

    while ($query->have_posts()) {
        $query->the_post();
        $output .= '<li style="margin-bottom: 5px;">' . get_the_title() . '</li>';
    }

    $output .= '</ul>';

    wp_reset_postdata();
    return $output;
}, 10, 6);

function get_thumbnail_alt_text(int $post_id): string {
    $attachment_id = get_post_thumbnail_id($post_id);
    if (!$attachment_id) return '';
    return esc_attr(get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
}