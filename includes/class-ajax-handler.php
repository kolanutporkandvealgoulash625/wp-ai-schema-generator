<?php
/**
 * AJAX Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASG_Ajax_Handler {

    public function __construct() {
        add_action( 'wp_ajax_wasg_detect_schema', array( $this, 'handle_detect' ) );
        add_action( 'wp_ajax_wasg_generate_schema', array( $this, 'handle_generate' ) );
        add_action( 'wp_ajax_wasg_save_schema', array( $this, 'handle_save' ) );
        add_action( 'wp_ajax_wasg_remove_schema', array( $this, 'handle_remove' ) );
        add_action( 'wp_ajax_wasg_scan_coverage', array( $this, 'handle_scan_coverage' ) );
        add_action( 'wp_ajax_wasg_bulk_generate', array( $this, 'handle_bulk_generate' ) );
        add_action( 'wp_ajax_wasg_save_settings', array( $this, 'handle_save_settings' ) );
        add_action( 'wp_ajax_wasg_test_connection', array( $this, 'handle_test_connection' ) );
    }

    /**
     * Detect schema type for a post (rule-based)
     */
    public function handle_detect() {
        check_ajax_referer( 'wasg_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized.' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) wp_send_json_error( 'Invalid post ID.' );

        $type = WASG_Schema_Detector::detect_type( $post_id );
        wp_send_json_success( array( 'schema_type' => $type, 'method' => 'rule_based' ) );
    }

    /**
     * Generate schema using AI
     */
    public function handle_generate() {
        check_ajax_referer( 'wasg_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized.' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) wp_send_json_error( 'Invalid post ID.' );

        $post = get_post( $post_id );
        if ( ! $post || empty( $post->post_content ) ) {
            wp_send_json_error( 'Post has no content. Save the post first.' );
        }

        // Step 1: Detect type with AI
        $detection = WASG_Schema_Detector::detect_with_ai( $post_id );

        if ( is_wp_error( $detection ) ) {
            // Fallback to rule-based
            $schema_type = WASG_Schema_Detector::detect_type( $post_id );
            $confidence  = 70;
            $reason      = 'Rule-based detection (AI unavailable: ' . $detection->get_error_message() . ')';
        } else {
            $schema_type = isset( $detection['schema_type'] ) ? $detection['schema_type'] : 'Article';
            $confidence  = isset( $detection['confidence'] ) ? $detection['confidence'] : 80;
            $reason      = isset( $detection['reason'] ) ? $detection['reason'] : '';
        }

        // Validate type
        $valid_types = array_keys( WASG_Schema_Detector::get_supported_types() );
        if ( ! in_array( $schema_type, $valid_types, true ) ) {
            $schema_type = 'Article';
        }

        // Step 2: For complex types, extract structured data with AI
        $extracted_data = array();
        if ( in_array( $schema_type, array( 'FAQPage', 'HowTo', 'Recipe' ), true ) ) {
            $extracted_data = $this->extract_structured_data( $post, $schema_type );
        }

        // Step 3: Build schema
        $schema = WASG_Schema_Builder::build( $post_id, $schema_type );
        if ( empty( $schema ) ) {
            wp_send_json_error( 'Could not build schema for this content type.' );
        }

        // Merge extracted AI data into schema
        if ( $schema_type === 'FAQPage' && ! empty( $extracted_data['faq'] ) ) {
            $schema['mainEntity'] = array();
            foreach ( $extracted_data['faq'] as $qa ) {
                $schema['mainEntity'][] = array(
                    '@type'          => 'Question',
                    'name'           => $qa['question'],
                    'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa['answer'] ),
                );
            }
            update_post_meta( $post_id, '_wasg_faq_data', $extracted_data['faq'] );
        }

        if ( $schema_type === 'HowTo' && ! empty( $extracted_data['steps'] ) ) {
            $schema['step'] = array();
            foreach ( $extracted_data['steps'] as $i => $step ) {
                $schema['step'][] = array(
                    '@type'    => 'HowToStep',
                    'position' => $i + 1,
                    'name'     => isset( $step['name'] ) ? $step['name'] : 'Step ' . ( $i + 1 ),
                    'text'     => $step['text'],
                );
            }
            update_post_meta( $post_id, '_wasg_howto_steps', $extracted_data['steps'] );

            if ( isset( $extracted_data['totalTime'] ) ) $schema['totalTime'] = $extracted_data['totalTime'];
        }

        if ( $schema_type === 'Recipe' && ! empty( $extracted_data ) ) {
            $recipe_fields = array( 'prepTime', 'cookTime', 'totalTime', 'recipeYield', 'recipeIngredient' );
            foreach ( $recipe_fields as $f ) {
                if ( isset( $extracted_data[ $f ] ) ) $schema[ $f ] = $extracted_data[ $f ];
            }
            if ( isset( $extracted_data['recipeInstructions'] ) ) {
                $schema['recipeInstructions'] = array();
                foreach ( $extracted_data['recipeInstructions'] as $step ) {
                    $schema['recipeInstructions'][] = array( '@type' => 'HowToStep', 'text' => $step );
                }
            }
            update_post_meta( $post_id, '_wasg_recipe_data', $extracted_data );
        }

        $schema_json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

        wp_send_json_success( array(
            'schema_type' => $schema_type,
            'confidence'  => $confidence,
            'reason'      => $reason,
            'schema_json' => $schema_json,
            'schema_data' => $schema,
        ) );
    }

    /**
     * Extract structured data from content using AI
     */
    private function extract_structured_data( $post, $schema_type ) {

        $content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
        $content = wp_trim_words( $content, 1500, '...' );

        $prompt = '';

        if ( $schema_type === 'FAQPage' ) {
            $prompt = "Extract all question-and-answer pairs from this content.

CONTENT:
{$content}

RESPOND WITH ONLY A JSON OBJECT:
{
  \"faq\": [
    {\"question\": \"The question?\", \"answer\": \"The answer text.\"},
    {\"question\": \"Another question?\", \"answer\": \"Another answer.\"}
  ]
}

Rules: Extract actual Q&A from the content. Do NOT invent questions. Keep answers concise (1-3 sentences). No markdown.";
        }

        if ( $schema_type === 'HowTo' ) {
            $prompt = "Extract the step-by-step instructions from this how-to content.

CONTENT:
{$content}

RESPOND WITH ONLY A JSON OBJECT:
{
  \"steps\": [
    {\"name\": \"Step title\", \"text\": \"Detailed step description.\"},
    {\"name\": \"Next step\", \"text\": \"Description.\"}
  ],
  \"totalTime\": \"PT30M\"
}

Rules: Extract actual steps from content. name = short step title. text = step details. totalTime in ISO 8601 duration (PT#H#M). No markdown.";
        }

        if ( $schema_type === 'Recipe' ) {
            $prompt = "Extract recipe data from this content.

CONTENT:
{$content}

RESPOND WITH ONLY A JSON OBJECT:
{
  \"prepTime\": \"PT15M\",
  \"cookTime\": \"PT30M\",
  \"totalTime\": \"PT45M\",
  \"recipeYield\": \"4 servings\",
  \"recipeIngredient\": [\"1 cup flour\", \"2 eggs\"],
  \"recipeInstructions\": [\"Step 1 text\", \"Step 2 text\"]
}

Rules: Extract ONLY data present in the content. Times in ISO 8601 (PT#H#M). No markdown.";
        }

        if ( empty( $prompt ) ) return array();

        $ai       = new WASG_AI_Provider();
        $response = $ai->send_prompt( $prompt );

        if ( is_wp_error( $response ) ) return array();

        $data = WASG_AI_Provider::extract_json( $response );
        if ( is_wp_error( $data ) ) return array();

        return $data;
    }

    /**
     * Save schema to post meta
     */
    public function handle_save() {
        check_ajax_referer( 'wasg_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized.' );

        $post_id     = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $schema_type = isset( $_POST['schema_type'] ) ? sanitize_text_field( wp_unslash( $_POST['schema_type'] ) ) : '';
        $schema_json = isset( $_POST['schema_json'] ) ? wp_unslash( $_POST['schema_json'] ) : ''; // phpcs:ignore

        if ( ! $post_id || empty( $schema_json ) ) {
            wp_send_json_error( 'Missing required data.' );
        }

        // Validate JSON
        $decoded = json_decode( $schema_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( 'Invalid JSON schema. Please fix syntax errors.' );
        }

        // Re-encode cleanly
        $clean_json = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

        update_post_meta( $post_id, '_wasg_schema_type', $schema_type );
        update_post_meta( $post_id, '_wasg_schema_json', $clean_json );
        delete_post_meta( $post_id, '_wasg_schema_disabled' );

        wp_send_json_success( 'Schema saved and active on this page!' );
    }

    /**
     * Remove / disable schema for a post
     */
    public function handle_remove() {
        check_ajax_referer( 'wasg_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized.' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) wp_send_json_error( 'Invalid post ID.' );

        delete_post_meta( $post_id, '_wasg_schema_type' );
        delete_post_meta( $post_id, '_wasg_schema_json' );
        update_post_meta( $post_id, '_wasg_schema_disabled', true );

        wp_send_json_success( 'Schema removed from this page.' );
    }

    /**
     * Scan coverage across all content
     */
    public function handle_scan_coverage() {
        check_ajax_referer( 'wasg_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $settings   = get_option( 'wasg_settings', array() );
        $post_types = isset( $settings['post_types'] ) ? $settings['post_types'] : array( 'post', 'page' );

        if ( class_exists( 'WooCommerce' ) && ! in_array( 'product', $post_types, true ) ) {
            $post_types[] = 'product';
        }

        $posts = get_posts( array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        $results = array(
            'total'     => count( $posts ),
            'has_schema' => array(),
            'no_schema'  => array(),
            'type_breakdown' => array(),
        );

        foreach ( $posts as $pid ) {
            $stored_json = get_post_meta( $pid, '_wasg_schema_json', true );
            $stored_type = get_post_meta( $pid, '_wasg_schema_type', true );
            $disabled    = get_post_meta( $pid, '_wasg_schema_disabled', true );

            $detected_type = WASG_Schema_Detector::detect_type( $pid );

            $item = array(
                'id'            => $pid,
                'title'         => get_the_title( $pid ),
                'url'           => get_permalink( $pid ),
                'edit_url'      => get_edit_post_link( $pid, 'raw' ),
                'post_type'     => get_post_type( $pid ),
                'detected_type' => $detected_type,
                'stored_type'   => $stored_type,
                'has_schema'    => ! empty( $stored_json ) && ! $disabled,
                'disabled'      => (bool) $disabled,
            );

            if ( ! empty( $stored_json ) && ! $disabled ) {
                $results['has_schema'][] = $item;
                $type_key = $stored_type ?: $detected_type;
            } else {
                $results['no_schema'][] = $item;
                $type_key = $detected_type;
            }

            if ( ! isset( $results['type_breakdown'][ $type_key ] ) ) {
                $results['type_breakdown'][ $type_key ] = 0;
            }
            $results['type_breakdown'][ $type_key ]++;
        }

        wp_send_json_success( $results );
    }

    /**
     * Bulk generate schema for a single post (called sequentially from JS)
     */
    public function handle_bulk_generate() {
        check_ajax_referer( 'wasg_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) wp_send_json_error( 'Invalid post ID.' );

        // Detect type
        $schema_type = WASG_Schema_Detector::detect_type( $post_id );

        // Build schema
        $schema = WASG_Schema_Builder::build( $post_id, $schema_type );
        if ( empty( $schema ) ) wp_send_json_error( 'Could not build schema.' );

        // For FAQ, HowTo, Recipe — try AI extraction
        $settings = get_option( 'wasg_settings', array() );
        $provider = isset( $settings['ai_provider'] ) ? $settings['ai_provider'] : 'gemini';
        $has_ai   = false;

        if ( $provider === 'ollama' ) {
            $has_ai = ! empty( $settings['ollama']['url'] );
        } else {
            $has_ai = ! empty( $settings[ $provider ]['api_key'] );
        }

        // If AI is configured and it's a complex type, enhance with AI
        if ( $has_ai && in_array( $schema_type, array( 'FAQPage', 'HowTo', 'Recipe' ), true ) ) {
            $_POST['post_id'] = $post_id; // Reuse generate handler internally
            // We'll just build without AI extraction for bulk to keep it fast
            // AI extraction can be done per-post from the meta box
        }

        $schema_json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

        // Save
        update_post_meta( $post_id, '_wasg_schema_type', $schema_type );
        update_post_meta( $post_id, '_wasg_schema_json', $schema_json );
        delete_post_meta( $post_id, '_wasg_schema_disabled' );

        wp_send_json_success( array(
            'post_id'     => $post_id,
            'schema_type' => $schema_type,
            'saved'       => true,
        ) );
    }

    /**
     * Save settings
     */
    public function handle_save_settings() {
        check_ajax_referer( 'wasg_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $current  = get_option( 'wasg_settings', array() );
        $provider = isset( $_POST['ai_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_provider'] ) ) : 'gemini';

        $allowed = array( 'openai', 'gemini', 'claude', 'openrouter', 'ollama' );
        if ( ! in_array( $provider, $allowed, true ) ) $provider = 'gemini';

        $updated = array(
            'ai_provider'    => $provider,
            'auto_inject'    => isset( $_POST['auto_inject'] ) && $_POST['auto_inject'] === '1',
            'auto_detect'    => isset( $_POST['auto_detect'] ) && $_POST['auto_detect'] === '1',
            'post_types'     => isset( $_POST['post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) ) : array( 'post', 'page' ),
            'org_name'       => isset( $_POST['org_name'] ) ? sanitize_text_field( wp_unslash( $_POST['org_name'] ) ) : '',
            'org_url'        => isset( $_POST['org_url'] ) ? esc_url_raw( wp_unslash( $_POST['org_url'] ) ) : '',
            'org_logo'       => isset( $_POST['org_logo'] ) ? esc_url_raw( wp_unslash( $_POST['org_logo'] ) ) : '',
            'default_author' => isset( $_POST['default_author'] ) ? sanitize_text_field( wp_unslash( $_POST['default_author'] ) ) : '',
        );

        foreach ( $allowed as $p ) {
            if ( $p === 'ollama' ) {
                $updated[ $p ] = array(
                    'url'   => isset( $_POST['ollama_url'] ) ? esc_url_raw( wp_unslash( $_POST['ollama_url'] ) ) : ( $current['ollama']['url'] ?? 'http://localhost:11434' ),
                    'model' => isset( $_POST['ollama_model'] ) ? sanitize_text_field( wp_unslash( $_POST['ollama_model'] ) ) : ( $current['ollama']['model'] ?? 'llama3.3' ),
                );
            } else {
                $updated[ $p ] = array(
                    'api_key' => isset( $_POST[ $p . '_api_key' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $p . '_api_key' ] ) ) : ( $current[ $p ]['api_key'] ?? '' ),
                    'model'   => isset( $_POST[ $p . '_model' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $p . '_model' ] ) ) : ( $current[ $p ]['model'] ?? '' ),
                );
            }
        }

        update_option( 'wasg_settings', $updated );
        wp_send_json_success( 'Settings saved successfully.' );
    }

    /**
     * Test connection
     */
    public function handle_test_connection() {
        check_ajax_referer( 'wasg_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized.' );

        $provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';

        if ( $provider === 'ollama' ) {
            $config = array(
                'url'   => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : 'http://localhost:11434',
                'model' => isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : 'llama3.3',
            );
        } else {
            $config = array(
                'api_key' => isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '',
                'model'   => isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '',
            );
        }

        $ai     = new WASG_AI_Provider();
        $result = $ai->test_connection( $provider, $config );

        if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );
        wp_send_json_success( 'Connection successful!' );
    }
}