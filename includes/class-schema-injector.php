<?php
/**
 * Schema Injector — Outputs JSON-LD in the <head> of frontend pages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASG_Schema_Injector {

    public function __construct() {
        $settings = get_option( 'wasg_settings', array() );
        $auto     = isset( $settings['auto_inject'] ) ? $settings['auto_inject'] : true;

        if ( $auto ) {
            add_action( 'wp_head', array( $this, 'inject_schema' ), 1 );
        }
    }

    /**
     * Output the JSON-LD script tag
     */
    public function inject_schema() {

        if ( is_admin() || ! is_singular() ) {
            return;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) return;

        // Check if schema is disabled for this post
        $disabled = get_post_meta( $post_id, '_wasg_schema_disabled', true );
        if ( $disabled ) return;

        // Check if this post type is enabled
        $settings   = get_option( 'wasg_settings', array() );
        $post_types = isset( $settings['post_types'] ) ? $settings['post_types'] : array( 'post', 'page' );
        $post       = get_post( $post_id );

        // Always allow product, course, event types
        $always_allow = array( 'product' );
        if ( ! in_array( $post->post_type, $post_types, true ) && ! in_array( $post->post_type, $always_allow, true ) ) {
            return;
        }

        // Get stored schema or build from detection
        $schema_json = get_post_meta( $post_id, '_wasg_schema_json', true );

        if ( empty( $schema_json ) ) {
            // Auto-detect and build
            $schema_type = WASG_Schema_Detector::detect_type( $post_id );
            $schema_data = WASG_Schema_Builder::build( $post_id, $schema_type );

            if ( empty( $schema_data ) ) return;

            $schema_json = wp_json_encode( $schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        }

        if ( ! empty( $schema_json ) ) {
            echo "\n<!-- AI Schema Markup Generator by Sharanvijay -->\n";
            echo '<script type="application/ld+json">' . "\n";
            echo $schema_json . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</script>' . "\n";
            echo "<!-- /AI Schema Markup Generator -->\n\n";
        }
    }
}