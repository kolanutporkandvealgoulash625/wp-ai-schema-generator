<?php
/**
 * Schema Builder — Generates JSON-LD structured data
 *
 * Builds valid Schema.org markup for each content type.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASG_Schema_Builder {

    /**
     * Build schema for a post based on detected or stored type
     */
    public static function build( $post_id, $schema_type = '' ) {

        $post = get_post( $post_id );
        if ( ! $post ) return null;

        if ( empty( $schema_type ) ) {
            $schema_type = get_post_meta( $post_id, '_wasg_schema_type', true );
        }
        if ( empty( $schema_type ) ) {
            $schema_type = WASG_Schema_Detector::detect_type( $post_id );
        }

        $settings = get_option( 'wasg_settings', array() );

        switch ( $schema_type ) {
            case 'Article':       return self::build_article( $post, $settings );
            case 'FAQPage':       return self::build_faq( $post, $settings );
            case 'HowTo':         return self::build_howto( $post, $settings );
            case 'Product':       return self::build_product( $post, $settings );
            case 'Recipe':        return self::build_recipe( $post, $settings );
            case 'LocalBusiness': return self::build_local_business( $post, $settings );
            case 'Course':        return self::build_course( $post, $settings );
            case 'Event':         return self::build_event( $post, $settings );
            case 'Review':        return self::build_review( $post, $settings );
            case 'VideoObject':   return self::build_video( $post, $settings );
            case 'SoftwareApplication': return self::build_software( $post, $settings );
            default:              return self::build_article( $post, $settings );
        }
    }

    /**
     * Article Schema
     */
    private static function build_article( $post, $settings ) {

        $schema = array(
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => $post->post_title,
            'description'      => self::get_excerpt( $post ),
            'url'              => get_permalink( $post->ID ),
            'datePublished'    => get_the_date( 'c', $post ),
            'dateModified'     => get_the_modified_date( 'c', $post ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => get_permalink( $post->ID ),
            ),
            'author'           => self::get_author_schema( $post ),
            'publisher'        => self::get_publisher_schema( $settings ),
        );

        $image = self::get_featured_image( $post->ID );
        if ( $image ) {
            $schema['image'] = $image;
        }

        $word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
        $schema['wordCount'] = $word_count;

        return $schema;
    }

    /**
     * FAQ Schema
     */
    private static function build_faq( $post, $settings ) {

        $schema = array(
            '@context'         => 'https://schema.org',
            '@type'            => 'FAQPage',
            'mainEntity'       => array(),
            'url'              => get_permalink( $post->ID ),
            'name'             => $post->post_title,
            'dateModified'     => get_the_modified_date( 'c', $post ),
        );

        // Try to extract Q&A from AI-stored data first
        $stored_faq = get_post_meta( $post->ID, '_wasg_faq_data', true );
        if ( ! empty( $stored_faq ) && is_array( $stored_faq ) ) {
            foreach ( $stored_faq as $qa ) {
                $schema['mainEntity'][] = array(
                    '@type'          => 'Question',
                    'name'           => $qa['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text'  => $qa['answer'],
                    ),
                );
            }
        }

        return $schema;
    }

    /**
     * HowTo Schema
     */
    private static function build_howto( $post, $settings ) {

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'HowTo',
            'name'          => $post->post_title,
            'description'   => self::get_excerpt( $post ),
            'url'           => get_permalink( $post->ID ),
            'datePublished' => get_the_date( 'c', $post ),
            'step'          => array(),
        );

        $image = self::get_featured_image( $post->ID );
        if ( $image ) $schema['image'] = $image;

        // Try stored steps
        $stored_steps = get_post_meta( $post->ID, '_wasg_howto_steps', true );
        if ( ! empty( $stored_steps ) && is_array( $stored_steps ) ) {
            foreach ( $stored_steps as $i => $step ) {
                $schema['step'][] = array(
                    '@type'    => 'HowToStep',
                    'position' => $i + 1,
                    'name'     => isset( $step['name'] ) ? $step['name'] : 'Step ' . ( $i + 1 ),
                    'text'     => $step['text'],
                );
            }
        }

        return $schema;
    }

    /**
     * Product Schema (WooCommerce)
     */
    private static function build_product( $post, $settings ) {

        if ( ! class_exists( 'WooCommerce' ) ) {
            return self::build_article( $post, $settings );
        }

        $product = wc_get_product( $post->ID );
        if ( ! $product ) return self::build_article( $post, $settings );

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $product->get_name(),
            'description' => self::get_excerpt( $post ),
            'url'         => get_permalink( $post->ID ),
            'sku'         => $product->get_sku(),
            'offers'      => array(
                '@type'           => 'Offer',
                'price'           => $product->get_price(),
                'priceCurrency'   => get_woocommerce_currency(),
                'availability'    => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url'             => get_permalink( $post->ID ),
            ),
        );

        $image = self::get_featured_image( $post->ID );
        if ( $image ) $schema['image'] = $image;

        // Brand from attribute or term
        $brand = $product->get_attribute( 'brand' );
        if ( ! empty( $brand ) ) {
            $schema['brand'] = array( '@type' => 'Brand', 'name' => $brand );
        }

        // Aggregate rating
        if ( $product->get_review_count() > 0 ) {
            $schema['aggregateRating'] = array(
                '@type'       => 'AggregateRating',
                'ratingValue' => $product->get_average_rating(),
                'reviewCount' => $product->get_review_count(),
            );
        }

        return $schema;
    }

    /**
     * Recipe Schema
     */
    private static function build_recipe( $post, $settings ) {

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'Recipe',
            'name'          => $post->post_title,
            'description'   => self::get_excerpt( $post ),
            'url'           => get_permalink( $post->ID ),
            'datePublished' => get_the_date( 'c', $post ),
            'author'        => self::get_author_schema( $post ),
        );

        $image = self::get_featured_image( $post->ID );
        if ( $image ) $schema['image'] = $image;

        // AI-stored recipe data
        $recipe_data = get_post_meta( $post->ID, '_wasg_recipe_data', true );
        if ( ! empty( $recipe_data ) && is_array( $recipe_data ) ) {
            if ( isset( $recipe_data['prepTime'] ) )      $schema['prepTime']     = $recipe_data['prepTime'];
            if ( isset( $recipe_data['cookTime'] ) )       $schema['cookTime']     = $recipe_data['cookTime'];
            if ( isset( $recipe_data['totalTime'] ) )      $schema['totalTime']    = $recipe_data['totalTime'];
            if ( isset( $recipe_data['recipeYield'] ) )    $schema['recipeYield']  = $recipe_data['recipeYield'];
            if ( isset( $recipe_data['recipeIngredient'] ) ) $schema['recipeIngredient'] = $recipe_data['recipeIngredient'];
            if ( isset( $recipe_data['recipeInstructions'] ) ) {
                $schema['recipeInstructions'] = array();
                foreach ( $recipe_data['recipeInstructions'] as $step ) {
                    $schema['recipeInstructions'][] = array(
                        '@type' => 'HowToStep',
                        'text'  => $step,
                    );
                }
            }
        }

        return $schema;
    }

    /**
     * LocalBusiness Schema
     */
    private static function build_local_business( $post, $settings ) {

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'LocalBusiness',
            'name'        => isset( $settings['org_name'] ) ? $settings['org_name'] : get_bloginfo( 'name' ),
            'url'         => isset( $settings['org_url'] ) ? $settings['org_url'] : home_url(),
            'description' => self::get_excerpt( $post ),
        );

        $logo = isset( $settings['org_logo'] ) ? $settings['org_logo'] : '';
        if ( ! empty( $logo ) ) $schema['logo'] = $logo;

        // AI-stored local data
        $local_data = get_post_meta( $post->ID, '_wasg_local_data', true );
        if ( ! empty( $local_data ) && is_array( $local_data ) ) {
            if ( isset( $local_data['telephone'] ) )     $schema['telephone'] = $local_data['telephone'];
            if ( isset( $local_data['email'] ) )         $schema['email']     = $local_data['email'];
            if ( isset( $local_data['address'] ) )       $schema['address']   = $local_data['address'];
            if ( isset( $local_data['openingHours'] ) )  $schema['openingHoursSpecification'] = $local_data['openingHours'];
        }

        return $schema;
    }

    /**
     * Course Schema
     */
    private static function build_course( $post, $settings ) {

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'Course',
            'name'          => $post->post_title,
            'description'   => self::get_excerpt( $post ),
            'url'           => get_permalink( $post->ID ),
            'datePublished' => get_the_date( 'c', $post ),
            'provider'      => array(
                '@type' => 'Organization',
                'name'  => isset( $settings['org_name'] ) ? $settings['org_name'] : get_bloginfo( 'name' ),
                'url'   => isset( $settings['org_url'] ) ? $settings['org_url'] : home_url(),
            ),
        );

        $image = self::get_featured_image( $post->ID );
        if ( $image ) $schema['image'] = $image;

        return $schema;
    }

    /**
     * Event Schema
     */
    private static function build_event( $post, $settings ) {

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'Event',
            'name'          => $post->post_title,
            'description'   => self::get_excerpt( $post ),
            'url'           => get_permalink( $post->ID ),
            'organizer'     => array(
                '@type' => 'Organization',
                'name'  => isset( $settings['org_name'] ) ? $settings['org_name'] : get_bloginfo( 'name' ),
            ),
        );

        $image = self::get_featured_image( $post->ID );
        if ( $image ) $schema['image'] = $image;

        $event_data = get_post_meta( $post->ID, '_wasg_event_data', true );
        if ( ! empty( $event_data ) && is_array( $event_data ) ) {
            if ( isset( $event_data['startDate'] ) ) $schema['startDate'] = $event_data['startDate'];
            if ( isset( $event_data['endDate'] ) )   $schema['endDate']   = $event_data['endDate'];
            if ( isset( $event_data['location'] ) )  $schema['location']  = $event_data['location'];
        }

        return $schema;
    }

    /**
     * Review Schema
     */
    private static function build_review( $post, $settings ) {

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'Review',
            'name'          => $post->post_title,
            'description'   => self::get_excerpt( $post ),
            'url'           => get_permalink( $post->ID ),
            'datePublished' => get_the_date( 'c', $post ),
            'author'        => self::get_author_schema( $post ),
        );

        $review_data = get_post_meta( $post->ID, '_wasg_review_data', true );
        if ( ! empty( $review_data ) && is_array( $review_data ) ) {
            if ( isset( $review_data['itemReviewed'] ) ) $schema['itemReviewed'] = $review_data['itemReviewed'];
            if ( isset( $review_data['reviewRating'] ) ) $schema['reviewRating'] = $review_data['reviewRating'];
        }

        return $schema;
    }

    /**
     * Video Schema
     */
    private static function build_video( $post, $settings ) {

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'VideoObject',
            'name'          => $post->post_title,
            'description'   => self::get_excerpt( $post ),
            'uploadDate'    => get_the_date( 'c', $post ),
            'url'           => get_permalink( $post->ID ),
        );

        $image = self::get_featured_image( $post->ID );
        if ( $image ) $schema['thumbnailUrl'] = $image;

        // Try to find embedded video URL
        preg_match( '/(?:youtube\.com\/embed\/|youtu\.be\/|youtube\.com\/watch\?v=)([\w-]+)/', $post->post_content, $yt );
        if ( ! empty( $yt[1] ) ) {
            $schema['embedUrl']    = 'https://www.youtube.com/embed/' . $yt[1];
            $schema['contentUrl']  = 'https://www.youtube.com/watch?v=' . $yt[1];
        }

        return $schema;
    }

    /**
     * Software Schema
     */
    private static function build_software( $post, $settings ) {

        $schema = array(
            '@context'           => 'https://schema.org',
            '@type'              => 'SoftwareApplication',
            'name'               => $post->post_title,
            'description'        => self::get_excerpt( $post ),
            'url'                => get_permalink( $post->ID ),
            'applicationCategory' => 'WebApplication',
        );

        $image = self::get_featured_image( $post->ID );
        if ( $image ) $schema['image'] = $image;

        return $schema;
    }

    // =========================================
    // HELPERS
    // =========================================

    private static function get_excerpt( $post, $words = 30 ) {
        if ( ! empty( $post->post_excerpt ) ) {
            return wp_strip_all_tags( $post->post_excerpt );
        }
        return wp_trim_words( wp_strip_all_tags( $post->post_content ), $words, '...' );
    }

    private static function get_featured_image( $post_id ) {
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id ) return '';

        $img_data = wp_get_attachment_image_src( $thumb_id, 'full' );
        if ( ! $img_data ) return '';

        return array(
            '@type'  => 'ImageObject',
            'url'    => $img_data[0],
            'width'  => $img_data[1],
            'height' => $img_data[2],
        );
    }

    private static function get_author_schema( $post ) {
        $author_id = $post->post_author;
        return array(
            '@type' => 'Person',
            'name'  => get_the_author_meta( 'display_name', $author_id ),
            'url'   => get_author_posts_url( $author_id ),
        );
    }

    private static function get_publisher_schema( $settings ) {

        $publisher = array(
            '@type' => 'Organization',
            'name'  => isset( $settings['org_name'] ) ? $settings['org_name'] : get_bloginfo( 'name' ),
            'url'   => isset( $settings['org_url'] ) ? $settings['org_url'] : home_url(),
        );

        $logo = isset( $settings['org_logo'] ) ? $settings['org_logo'] : '';
        if ( ! empty( $logo ) ) {
            $publisher['logo'] = array(
                '@type' => 'ImageObject',
                'url'   => $logo,
            );
        }

        return $publisher;
    }
}