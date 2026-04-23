<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

function get_organized_data_from_ids($id_array) {
    // If the array is empty or not an array, return early.
    if (empty($id_array) || !is_array($id_array)) {
        return [];
    }

    $settings = anm_get_settings();
    $data = array_fill_keys($settings['categories'], []);

    // Run post query
    $all_posts = get_posts([
        'post__in'       => $id_array,
        'category_name'  => implode(',', $settings['categories']),
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);
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

function parse_node_to_word($node, &$section) {
    // Check for Text Nodes (Type 3)
    if ($node->nodeType === 3) {
        $text = $node->nodeValue; // Use nodeValue for text nodes
        if (strlen(trim($text)) > 0) {
            $section->addText($text);
        }
        return;
    }

    switch ($node->nodeName) {
        case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
            $clean_text = htmlspecialchars($node->nodeValue, ENT_XML1, 'UTF-8');
            $section->addText($clean_text, ['bold' => true]);
            break;
            
        case 'p':
        case 'div':
        case 'span':
            // Create a new text run for the paragraph to handle nested <strong> or <a>
            $textRun = $section->addTextRun();
            foreach ($node->childNodes as $child) {
                process_inline_tags($child, $textRun);
            }
            break;

        case 'ul':
        case 'ol':
            $style = ($node->nodeName === 'ul') 
                ? \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED 
                : \PhpOffice\PhpWord\Style\ListItem::TYPE_NUMBER;
        
            foreach ($node->childNodes as $li) {
                // Skip whitespace nodes between <li> tags
                if ($li->nodeName !== 'li') continue;
        
                // addListItemRun allows us to put links/bold/italics inside a bullet
                $listItemRun = $section->addListItemRun(0, $style);
                
                // Process the contents of the <li> just like a paragraph
                foreach ($li->childNodes as $child) {
                    process_inline_tags($child, $listItemRun);
                }
            }
            break;
    }
}

/**
 * Handles bold, italics, and links inside a paragraph (TextRun)
 */
function process_inline_tags($node, &$textRun) {
    $fontStyle = [];
    if ($node->nodeName === 'strong' || $node->nodeName === 'b') $fontStyle['bold'] = true;
    if ($node->nodeName === 'em' || $node->nodeName === 'i') $fontStyle['italic'] = true;
    
    if ($node->nodeName === 'a') {
        $href = $node->getAttribute('href');
        $clean_text = htmlspecialchars($node->nodeValue, ENT_XML1, 'UTF-8');
        if ($href) {
            $textRun->addLink($href, $clean_text, ['color' => '0000FF', 'underline' => 'single']);
        } else if (strlen(trim($clean_text)) > 0) {
            $textRun->addText($clean_text, $fontStyle);
        }
    } elseif ($node->nodeType === XML_TEXT_NODE) {
        $clean_text = htmlspecialchars($node->nodeValue, ENT_XML1, 'UTF-8');
        if (strlen(trim($clean_text)) > 0) {
            $textRun->addText($clean_text, $fontStyle);
        }
    } else {
        // Recurse for nested tags like <strong><em>text</em></strong>
        foreach ($node->childNodes as $child) {
            process_inline_tags($child, $textRun);
        }
    }
}

function get_purifier() {
    static $purifier = null;
    if ($purifier) return $purifier;

    $config = \HTMLPurifier_Config::createDefault();

    // Only allow the elements you actually need
    $config->set('HTML.Allowed', 'p,br,strong,b,em,i,ul,ol,li,a[href],sup,sub,h1,h2,h3,h4,h5,h6');

    // Strip all inline styles and classes
    $config->set('CSS.AllowedProperties', []);
    $config->set('HTML.AllowedAttributes', 'a.href');

    // Force links to be absolute (prevents relative link weirdness in DOCX/PDF)
    $config->set('URI.Base', get_site_url());
    $config->set('URI.MakeAbsolute', true);
    $config->set('AutoFormat.Linkify', false); // prevents bare URLs being wrapped in extra <a> tags
    
    // Strip empty tags
    $config->set('AutoFormat.RemoveEmpty', true);
    $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true); // also catches &nbsp; only elements

    // Cache dir — use WordPress uploads so it's writable
    $upload_dir = wp_upload_dir();
    $cache_path = $upload_dir['basedir'] . '/htmlpurifier-cache';
    if (!is_dir($cache_path)) wp_mkdir_p($cache_path);
    $config->set('Cache.SerializerPath', $cache_path);

    $purifier = new \HTMLPurifier($config);
    return $purifier;
}

function headings_to_bold($html) {
    foreach (['h1','h2','h3','h4','h5','h6'] as $tag) {
        $html = preg_replace('/<'.$tag.'>(.*?)<\/'.$tag.'>/is', '<strong>$1</strong>', $html);
    }
    return $html;
}

add_action('template_redirect', function() {
    global $wpdb;
    
    // Check which format is requested
    $docx_req = isset($_GET['notice_archive_docx']);
    $pdf_req  = isset($_GET['notice_archive_pdf']);
    $html_req = isset($_GET['notice_archive_html']);

    if (!$docx_req && !$pdf_req && !$html_req) {
        return;
    }

    // Capture date components
    $year  = $_GET['notice_archive_year']  ?? null;
    $month = $_GET['notice_archive_month'] ?? null;
    $day   = $_GET['notice_archive_day']   ?? null;

    $archive = null;

    // Attempt to fetch by specific date if provided
    if ($year && $month && $day) {
        $target_date = sprintf('%04d-%02d-%02d', intval($year), intval($month), intval($day));
        $archive = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE . " WHERE archive_date = %s",
            $target_date
        ));
    }
    if (!$archive) {
        list($year, $month, $day) = explode('-', date('Y-m-d'));

        // Fallback: If date lookup fails or wasn't provided, get the current state of play
        $settings = anm_get_settings();
        $all_ids   = [];

        foreach ($settings['categories'] as $cat) {
            $tags = array_map(fn($s) => $cat . $s, $settings['suffixes']);
            $p_ids = get_posts([
                'category_name' => $cat,
                'tax_query'     => [['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $tags]],
                'fields'        => 'ids', 
                'posts_per_page' => -1,
                'post_status'    => 'publish'
            ]);
            $all_ids = array_merge($all_ids, $p_ids);
        }
        
        $unique_ids = array_unique($all_ids);
        sort($unique_ids); 
        $post_ids_string = implode(',', $unique_ids);
    }

    $data = get_organized_data_from_ids(explode(',', $archive ? $archive->post_ids : $post_ids_string));

    $purifier = get_purifier();
    
    // --- HTML VIEW ---
    if ($html_req) {
        
        // Update save links to use date-based parameters for consistency
        $date_query = "&notice_archive_year={$year}&notice_archive_month={$month}&notice_archive_day={$day}";

        $html = '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Notices - ' . $day . '/' . $month . '/' . $year . '</title>
                    <style>
                        * { box-sizing: border-box; }
                        body { max-width: 800px; margin: auto; font-family: sans-serif; padding: 0 16px; font-size: 18px; line-height: 1.6; }
                        .toolbar { background: #eee; padding: 10px 16px; margin: 0 -16px 24px; display: flex; gap: 16px; flex-wrap: wrap; }
                        .toolbar a { text-decoration: none; color: #0000EE; }
                        h1 { font-size: clamp(1.2rem, 5vw, 1.8rem); }
                        h2 { font-size: clamp(1rem, 4vw, 1.4rem); }
                        hr { border: 0; border-top: 1px solid #333; margin: 20px 0; }
                        img { max-width: 100%; height: auto; }
                        a { word-break: break-word; }
                    </style>
                </head>
                <body>';
        $html .= '<div style="background:#eee; padding:10px;" class="toolbar"><a href="?notice_archive_docx=1'.$date_query.'">Save DOCX</a> | <a href="?notice_archive_pdf=1'.$date_query.'">Save PDF</a></div>';
        foreach ($data as $cat => $posts) {
            $html .= "<h1>".strtoupper($cat)."</h1>";
            $add_hr = false;
            foreach($posts as $p) {
                if ($add_hr) {
                    $html .= '<hr style="border: 0; border-top: 1px solid #333333; margin: 20px 0;">';
                }
                $clean_content = headings_to_bold($purifier->purify($p->post_content));
                $html .= '<h2>' . $p->post_title . '</h2>';
                
                $event_start_raw = get_post_meta($p->ID, 'event_start_time', true);
                if ($event_start_raw) {
                    $date = new \DateTime($event_start_raw);
                    $html .= '<div><i>'. $date->format('l jS F Y \a\t g:ia') . '</i></div>';
                }

                $html .= '<div>'.$clean_content . '</div>';
                $add_hr = true;
            }
        }
        $html .= '</body></html>';
        echo $html;
        exit;
    }

    // --- DOCX GENERATION (Using PHPWord) ---
    if ($docx_req) {
        // 1. Clear any accidental whitespace or warnings from other files
        while (ob_get_level()) {
            ob_end_clean();
        }

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
 
        // Set some basic metadata to help Word validate the file
        $phpWord->getDocInfo()->setCreator('Wordpress');
        $phpWord->getDocInfo()->setTitle('Notices-' . $day . '-' . $month . '-' . $year);

        // Add styles
        $headingStyle = array('name' => 'Verdana', 'size' => 20, 'bold' => true, 'color' => '333333');
        $phpWord->addTitleStyle(1, [...$headingStyle, 'size' => 16], array('spaceAfter' => 240, 'keepNext' => true));
        $phpWord->addTitleStyle(2, [...$headingStyle, 'size' => 14], array('spaceAfter' => 120, 'keepNext' => true));
        $phpWord->addTitleStyle(3, [...$headingStyle, 'size' => 12], array('spaceAfter' => 120, 'keepNext' => true));
        
        $phpWord->setDefaultFontName('Georgia');
        $phpWord->setDefaultFontSize(12);
        $phpWord->setDefaultFontColor('333333');
        
        $phpWord->addNumberingStyle(
            'bulletStyle',
            array(
                'type' => 'multilevel',
                'levels' => array(
                    array('level' => 0, 'format' => 'bullet', 'text' => '•', 'left' => 360, 'hanging' => 360),
                    array('level' => 1, 'format' => 'bullet', 'text' => '○', 'left' => 720, 'hanging' => 360),
                ),
            )
        );

        $section = $phpWord->addSection([
            'paperSize'    => 'A4',
            'marginTop'    => 1134,
            'marginBottom' => 1134,
            'marginLeft'   => 1134,
            'marginRight'  => 1134,
        ]);
        
        $section->addText(
            'Horsham Churches Together', 
            [...$headingStyle, 'size' => 28],
            array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 240) // Paragraph Style
        );
        
        // Create the TOC
        $section->addTitle("Contents", 2);
        foreach ($data as $cat => $posts) {
            // Category Heading
            $section->addTitle(ucfirst($cat), 3);
            
            foreach ($posts as $p) {
                $textRun = $section->addTextRun(array('numStyle' => 'bulletStyle', 'depth' => 0, 'spaceAfter' => 60));
                $textRun->addLink('#' . strval($p->ID), $p->post_title, array('color' => '0000FF', 'underline' => 'single'));
            }
        }
        $section->addTextBreak(1);
    
        foreach ($data as $cat => $posts) {
            // Add a Title style for categories
            $section->addTitle(strtoupper($cat), 1);

            foreach ($posts as $p) {

                // Add the Title
                $section->addBookmark($p->ID);
                $section->addTitle($p->post_title, 2);
                
                // Add the event date and time, is set
                $event_start_raw = get_post_meta($p->ID, 'event_start_time', true);
                
                if ($event_start_raw) {
                    $date = new \DateTime($event_start_raw);
                    $event_label = $date->format('l jS F Y \a\t g:ia');
                    $section->addText($event_label, array('italic' => true, 'color' => '333333'), array('keepNext' => true));
                }
                
                // add the body
                $dom = new \DOMDocument();
                $html_string = '<?xml encoding="UTF-8"><html><head><meta charset="UTF-8"></head><body>' . $p->post_content . '</body></html>';
                @$dom->loadHTML($html_string, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            
                // Get the body element we just created
                $body = $dom->getElementsByTagName('body')->item(0);
            
                if ($body && $body->hasChildNodes()) {
                    foreach ($body->childNodes as $node) {
                        parse_node_to_word($node, $section);
                    }
                } else {
                    $section->addText(strip_tags($p->post_content));
                }
                $section->addTextBreak(1);
            }
        }
        
        // Add the page numbers
        $footer = $section->addFooter();
        $footerRun = $footer->addTextRun(array(
            'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            'spaceBefore' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(10)
        ));
        $footerRun->addField('PAGE', array('format' => 'Arabic'));
        $footerRun->addText(' / ');
        $footerRun->addField('NUMPAGES', array('format' => 'Arabic'));
    
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="Notices-' . $year . '-' . $month . '-' . $day . '.docx"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
    
        if (ob_get_length()) ob_clean();
        flush();
    
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
        exit;
    }

    // --- PDF GENERATION (Using Dompdf) ---
    if ($pdf_req) {
        if (ob_get_length()) ob_end_clean();
        
        $html = '<html>
                    <head>
                        <meta charset="UTF-8">
                        <style>
                            body { font-family: sans-serif; }
                            h1 { text-align: center; }
                            h2 { background:#f0f0f0; padding:5px; }
                            h1, h2, h3, h4, h5, h6 { break-after: avoid; page-break-after: avoid; }
                            @page {
                                margin: 1cm;
                            }
                        </style>
                    </head>
                    <body>
                        <h1>Horsham Churches Together</h1>';
        foreach($data as $cat => $posts) {
            $html .= '<h2>'.strtoupper($cat).'</h2>';
            $add_hr = false;
            foreach($posts as $p) {
                if ($add_hr) {
                    $html .= '<hr style="border: 0; border-top: 1px solid #333333; margin: 20px 0;">';
                }
                $clean_content = headings_to_bold($purifier->purify($p->post_content));
                $html .= '<h3>'.$p->post_title.'</h3>';
                $event_start_raw = get_post_meta($p->ID, 'event_start_time', true);
                if ($event_start_raw) {
                    $date = new \DateTime($event_start_raw);
                    $html .= '<i>'. $date->format('l jS F Y \a\t g:ia') . '</i>';
                }
                $html .= $clean_content;
                $add_hr = true;
            }
        }
        $html .= '</body></html>';

        $upload_dir = wp_upload_dir();
        $tmp_path = $upload_dir['basedir'] . '/mpdf-tmp';
        if (!is_dir($tmp_path)) wp_mkdir_p($tmp_path);

        $mpdf = new \Mpdf\Mpdf([
            'tempDir'       => $tmp_path,
            'margin_top'    => 10,
            'margin_bottom' => 10,
            'margin_left'   => 10,
            'margin_right'  => 10,
            'default_font'  => 'dejavusans',
        ]);
        $mpdf->SetFooter('{PAGENO} / {nb}');
        $mpdf->WriteHTML($html);
        $mpdf->Output('Notices-' . $year . '-' . $month . '-' . $day . '.pdf', 'D');
        exit;

        /*
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $font = $fontMetrics->get_font('helvetica');
            $w = $canvas->get_width();
            $h = $canvas->get_height();
            $text = $pageNumber . ' / ' . $pageCount;
            $size = 10;
            $textWidth = $fontMetrics->get_text_width($text, $font, $size);
            $canvas->text(($w - $textWidth) / 2, $h - 28 - $size, $text, $font, $size, [0.6, 0.6, 0.6]);
        });

        $dompdf->stream('Notices-' . $year . '-' . $month . '-' . $day . '.pdf');
        exit;
        */
    }
});