<?php

namespace AdvancedNoticesManager;

function get_organized_data_from_ids($id_array) {
    $categories = ['introduction', 'news', 'events', 'jobs', 'prayer', 'volunteering'];
    $data = [];
    foreach ($categories as $cat) {
        $args = [
            'post__in' => $id_array, 'category_name' => $cat,
            'orderby' => ($cat === 'events') ? 'meta_value' : 'title', 'order' => 'ASC'
        ];
        if ($cat === 'events') { $args['meta_key'] = 'event_start'; $args['meta_type'] = 'DATETIME'; }
        $posts = get_posts($args);
        if ($posts) $data[$cat] = $posts;
    }
    return $data;
}

function parse_node_to_word($node, &$section) {
    // Check for Text Nodes (Type 3)
    if ($node->nodeType === 3) {
        $text = $node->nodeValue; // Use nodeValue for text nodes
        if (strlen(trim($text)) > 0) {
            $section->addText($text);
            error_log("ANM Debug - Found Text: " . substr($text, 0, 20));
        }
        return;
    }

    switch ($node->nodeName) {
        case 'p':
        case 'div':
            // Create a new text run for the paragraph to handle nested <strong> or <a>
            $textRun = $section->addTextRun();
            foreach ($node->childNodes as $child) {
                process_inline_tags($child, $textRun);
            }
            break;

        case 'ul':
            foreach ($node->getElementsByTagName('li') as $li) {
                $clean_text = htmlspecialchars($li->nodeValue, ENT_XML1, 'UTF-8');
                $section->addListItem($clean_text, 0, null, \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED);
            }
            break;

        case 'ol':
            foreach ($node->getElementsByTagName('li') as $li) {
                $clean_text = htmlspecialchars($li->nodeValue, ENT_XML1, 'UTF-8');
                $section->addListItem($clean_text, 0, null, \PhpOffice\PhpWord\Style\ListItem::TYPE_NUMBER);
            }
            break;

        case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
            $clean_text = htmlspecialchars($node->nodeValue, ENT_XML1, 'UTF-8');
            $section->addText($clean_text, ['bold' => true]);
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

add_action('template_redirect', function() {
    global $wpdb;
    // Re-check inside the hook to be safe
    $docx_id = $_GET['notice_archive_docx'] ?? null;
    $pdf_id  = $_GET['notice_archive_pdf'] ?? null;
    $html_id = $_GET['notice_archive_html'] ?? null;

    if (!$docx_id && !$pdf_id && !$html_id) {
        return;
    }

    $id = $id = intval($docx_id ?: ($pdf_id ?: $html_id));
    
    $archive = $wpdb->get_row("SELECT * FROM " . ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE . " WHERE id = $id");
    if (!$archive) wp_die('Archive not found.');

    $data = get_organized_data_from_ids(explode(',', $archive->post_ids));

    // --- HTML VIEW ---
    if ($html_id) {
        echo '<html><body style="max-width:800px; margin:auto; font-family:sans-serif;">';
        echo '<div style="background:#eee; padding:10px;"><a href="?download_docx='.$id.'">Save DOCX</a> | <a href="?download_pdf='.$id.'">Save PDF</a></div>';
        foreach ($data as $cat => $posts) {
            echo "<h1>".strtoupper($cat)."</h1>";
            foreach($posts as $p) {
                echo "<h3>{$p->post_title}</h3><div>".wpautop($p->post_content)."</div><hr>";
            }
        }
        echo '</body></html>';
        exit;
    }

    // --- DOCX GENERATION (Using PHPWord) ---
    if ($docx_id) {
        // 1. Clear any accidental whitespace or warnings from other files
        while (ob_get_level()) {
            ob_end_clean();
        }
    
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        
        // Set some basic metadata to help Word validate the file
        $phpWord->getDocInfo()->setCreator('Advanced Notices Manager');
        $phpWord->getDocInfo()->setTitle("Notices - " . $archive->archive_date);
    
        // Add styles    
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 20, 'name' => 'Arial'], ['spaceAfter' => 240]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 14, 'name' => 'Arial'], ['spaceAfter' => 120]);


        $section = $phpWord->addSection();
    
        foreach ($data as $cat => $posts) {
            // Add a Title style for categories
            $section->addTitle(strtoupper($cat), 1);

            foreach ($posts as $p) {

                // 1. Add the Title
                $section->addTitle($p->post_title, 2);

                $dom = new \DOMDocument();
                // Use the UTF-8 Meta Tag trick to fix the Â and â€™ symbols
                $html_string = '<?xml encoding="UTF-8"><html><body>' . $p->post_content . '</body></html>';
                
                // Load with error silencing
                @$dom->loadHTML($html_string, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            
                // Get the body element we just created
                $body = $dom->getElementsByTagName('body')->item(0);
            
                if ($body && $body->hasChildNodes()) {
                    foreach ($body->childNodes as $node) {
                        // Log to see if we are actually hitting nodes now
                        //error_log("ANM Logic - Processing Node: " . $node->nodeName);
                        
                        parse_node_to_word($node, $section);
                    }
                } else {
                    error_log("ANM Error - No nodes found for post: " . $p->post_title);
                    // Fallback: if DOM fails, just add the stripped text
                    $section->addText(strip_tags($p->post_content));
                }
              
                $section->addTextBreak(1); // Space between notices
            }
            // Page break between categories? (Optional)
            //$section->addPageBreak();
        }
    
        // 2. Set strict headers to force the browser to treat this as a binary file
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="Notices-' . $archive->archive_date . '.docx"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
    
        // 3. Clear buffer one last time before output
        if (ob_get_length()) ob_clean();
        flush();
    
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
        exit;
    }

    // --- PDF GENERATION (Using Dompdf) ---
    if ($pdf_id) {
        if (ob_get_length()) ob_end_clean();
        $dompdf = new \Dompdf\Dompdf();
        ob_start();
        echo '<html><style>body{font-family:sans-serif;} h2{background:#f0f0f0; padding:5px;}</style><body>';
        foreach($data as $cat => $posts) {
            echo "<h2>".strtoupper($cat)."</h2>";
            foreach($posts as $p) echo "<h3>{$p->post_title}</h3><div>".wpautop($p->post_content)."</div>";
        }
        echo '</body></html>';
        $dompdf->loadHtml(ob_get_clean());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("Notices-{$archive->archive_date}.pdf");
        exit;
    }
});
