<?php
/**
 * Migration script: ACF fields to custom meta
 * Triggered by visiting: /wp-admin/?run_anm_migration=1
 * Remove this file after running.
 */

add_action( 'admin_notices', function() {

    if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['run_anm_migration'] ) ) {
        return;
    }

    $posts = get_posts( [
        'post_type'      => 'post',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );

    $migrated = 0;
    $skipped  = 0;
    $errors   = [];
    $log      = [];

    foreach ( $posts as $post_id ) {
        $title        = get_the_title( $post_id );
        $post_changed = false;

        // --- event_start -> event_start_time ---
        $event_start = get_post_meta( $post_id, 'event_start', true );
        if ( ! empty( $event_start ) ) {
            $timestamp = is_numeric( $event_start )
                ? (int) $event_start
                : strtotime( $event_start );

            if ( $timestamp ) {
                $iso      = gmdate( 'Y-m-d\TH:i:s', $timestamp );
                $existing = get_post_meta( $post_id, 'event_start_time', true );
                if ( $existing === $iso ) {
                    $log[] = "Post $post_id ($title): event_start_time already set to ($iso), skipped";
                } else {
                    $result = update_post_meta( $post_id, 'event_start_time', $iso );
                    if ( $result !== false ) {
                        $log[]        = "Post $post_id ($title): event_start ($event_start) → event_start_time ($iso)";
                        $post_changed = true;
                    } else {
                        $errors[] = "Post $post_id ($title): failed to write event_start_time";
                    }
                }
            } else {
                $errors[] = "Post $post_id ($title): could not parse event_start value '$event_start'";
            }
        }

        // --- expiry -> expiry_date ---
        $expiry = get_post_meta( $post_id, 'expiry', true );
        if ( ! empty( $expiry ) ) {
            $timestamp = is_numeric( $expiry )
                ? (int) $expiry
                : strtotime( $expiry );

            if ( $timestamp ) {
                $date     = gmdate( 'Y-m-d', $timestamp );
                $existing = get_post_meta( $post_id, 'expiry_date', true );
                if ( $existing === $date ) {
                    $log[] = "Post $post_id ($title): expiry_date already set to ($date), skipped";
                } else {
                    $result = update_post_meta( $post_id, 'expiry_date', $date );
                    if ( $result !== false ) {
                        $log[]        = "Post $post_id ($title): expiry ($expiry) → expiry_date ($date)";
                        $post_changed = true;
                    } else {
                        $errors[] = "Post $post_id ($title): failed to write expiry_date";
                    }
                }
            } else {
                $errors[] = "Post $post_id ($title): could not parse expiry value '$expiry'";
            }
        }

        if ( $post_changed ) {
            $migrated++;
        } else {
            $skipped++;
        }
    }
    echo '<div class="notice notice-success"><p><strong>ANM Migration complete.</strong> ';
    echo "Migrated: $migrated, Skipped: $skipped</p>";

    if ( ! empty( $log ) ) {
        echo '<ul style="margin: 4px 0 8px; list-style: disc; padding-left: 20px;">';
        foreach ( $log as $entry ) {
            echo '<li>' . esc_html( $entry ) . '</li>';
        }
        echo '</ul>';
    }

    echo '</div>';

    if ( ! empty( $errors ) ) {
        echo '<div class="notice notice-error"><p><strong>ANM Migration errors:</strong></p>';
        echo '<ul style="margin: 4px 0 8px; list-style: disc; padding-left: 20px;">';
        foreach ( $errors as $error ) {
            echo '<li>' . esc_html( $error ) . '</li>';
        }
        echo '</ul></div>';
    }

} );