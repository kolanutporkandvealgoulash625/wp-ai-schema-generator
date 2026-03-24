<?php
/**
 * Multi-Provider AI Handler — March 2026 Models
 *
 * Supports: OpenAI, Google Gemini, Anthropic Claude, OpenRouter, Ollama
 * Independent copy — no cross-plugin dependencies.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASG_AI_Provider {

    private $provider;
    private $config;
    private $settings;

    public function __construct() {
        $this->settings = get_option( 'wasg_settings', array() );
        $this->provider = isset( $this->settings['ai_provider'] ) ? $this->settings['ai_provider'] : 'gemini';
        $this->config   = isset( $this->settings[ $this->provider ] ) ? $this->settings[ $this->provider ] : array();
    }

    public function send_prompt( $prompt ) {
        switch ( $this->provider ) {
            case 'openai':      return $this->call_openai( $prompt );
            case 'gemini':      return $this->call_gemini( $prompt );
            case 'claude':      return $this->call_claude( $prompt );
            case 'openrouter':  return $this->call_openrouter( $prompt );
            case 'ollama':      return $this->call_ollama( $prompt );
            default:            return new WP_Error( 'invalid_provider', 'Unknown AI provider.' );
        }
    }

    public function test_connection( $provider, $config ) {
        $this->provider = $provider;
        $this->config   = $config;
        return $this->send_prompt( 'Respond with only the word: connected' );
    }

    // =========================================
    // OPENAI
    // =========================================
    private function call_openai( $prompt ) {
        $api_key = isset( $this->config['api_key'] ) ? $this->config['api_key'] : '';
        $model   = isset( $this->config['model'] ) ? $this->config['model'] : 'gpt-4o-mini';

        if ( empty( $api_key ) ) return new WP_Error( 'no_key', 'OpenAI API key is not configured.' );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 60,
            'headers' => array( 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'model'       => $model,
                'messages'    => array( array( 'role' => 'user', 'content' => $prompt ) ),
                'max_tokens'  => 2000,
                'temperature' => 0.2,
            ) ),
        ) );
        return $this->parse_openai_response( $response );
    }

    private function parse_openai_response( $response ) {
        if ( is_wp_error( $response ) ) return new WP_Error( 'connection_error', 'Connection failed: ' . $response->get_error_message() );
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code !== 200 ) {
            $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'API error (HTTP ' . $code . ')';
            return new WP_Error( 'api_error', $msg );
        }
        if ( isset( $body['choices'][0]['message']['content'] ) ) return trim( $body['choices'][0]['message']['content'] );
        return new WP_Error( 'parse_error', 'Could not parse OpenAI response.' );
    }

    // =========================================
    // GOOGLE GEMINI
    // =========================================
    private function call_gemini( $prompt ) {
        $api_key = isset( $this->config['api_key'] ) ? $this->config['api_key'] : '';
        $model   = isset( $this->config['model'] ) ? $this->config['model'] : 'gemini-2.0-flash';

        if ( empty( $api_key ) ) return new WP_Error( 'no_key', 'Gemini API key is not configured.' );

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
        $response = wp_remote_post( $url, array(
            'timeout' => 60,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
                'generationConfig' => array( 'temperature' => 0.2, 'maxOutputTokens' => 2000 ),
            ) ),
        ) );

        if ( is_wp_error( $response ) ) return new WP_Error( 'connection_error', 'Connection failed: ' . $response->get_error_message() );
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code !== 200 ) {
            $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Gemini error (HTTP ' . $code . ')';
            return new WP_Error( 'api_error', $msg );
        }
        if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) return trim( $body['candidates'][0]['content']['parts'][0]['text'] );
        return new WP_Error( 'parse_error', 'Could not parse Gemini response.' );
    }

    // =========================================
    // ANTHROPIC CLAUDE
    // =========================================
    private function call_claude( $prompt ) {
        $api_key = isset( $this->config['api_key'] ) ? $this->config['api_key'] : '';
        $model   = isset( $this->config['model'] ) ? $this->config['model'] : 'claude-3-5-haiku-20241022';

        if ( empty( $api_key ) ) return new WP_Error( 'no_key', 'Claude API key is not configured.' );

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'timeout' => 60,
            'headers' => array(
                'x-api-key'         => $api_key,
                'anthropic-version'  => '2023-06-01',
                'Content-Type'       => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'      => $model,
                'max_tokens' => 2000,
                'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
            ) ),
        ) );

        if ( is_wp_error( $response ) ) return new WP_Error( 'connection_error', 'Connection failed: ' . $response->get_error_message() );
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code !== 200 ) {
            $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Claude error (HTTP ' . $code . ')';
            return new WP_Error( 'api_error', $msg );
        }
        if ( isset( $body['content'][0]['text'] ) ) return trim( $body['content'][0]['text'] );
        return new WP_Error( 'parse_error', 'Could not parse Claude response.' );
    }

    // =========================================
    // OPENROUTER
    // =========================================
    private function call_openrouter( $prompt ) {
        $api_key = isset( $this->config['api_key'] ) ? $this->config['api_key'] : '';
        $model   = isset( $this->config['model'] ) ? $this->config['model'] : 'meta-llama/llama-3.3-70b-instruct:free';

        if ( empty( $api_key ) ) return new WP_Error( 'no_key', 'OpenRouter API key is not configured.' );

        $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => home_url(),
            ),
            'body' => wp_json_encode( array(
                'model'       => $model,
                'messages'    => array( array( 'role' => 'user', 'content' => $prompt ) ),
                'max_tokens'  => 2000,
                'temperature' => 0.2,
            ) ),
        ) );
        return $this->parse_openai_response( $response );
    }

    // =========================================
    // OLLAMA
    // =========================================
    private function call_ollama( $prompt ) {
        $url   = isset( $this->config['url'] ) ? rtrim( $this->config['url'], '/' ) : 'http://localhost:11434';
        $model = isset( $this->config['model'] ) ? $this->config['model'] : 'llama3.3';

        $response = wp_remote_post( $url . '/api/chat', array(
            'timeout' => 120,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'model'    => $model,
                'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ),
                'stream'   => false,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) return new WP_Error( 'connection_error', 'Cannot reach Ollama at ' . $url . ': ' . $response->get_error_message() );
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code !== 200 ) {
            $msg = isset( $body['error'] ) ? $body['error'] : 'Ollama error (HTTP ' . $code . ')';
            return new WP_Error( 'api_error', $msg );
        }
        if ( isset( $body['message']['content'] ) ) return trim( $body['message']['content'] );
        return new WP_Error( 'parse_error', 'Could not parse Ollama response.' );
    }

    /**
     * Extract JSON from AI response
     */
    public static function extract_json( $text ) {
        // Try code block
        if ( preg_match( '/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $text, $m ) ) {
            $d = json_decode( $m[1], true );
            if ( json_last_error() === JSON_ERROR_NONE ) return $d;
        }
        // Try raw object
        if ( preg_match( '/\{[\s\S]*\}/', $text, $m ) ) {
            $d = json_decode( $m[0], true );
            if ( json_last_error() === JSON_ERROR_NONE ) return $d;
        }
        return new WP_Error( 'json_error', 'Could not extract JSON from AI response.' );
    }

    /**
     * Provider info — UPDATED March 2026
     */
    public static function get_providers_info() {
        return array(
            'openai' => array(
                'name'      => 'OpenAI',
                'free_tier' => false,
                'cost'      => '~$0.005/page',
                'key_url'   => 'https://platform.openai.com/api-keys',
                'models'    => array(
                    'gpt-4o-mini' => 'GPT-4o Mini (Fast & affordable)',
                    'gpt-4o'      => 'GPT-4o (Best quality)',
                    'o3-mini'     => 'o3-mini (Reasoning model)',
                ),
            ),
            'gemini' => array(
                'name'      => 'Google Gemini',
                'free_tier' => true,
                'cost'      => 'Free tier available',
                'key_url'   => 'https://aistudio.google.com/apikey',
                'models'    => array(
                    'gemini-2.0-flash'              => 'Gemini 2.0 Flash (Recommended)',
                    'gemini-2.5-pro-preview-03-25'  => 'Gemini 2.5 Pro (Latest & most capable)',
                ),
            ),
            'claude' => array(
                'name'      => 'Anthropic Claude',
                'free_tier' => false,
                'cost'      => '~$0.005/page',
                'key_url'   => 'https://console.anthropic.com/',
                'models'    => array(
                    'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku (Fast & cheap)',
                    'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet (Latest & best)',
                ),
            ),
            'openrouter' => array(
                'name'      => 'OpenRouter',
                'free_tier' => true,
                'cost'      => 'Free models available',
                'key_url'   => 'https://openrouter.ai/keys',
                'models'    => array(
                    'meta-llama/llama-3.3-70b-instruct:free'   => 'Llama 3.3 70B (FREE)',
                    'google/gemini-2.0-flash-exp:free'         => 'Gemini 2.0 Flash (FREE)',
                    'mistralai/mistral-small-24b-instruct:free' => 'Mistral Small 24B (FREE)',
                    'qwen/qwen-2.5-72b-instruct:free'          => 'Qwen 2.5 72B (FREE)',
                ),
            ),
            'ollama' => array(
                'name'      => 'Ollama (Local)',
                'free_tier' => true,
                'cost'      => 'Completely free',
                'key_url'   => 'https://ollama.com/download',
                'models'    => array(),
            ),
        );
    }
}