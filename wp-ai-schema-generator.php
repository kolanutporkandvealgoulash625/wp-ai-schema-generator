<?php
/**
 * Plugin Name: AI Schema Markup Generator
 * Plugin URI:  https://github.com/sharanvijaydev/wp-ai-schema-generator
 * Description: AI auto-detects your content type and generates rich JSON-LD schema markup. Supports Article, FAQ, HowTo, Product, Recipe, LocalBusiness, Course, Event & more. Multi-AI provider.
 * Version:     1.0.0
 * Author:      Sharanvijay
 * Author URI:  https://thozhilnutpamtech.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-ai-schema
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WASG_VERSION', '1.0.0' );
define( 'WASG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WASG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WASG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WASG_PLUGIN_DIR . 'includes/class-ai-provider.php';
require_once WASG_PLUGIN_DIR . 'includes/class-schema-detector.php';
require_once WASG_PLUGIN_DIR . 'includes/class-schema-builder.php';
require_once WASG_PLUGIN_DIR . 'includes/class-schema-injector.php';
require_once WASG_PLUGIN_DIR . 'includes/class-admin.php';
require_once WASG_PLUGIN_DIR . 'includes/class-ajax-handler.php';

function wasg_init() {
    new WASG_Admin();
    new WASG_Ajax_Handler();
    new WASG_Schema_Injector();
}
add_action( 'plugins_loaded', 'wasg_init' );

/**
 * Activation — defaults
 */
register_activation_hook( __FILE__, function () {
    $defaults = array(
        'ai_provider'      => 'gemini',
        'auto_inject'      => true,
        'auto_detect'      => true,
        'post_types'       => array( 'post', 'page' ),
        'org_name'         => get_bloginfo( 'name' ),
        'org_url'          => home_url(),
        'org_logo'         => '',
        'default_author'   => '',
        'openai'           => array( 'api_key' => '', 'model' => 'gpt-4o-mini' ),
        'gemini'           => array( 'api_key' => '', 'model' => 'gemini-2.0-flash' ),
        'claude'           => array( 'api_key' => '', 'model' => 'claude-3-5-haiku-20241022' ),
        'openrouter'       => array( 'api_key' => '', 'model' => 'meta-llama/llama-3.3-70b-instruct:free' ),
        'ollama'           => array( 'url' => 'http://localhost:11434', 'model' => 'llama3.3' ),
    );
    add_option( 'wasg_settings', $defaults );
});

/**
 * Deactivation
 */
register_deactivation_hook( __FILE__, function () {
    delete_transient( 'wasg_coverage_report' );
});

/**
 * Settings link on plugins page
 */
add_filter( 'plugin_action_links_' . WASG_PLUGIN_BASENAME, function ( $links ) {
    $url = admin_url( 'admin.php?page=wasg-settings' );
    array_unshift( $links, '<a href="' . $url . '">Settings</a>' );
    return $links;
});