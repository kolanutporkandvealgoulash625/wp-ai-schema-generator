<?php
/**
 * Admin — Menus, Pages, Meta Box, Assets
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASG_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
    }

    public function register_menus() {
        add_menu_page( 'AI Schema', 'AI Schema', 'manage_options', 'wasg-coverage', array( $this, 'render_coverage' ), 'dashicons-schema', 32 );
        add_submenu_page( 'wasg-coverage', 'Coverage Report', 'Coverage Report', 'manage_options', 'wasg-coverage', array( $this, 'render_coverage' ) );
        add_submenu_page( 'wasg-coverage', 'Settings', 'Settings', 'manage_options', 'wasg-settings', array( $this, 'render_settings' ) );
    }

    public function enqueue_assets( $hook ) {
        $is_ours  = ( strpos( $hook, 'wasg' ) !== false );
        $is_edit  = ( $hook === 'post.php' || $hook === 'post-new.php' );

        if ( ! $is_ours && ! $is_edit ) return;

        wp_enqueue_style( 'wasg-admin-css', WASG_PLUGIN_URL . 'admin/css/admin-style.css', array(), WASG_VERSION );
        wp_enqueue_script( 'wasg-admin-js', WASG_PLUGIN_URL . 'admin/js/admin-script.js', array( 'jquery' ), WASG_VERSION, true );
        wp_localize_script( 'wasg-admin-js', 'wasg_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wasg_nonce' ),
        ) );
    }

    public function register_meta_box() {
        $settings   = get_option( 'wasg_settings', array() );
        $post_types = isset( $settings['post_types'] ) ? $settings['post_types'] : array( 'post', 'page' );

        // Always add to products
        if ( class_exists( 'WooCommerce' ) && ! in_array( 'product', $post_types, true ) ) {
            $post_types[] = 'product';
        }

        foreach ( $post_types as $pt ) {
            add_meta_box( 'wasg_schema_box', '📋 AI Schema Markup', array( $this, 'render_meta_box' ), $pt, 'side', 'default' );
        }
    }

    public function render_meta_box( $post ) {
        include WASG_PLUGIN_DIR . 'admin/views/meta-box.php';
    }
    public function render_coverage() {
        include WASG_PLUGIN_DIR . 'admin/views/coverage-report.php';
    }
    public function render_settings() {
        include WASG_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
}