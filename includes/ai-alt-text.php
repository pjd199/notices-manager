<?php

namespace AdvancedNoticesManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MIME types that are valid for use in an HTML <img> tag and supported
 * by the OpenAI Vision API.
 */
const ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/avif',
];

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

add_filter(
    'wp_generate_attachment_metadata',
    __NAMESPACE__ . '\\maybe_generate_alt_text',
    10,
    2
);

// ---------------------------------------------------------------------------
// Hook callback
// ---------------------------------------------------------------------------

/**
 * Called by wp_generate_attachment_metadata.
 * Returns $metadata untouched — the filter contract requires it.
 *
 * @param array $metadata      Attachment metadata array.
 * @param int   $attachment_id
 *
 * @return array
 */
function maybe_generate_alt_text( array $metadata, int $attachment_id ): array {
    $settings = anm_get_settings();
    if (!$settings['ai_alt_text']) {
        return $metadata;
    }
    // Only act on image MIME types that work in <img> tags.
    $mime_type = get_post_mime_type( $attachment_id );
    if ( ! in_array( $mime_type, ALLOWED_MIME_TYPES, true ) ) {
        return $metadata;
    }

    // Skip if alt text has already been set (don't overwrite manual edits).
    $existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
    if ( ! empty( $existing_alt ) ) {
        return $metadata;
    }

    // Resolve the local filesystem path for the image.
    // Prefer the medium thumbnail (smaller payload); fall back to original.
    [ $file_path, $file_mime ] = get_best_image_path( $attachment_id, $metadata, $mime_type );
    if ( empty( $file_path ) ) {
        error_log( "ANM Alt Text: could not resolve a usable file path for attachment {$attachment_id}." );
        return $metadata;
    }

    // Call OpenAI API.
    $alt_text = generate_alt_text_via_openai( $file_path, $file_mime, $attachment_id );
    if ( empty( $alt_text ) ) {
        // Failure already logged inside generate_alt_text_via_openai().
        return $metadata;
    }

    // Save updated image metadata
    update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );

    return $metadata;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Return the best local filesystem path for the image to send to the API,
 * along with its MIME type (the thumbnail may differ from the original).
 * Uses the medium thumbnail if it exists, otherwise the full original.
 *
 * @param int    $attachment_id
 * @param array  $metadata
 * @param string $original_mime  MIME type of the original attachment.
 *
 * @return array{ 0: string|null, 1: string } [ file_path, mime_type ]
 */
function get_best_image_path( int $attachment_id, array $metadata, string $original_mime ): array {
    $upload_dir  = wp_upload_dir();
    $base_dir    = trailingslashit( $upload_dir['basedir'] );
    $upload_subdir = trailingslashit( $upload_dir['subdir'] ); // e.g. /2025/05/

    // Thumbnails are stored alongside the original, so derive the subdir from
    // the original's full path rather than the current upload month.
    $original_path = get_attached_file( $attachment_id );
    if ( ! empty( $original_path ) ) {
        $original_dir = trailingslashit( dirname( $original_path ) );
    } else {
        $original_dir = $base_dir . ltrim( $upload_subdir, '/' );
    }

    // Try medium thumbnail first.
    if ( ! empty( $metadata['sizes']['medium']['file'] ) ) {
        $thumb_path = $original_dir . $metadata['sizes']['medium']['file'];
        if ( is_readable( $thumb_path ) ) {
            // Determine the thumbnail's MIME type (usually same as original).
            $thumb_mime = mime_content_type( $thumb_path ) ?: $original_mime;
            return [ $thumb_path, $thumb_mime ];
        }
    }

    // Fall back to the original file.
    if ( ! empty( $original_path ) && is_readable( $original_path ) ) {
        return [ $original_path, $original_mime ];
    }

    return [ null, $original_mime ];
}

/**
 * Read $file_path from disk, base64-encode it, and send to OpenAI Vision
 * as an inline data URI. Returns the generated alt text, or null on failure.
 *
 * @param string $file_path     Absolute path to the image file.
 * @param string $mime_type     MIME type of the file (e.g. 'image/jpeg').
 * @param int    $attachment_id Used only for error-log context.
 *
 * @return string|null
 */
function generate_alt_text_via_openai( string $file_path, string $mime_type, int $attachment_id ): ?string {
    $settings = anm_get_settings();
    $api_key = $settings['openai_api_key'];

    if ( empty( $api_key ) ) {
        error_log( 'ANM Alt Text: pmc_openai_api_key option is empty — cannot call OpenAI.' );
        return null;
    }

    if ( ! class_exists( \OpenAI\Client::class ) ) {
        error_log( 'ANM Alt Text: OpenAI PHP client class not found. Run: composer require openai-php/client symfony/http-client' );
        return null;
    }

    // Read image bytes — converting AVIF to WebP in memory if needed.
    if ( $mime_type === 'image/avif' ) {
        $raw = convert_to_webp_bytes( $file_path );
        if ( $raw === null ) {
            return null;
        }
        $mime_type = 'image/webp';
    } else {
        $raw = @file_get_contents( $file_path );
        if ( $raw === false ) {
            error_log( "ANM Alt Text: failed to read image file at {$file_path} for attachment {$attachment_id}." );
            return null;
        }
    }

    $base64   = base64_encode( $raw );
    $data_uri = "data:{$mime_type};base64,{$base64}";

    try {
        $http_client = \Symfony\Component\HttpClient\HttpClient::create( [
            'timeout' => 120,
        ] );

        $client = \OpenAI::factory()
            ->withApiKey( $api_key )
            ->withHttpClient( new \Symfony\Component\HttpClient\Psr18Client( $http_client ) )
            ->make();

        $response = $client->chat()->create( [
            'model'      => 'gpt-5.4-mini',
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url' => $data_uri, // Inline base64 — no external HTTP fetch by OpenAI.
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Write a concise, descriptive alt text for this image in plain English. '
                                    . 'The alt text should describe what is visually present for a screen reader user. '
                                    . 'Do not start with "Image of" or "Picture of". '
                                    . 'Return only the alt text string — no punctuation at the end, no quotes, no extra commentary.',
                        ],
                    ],
                ],
            ],
        ] );

        $alt_text = trim( $response->choices[0]->message->content ?? '' );

        if ( empty( $alt_text ) ) {
            error_log( "ANM Alt Text: OpenAI returned an empty response for attachment {$attachment_id}." );
            return null;
        }

        return $alt_text;

    } catch ( \Throwable $e ) {
        error_log( "ANM Alt Text: OpenAI request failed for attachment {$attachment_id}: " . $e->getMessage() );
        return null;
    }
}

/**
 * Convert an AVIF file to WebP bytes in memory using Imagick.
 * Returns the raw WebP binary string, or null if conversion fails.
 *
 * @param string $avif_path Absolute path to the source AVIF file.
 *
 * @return string|null Raw WebP image bytes.
 */
function convert_to_webp_bytes( string $avif_path ): ?string {
    if ( ! class_exists( \Imagick::class ) ) {
        error_log( 'ANM Alt Text: Imagick is not available — cannot convert AVIF to WebP.' );
        return null;
    }

    try {
        $imagick = new \Imagick( $avif_path );
        $imagick->setImageFormat( 'webp' );
        $bytes = $imagick->getImageBlob();
        $imagick->clear();

        return $bytes;
    } catch ( \Throwable $e ) {
        error_log( 'ANM Alt Text: Image conversion failed: ' . $e->getMessage() );
        return null;
    }
}