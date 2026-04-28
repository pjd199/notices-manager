<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

add_action('template_redirect', function() {
    global $wpdb;
    
    // Check the requested format
    if (!isset($_GET['plain_text'])) {
        return;
    }
    $format = $_GET['plain_text'];
    if (!in_array($format ?? '', ['pdf', 'docx', 'html'])) {
        return;
    }

    // Capture date components
    $year  = isset($_GET['year']) && is_numeric($_GET['year'])  ? intval($_GET['year'])  : null;
    $month = isset($_GET['month']) &&is_numeric($_GET['month']) ? intval($_GET['month']) : null;
    $day   = isset($_GET['day']) &&is_numeric($_GET['day'])   ? intval($_GET['day'])   : null;

    if (!$year || !$month || !$day) {
        list($year, $month, $day) = explode('-', date('Y-m-d'));
    }

    $toc = !in_array($_GET['toc'] ?? 'true', ['false', '0']);

    $html = plain_text_notices($year, $month, $day, $toc);

    while (ob_get_level()) {
        ob_end_clean();
    }

    switch ($format) {
        case 'html':
            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
            exit;

        case 'docx':
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="Notices-' . $year . '-' . $month . '-' . $day . '.docx"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');

            $phpWord = html_to_docx($html);
            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save('php://output');
            exit;

        case 'pdf':
            $html = preg_replace('/font-size: \d+(px|pt);/', 'font-size: 11pt;', $html);

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
    }
}, 1);