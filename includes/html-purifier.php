<?php

namespace AdvancedNoticesManager;

if (!defined('ABSPATH')) exit;

class HeadingToParagraphFilter extends \HTMLPurifier_Filter
{
    public $name = 'HeadingToParagraph';

    public function preFilter($html, $config, $context)
    {
        // Replace opening heading tags with paragraph tags
        $html = preg_replace('/<h[1-6]([^>]*)>/i', '<p$1><b>', $html);
        
        // Replace closing heading tags with paragraph tags
        $html = preg_replace('/<\/h[1-6]>/i', '</b></p>', $html);
        
        return $html;
    }
}

class StripWordPressBlockCommentsFilter extends \HTMLPurifier_Filter
{
    public $name = 'StripWordPressBlockComments';

    public function preFilter($html, $config, $context)
    {
        return preg_replace('/<!--\s*\/?wp:[^>]*-->\s*/i', '', $html);
    }
}

class UnwrapBlockquoteFilter extends \HTMLPurifier_Filter
{
    public $name = 'UnwrapBlockquote';

    public function postFilter($html, $config, $context)
    {
        // Remove opening blockquote tags (with any attributes)
        $html = preg_replace('/<blockquote[^>]*>/i', '', $html);
        // Remove closing blockquote tags
        $html = preg_replace('/<\/blockquote>/i', '', $html);
        return $html;
    }
}

class StripVideoEmbedsFilter extends \HTMLPurifier_Filter
{
    public $name = 'StripVideoEmbeds';

    public function preFilter($html, $config, $context)
    {
        // Matches <figure> elements containing "is-provider-vimeo", "is-provider-youtube", 
        // or "is-type-video" classes and strips them along with their inner contents.
        $pattern = '/<figure[^>]*(is-provider-vimeo|is-provider-youtube|is-type-video)[^>]*>.*?<\/figure>/is';
        
        return preg_replace($pattern, '', $html);
    }
}

function get_html_purifier() {
    static $purifier = null;
    if ($purifier) {
        return $purifier;
    }

    // Create configuration
    $config = \HTMLPurifier_Config::createDefault();

    // Add custom filters
    $config->set('Filter.Custom', [
        new HeadingToParagraphFilter(),
        new StripWordPressBlockCommentsFilter(),
        new UnwrapBlockquoteFilter(),
        new StripVideoEmbedsFilter(),
    ]);

    // Only allow the elements you actually need
    $config->set('HTML.Allowed', 'p,br,strong,b,em,i,ul,ol,li,a[href],sup,sub,h1,h2,h3,h4,h5,h6,blockquote');
    $config->set('HTML.ForbiddenElements', ['img', 'style', 'script', 'iframe', 'video', 'audio', 'figure']);

    // Strip all inline styles and classes
    $config->set('CSS.AllowedProperties', ['id']);
    $config->set('HTML.AllowedAttributes', 'a.href, img.src');
    $config->set('Attr.EnableID', true);

    // Force links to be absolute (prevents relative link weirdness in DOCX/PDF)
    $config->set('URI.Base', get_site_url());
    $config->set('URI.MakeAbsolute', true);
    $config->set('AutoFormat.Linkify', false); // prevents bare URLs being wrapped in extra <a> tags
    
    // Strip empty tags
    $config->set('AutoFormat.RemoveEmpty', true);
    $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true); // also catches &nbsp; only elements

    // Cache dir — use WordPress uploads so it's writable
    $upload_dir = wp_upload_dir();
    $cache_path = $upload_dir['basedir'] . '/htmlpurifier-cache/' . md5_file(__FILE__);
    if (!is_dir($cache_path)) {
        wp_mkdir_p($cache_path);
    }
    $config->set('Cache.SerializerPath', $cache_path);

    $purifier = new \HTMLPurifier($config);
    return $purifier;
}
