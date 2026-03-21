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

add_action('template_redirect', function() {
    if (!isset($_GET['download_docx']) && !isset($_GET['download_pdf']) && !isset($_GET['view_notice_archive'])) return;

    $id = intval($_GET['download_docx'] ?: $_GET['download_pdf'] ?: $_GET['view_notice_archive']);
    $archive = $wpdb->get_row("SELECT * FROM " . ADVANCED_NOTICES_MANAGER_ARCHIVE_TABLE . " WHERE id = $id");
    if (!$archive) wp_die('Archive not found.');

    $data = get_organized_data_from_ids(explode(',', $archive->post_ids));

    // --- HTML VIEW ---
    if (isset($_GET['view_notice_archive'])) {
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
    if (isset($_GET['download_docx'])) {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        foreach ($data as $cat => $posts) {
            $section->addTitle(strtoupper($cat), 1);
            foreach ($posts as $p) {
                $section->addText($p->post_title, ['bold' => true, 'size' => 14]);
                $section->addText(strip_tags($p->post_content));
            }
        }
        header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        header('Content-Disposition: attachment; filename="Notices-'.$archive->archive_date.'.docx"');
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
        exit;
    }

    // --- PDF GENERATION (Using Dompdf) ---
    if (isset($_GET['download_pdf'])) {
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