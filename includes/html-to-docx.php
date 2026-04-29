<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

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
            $level = (int) substr($node->nodeName, 1);
            if ($node->hasAttribute('id')) {
                $section->addBookmark($node->getAttribute('id'));
            }
            $title = $section->addTitle($clean_text, $level);
            break;
            
        case 'p':
        case 'div':
        case 'span':
            $paragraphStyle = [];

            // Check inline style attribute
            $style = $node->getAttribute('style');
            if ($style && preg_match('/text-align:\s*(left|right|center|justify)/', $style, $matches)) {
                $alignMap = [
                    'left'    => \PhpOffice\PhpWord\SimpleType\Jc::START,
                    'center'  => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                    'right'   => \PhpOffice\PhpWord\SimpleType\Jc::END,
                    'justify' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
                ];
                $paragraphStyle['alignment'] = $alignMap[$matches[1]] ?? \PhpOffice\PhpWord\SimpleType\Jc::START;
            }
            
            $textRun = $section->addTextRun($paragraphStyle);
            foreach ($node->childNodes as $child) {
                process_inline_tags($child, $textRun);
            }
            break;

        case 'hr':
            $section->addText(esc_html('---'), null, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
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

function process_inline_tags($node, &$textRun, $fontStyle = []) {
    if ($node->nodeName === 'strong' || $node->nodeName === 'b') $fontStyle['bold'] = true;
    if ($node->nodeName === 'em' || $node->nodeName === 'i') $fontStyle['italic'] = true;

    // Handle style attribute for bold/italic
    if ($node->nodeType === XML_ELEMENT_NODE) {
        $style = $node->getAttribute('style');
        if ($style) {
            if (preg_match('/font-weight:\s*bold/', $style))   $fontStyle['bold'] = true;
            if (preg_match('/font-style:\s*italic/', $style))  $fontStyle['italic'] = true;
        }
    }
    
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
    } elseif ($node->nodeName === 'br') {
       $textRun->addTextBreak();
    } else {
        // Recurse for nested tags like <strong><em>text</em></strong>
        foreach ($node->childNodes as $child) {
            process_inline_tags($child, $textRun, $fontStyle);
        }
    }
}

function html_to_docx($html) {
    $phpWord = new \PhpOffice\PhpWord\PhpWord();

    $dom = new \DOMDocument();
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $phpWord->getDocInfo()->setCreator(get_bloginfo("name"));
    $phpWord->getDocInfo()->setTitle($dom->getElementsByTagName('title')->item(0)?->textContent ?? '');

    $default_font_size = 12;
    $default_font_name = 'Arial';
    $default_font_color ='333333';
    $headingStyle = array(
        'name' => $default_font_name, 
        'size' => $default_font_size,
        'bold' => true, 
        'color' => $default_font_color
    );
    $paragraphStyle = array(
        'keepNext' => true, 
        'spaceBefore' => 120,
        'spaceAfter' => 120
    );
    $phpWord->addTitleStyle(1, [...$headingStyle, 'size' => 28], [...$paragraphStyle,
        'spaceBefore' => 240,
        'spaceAfter' => 240,
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
    ]);
    $phpWord->addTitleStyle(2, [...$headingStyle, 'size' => 20], [...$paragraphStyle,  
        'shading' => [
            'type'  => \PhpOffice\PhpWord\SimpleType\TblWidth::AUTO,
            'color' => 'auto',
            'fill'  => 'f0f0f0'
        ]
    ]);
    $phpWord->addTitleStyle(3, [...$headingStyle, 'size' => 14], $paragraphStyle);
    $phpWord->addTitleStyle(4, $headingStyle, $paragraphStyle);
    $phpWord->addTitleStyle(5, $headingStyle, $paragraphStyle);
    $phpWord->addTitleStyle(6, $headingStyle, $paragraphStyle);
    
    $phpWord->setDefaultFontName($default_font_name);
    $phpWord->setDefaultFontSize($default_font_size);
    $phpWord->setDefaultFontColor($default_font_color);
    
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
    
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body && $body->hasChildNodes()) {
        foreach ($body->childNodes as $node) {
            parse_node_to_word($node, $section);
        }
    } else {
        $section->addText(strip_tags($clean_content));
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

    return $phpWord;
}