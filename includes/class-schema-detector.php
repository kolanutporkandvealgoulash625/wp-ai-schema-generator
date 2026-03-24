<?php
/**
 * Schema Type Detector
 *
 * Rule-based detection first, AI detection as enhancement.
 * Works WITHOUT AI for basic detection, WITH AI for accuracy.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASG_Schema_Detector {

    /**
     * Supported schema types
     */
    public static function get_supported_types() {
        return array(
            'Article'        => '📝 Article / Blog Post',
            'FAQPage'        => '❓ FAQ Page',
            'HowTo'          => '🔧 How-To / Tutorial',
            'Product'        => '🛍️ Product (WooCommerce)',
            'Recipe'         => '🍳 Recipe',
            'LocalBusiness'  => '📍 Local Business',
            'Course'         => '🎓 Course / Lesson',
            'Event'          => '📅 Event',
            'Review'         => '⭐ Review',
            'VideoObject'    => '🎬 Video',
            'SoftwareApplication' => '💻 Software / App',
        );
    }

    /**
     * Rule-based detection (no AI needed)
     */
    public static function detect_type( $post_id ) {

        $post = get_post( $post_id );
        if ( ! $post ) {
            return 'Article';
        }

        $content    = strtolower( wp_strip_all_tags( $post->post_content ) );
        $title      = strtolower( $post->post_title );
        $post_type  = $post->post_type;

        // WooCommerce Product — definitive
        if ( $post_type === 'product' && class_exists( 'WooCommerce' ) ) {
            return 'Product';
        }

        // LMS Course detection
        $lms_types = array( 'sfwd-courses', 'courses', 'lp_course', 'tutor_course', 'course', 'llms_course' );
        if ( in_array( $post_type, $lms_types, true ) ) {
            return 'Course';
        }

        // LMS Lesson detection — still Course schema
        $lesson_types = array( 'sfwd-lessons', 'lesson', 'lp_lesson', 'tutor_lesson', 'llms_lesson' );
        if ( in_array( $post_type, $lesson_types, true ) ) {
            return 'Course';
        }

        // Event post types
        $event_types = array( 'tribe_events', 'event', 'events', 'mec-events' );
        if ( in_array( $post_type, $event_types, true ) ) {
            return 'Event';
        }

        // Content-based detection
        // FAQ detection — has Q&A patterns
        if ( self::has_faq_pattern( $content, $post->post_content ) ) {
            return 'FAQPage';
        }

        // HowTo detection — step-by-step patterns
        if ( self::has_howto_pattern( $content, $title ) ) {
            return 'HowTo';
        }

        // Recipe detection
        if ( self::has_recipe_pattern( $content, $title ) ) {
            return 'Recipe';
        }

        // Video detection
        if ( self::has_video_embed( $post->post_content ) ) {
            return 'VideoObject';
        }

        // Review detection
        if ( self::has_review_pattern( $content, $title ) ) {
            return 'Review';
        }

        // Software/App detection
        if ( self::has_software_pattern( $content, $title ) ) {
            return 'SoftwareApplication';
        }

        // Local business pages
        if ( self::is_local_business_page( $content, $title, $post->post_name ) ) {
            return 'LocalBusiness';
        }

        // Default — Article
        return 'Article';
    }

    /**
     * FAQ pattern detection
     */
    private static function has_faq_pattern( $content, $raw_content ) {

        // Check for FAQ blocks (Yoast, Rank Math, Gutenberg)
        if ( has_block( 'yoast/faq-block', $raw_content ) || has_block( 'rank-math/faq-block', $raw_content ) ) {
            return true;
        }

        // Check for Q&A heading patterns
        $qa_patterns = array(
            '/\b(frequently asked questions|faqs?)\b/i',
            '/\bq[\s]*[.:]\s/i',
        );
        $qa_count = 0;
        foreach ( $qa_patterns as $pattern ) {
            $qa_count += preg_match_all( $pattern, $content, $m );
        }

        // Check for question-mark headings
        preg_match_all( '/<h[2-4][^>]*>[^<]*\?[^<]*<\/h[2-4]>/i', $raw_content, $q_headings );
        $question_headings = count( $q_headings[0] );

        return ( $qa_count >= 2 || $question_headings >= 3 );
    }

    /**
     * HowTo pattern detection
     */
    private static function has_howto_pattern( $content, $title ) {

        $howto_title_patterns = array(
            '/\bhow to\b/i',
            '/\bstep[- ]by[- ]step\b/i',
            '/\btutorial\b/i',
            '/\bguide to\b/i',
            '/\bdiy\b/i',
        );

        foreach ( $howto_title_patterns as $pattern ) {
            if ( preg_match( $pattern, $title ) ) {
                return true;
            }
        }

        // Check content for numbered steps
        preg_match_all( '/\bstep\s+\d+/i', $content, $steps );
        if ( count( $steps[0] ) >= 3 ) {
            return true;
        }

        return false;
    }

    /**
     * Recipe pattern detection
     */
    private static function has_recipe_pattern( $content, $title ) {

        $recipe_keywords = array(
            'recipe', 'ingredients', 'prep time', 'cook time', 'servings',
            'tablespoon', 'teaspoon', 'cups of', 'preheat oven', 'bake for',
            'minutes at', 'calories per serving',
        );

        $count = 0;
        foreach ( $recipe_keywords as $keyword ) {
            if ( strpos( $content, $keyword ) !== false ) {
                $count++;
            }
        }

        return $count >= 3;
    }

    /**
     * Video embed detection
     */
    private static function has_video_embed( $raw_content ) {

        $video_patterns = array(
            '/youtube\.com\/embed/i',
            '/youtu\.be\//i',
            '/vimeo\.com/i',
            '/\[video\s/i',
            '/<video\s/i',
            '/wp-block-embed.*youtube/i',
            '/wp-block-embed.*vimeo/i',
        );

        foreach ( $video_patterns as $pattern ) {
            if ( preg_match( $pattern, $raw_content ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Review pattern detection
     */
    private static function has_review_pattern( $content, $title ) {

        $review_patterns = array(
            '/\breview\b/i',
            '/\brating[s]?\b/i',
            '/\bpros and cons\b/i',
            '/\bverdict\b/i',
            '/\b\d+(\.\d+)?\s*\/\s*\d+\b/',  // X/10 or X/5 rating
            '/\bstars?\b.*\b(out of|\/)\b/i',
        );

        $count = 0;
        foreach ( $review_patterns as $pattern ) {
            if ( preg_match( $pattern, $title . ' ' . $content ) ) {
                $count++;
            }
        }

        return $count >= 2;
    }

    /**
     * Software/App pattern detection
     */
    private static function has_software_pattern( $content, $title ) {

        $sw_keywords = array(
            'download', 'install', 'plugin', 'app', 'software',
            'version', 'changelog', 'system requirements', 'compatibility',
            'open source', 'free download', 'premium version',
        );

        $count = 0;
        foreach ( $sw_keywords as $kw ) {
            if ( strpos( $content, $kw ) !== false ) {
                $count++;
            }
        }

        return $count >= 4;
    }

    /**
     * Local business page detection
     */
    private static function is_local_business_page( $content, $title, $slug ) {

        $local_slugs = array( 'about', 'about-us', 'contact', 'contact-us', 'location', 'our-store', 'visit-us' );
        if ( in_array( $slug, $local_slugs, true ) ) {
            return true;
        }

        $local_keywords = array(
            'business hours', 'opening hours', 'our address', 'visit us',
            'phone number', 'our location', 'directions', 'get in touch',
        );

        $count = 0;
        foreach ( $local_keywords as $kw ) {
            if ( strpos( $content, $kw ) !== false ) {
                $count++;
            }
        }

        return $count >= 2;
    }

    /**
     * AI-enhanced detection — uses AI to confirm or correct the rule-based detection
     */
    public static function detect_with_ai( $post_id ) {

        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found.' );
        }

        $content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
        $content = wp_trim_words( $content, 500, '...' );

        $rule_based = self::detect_type( $post_id );
        $types      = implode( ', ', array_keys( self::get_supported_types() ) );

        $prompt = "You are a Schema.org structured data expert.

Analyze this web page and determine which Schema.org type BEST fits the content.

PAGE TITLE: \"{$post->post_title}\"
PAGE SLUG: {$post->post_name}
PAGE TYPE: {$post->post_type}

CONTENT:
{$content}

RULE-BASED DETECTION SUGGESTED: {$rule_based}

SUPPORTED TYPES: {$types}

RESPOND WITH ONLY A JSON OBJECT:
{
  \"schema_type\": \"Article\",
  \"confidence\": 95,
  \"reason\": \"Brief explanation why this type fits\"
}

If multiple types could apply, choose the PRIMARY one. Consider:
- Is this teaching how to do something? → HowTo
- Does it have Q&A pairs? → FAQPage
- Is it a recipe with ingredients? → Recipe
- Is it reviewing a product/service? → Review
- Is it a standard blog post? → Article";

        $ai       = new WASG_AI_Provider();
        $response = $ai->send_prompt( $prompt );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $result = WASG_AI_Provider::extract_json( $response );

        if ( is_wp_error( $result ) ) {
            // Fall back to rule-based
            return array(
                'schema_type' => $rule_based,
                'confidence'  => 70,
                'reason'      => 'Rule-based detection (AI response could not be parsed).',
            );
        }

        return $result;
    }
}