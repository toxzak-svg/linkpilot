<?php
/**
 * LinkPilot Analyzer Class
 *
 * Handles keyword extraction and internal link suggestions
 *
 * @package LinkPilot
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class LinkPilot_Analyzer
 */
class LinkPilot_Analyzer {

    /**
     * Common stop words to filter out
     *
     * @var array
     */
    private static $stop_words = array(
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
        'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
        'to', 'was', 'were', 'will', 'with', 'the', 'this', 'but', 'they',
        'have', 'had', 'what', 'when', 'where', 'who', 'which', 'why', 'how',
        'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some',
        'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too',
        'very', 'can', 'just', 'should', 'now', 'also', 'into', 'your', 'our',
        'their', 'there', 'here', 'would', 'could', 'may', 'might', 'must',
        'shall', 'about', 'above', 'after', 'again', 'against', 'any', 'because',
        'been', 'before', 'being', 'below', 'between', 'during', 'further',
        'having', 'once', 'over', 'through', 'under', 'until', 'while', 'you',
        'me', 'him', 'her', 'them', 'my', 'his', 'she', 'we', 'us', 'i', 'am',
        'do', 'does', 'did', 'doing', 'if', 'then', 'else', 'up', 'down', 'out',
        'off', 'get', 'got', 'gets', 'make', 'made', 'makes', 'like', 'well',
        'back', 'even', 'still', 'way', 'take', 'come', 'use', 'used', 'using'
    );

    /**
     * Extract keywords from content
     *
     * @param string $content The post content
     * @param int    $limit   Maximum number of keywords to return
     * @return array Array of keywords with their frequency
     */
    public static function extract_keywords( $content, $limit = 20 ) {
        // Strip shortcodes first, then HTML tags
        $content = strip_shortcodes( $content );
        $content = wp_strip_all_tags( $content );
        
        // Convert to lowercase
        $content = strtolower( $content );
        
        // Remove special characters but keep spaces
        $content = preg_replace( '/[^a-z0-9\s]/', '', $content );
        
        // Split into words
        $words = preg_split( '/\s+/', $content, -1, PREG_SPLIT_NO_EMPTY );
        
        // Filter out stop words and short words
        $filtered_words = array_filter( $words, function( $word ) {
            return strlen( $word ) > 2 && ! in_array( $word, self::$stop_words, true );
        });
        
        // Count word frequency
        $word_counts = array_count_values( $filtered_words );
        
        // Sort by frequency (descending)
        arsort( $word_counts );
        
        // Get top keywords
        $keywords = array_slice( $word_counts, 0, $limit, true );
        
        // Also extract two-word phrases (bigrams)
        $bigrams = self::extract_bigrams( $content, 10 );
        
        return array(
            'single_keywords' => $keywords,
            'phrases'         => $bigrams,
        );
    }

    /**
     * Extract two-word phrases (bigrams) from content
     *
     * @param string $content The cleaned content
     * @param int    $limit   Maximum number of bigrams to return
     * @return array Array of bigrams with their frequency
     */
    private static function extract_bigrams( $content, $limit = 10 ) {
        $words = preg_split( '/\s+/', $content, -1, PREG_SPLIT_NO_EMPTY );
        $bigrams = array();
        
        for ( $i = 0; $i < count( $words ) - 1; $i++ ) {
            $word1 = $words[ $i ];
            $word2 = $words[ $i + 1 ];
            
            // Skip if either word is a stop word or too short
            if ( in_array( $word1, self::$stop_words, true ) || 
                 in_array( $word2, self::$stop_words, true ) ||
                 strlen( $word1 ) < 3 || strlen( $word2 ) < 3 ) {
                continue;
            }
            
            $bigram = $word1 . ' ' . $word2;
            if ( ! isset( $bigrams[ $bigram ] ) ) {
                $bigrams[ $bigram ] = 0;
            }
            $bigrams[ $bigram ]++;
        }
        
        // Filter bigrams that only appear once
        $bigrams = array_filter( $bigrams, function( $count ) {
            return $count > 1;
        });
        
        arsort( $bigrams );
        
        return array_slice( $bigrams, 0, $limit, true );
    }

    /**
     * Find suggested internal links based on keywords
     *
     * @param int   $current_post_id The current post ID to exclude
     * @param array $keywords        Array of keywords to search for
     * @param int   $limit           Maximum number of suggestions to return
     * @return array Array of suggested posts with relevance scores
     */
    public static function find_link_suggestions( $current_post_id, $keywords, $limit = 5 ) {
        global $wpdb;
        
        $all_keywords = array();
        
        // Combine single keywords and phrases
        if ( isset( $keywords['single_keywords'] ) ) {
            foreach ( $keywords['single_keywords'] as $keyword => $count ) {
                $all_keywords[] = array(
                    'term'   => $keyword,
                    'weight' => $count,
                );
            }
        }
        
        if ( isset( $keywords['phrases'] ) ) {
            foreach ( $keywords['phrases'] as $phrase => $count ) {
                $all_keywords[] = array(
                    'term'   => $phrase,
                    'weight' => $count * 2, // Phrases are weighted higher
                );
            }
        }
        
        if ( empty( $all_keywords ) ) {
            return array();
        }
        
        // Build search query
        $candidates = array();
        
        // Get posts that are published and indexable
        $excluded_statuses = array( 'draft', 'pending', 'trash', 'auto-draft', 'inherit' );
        
        foreach ( $all_keywords as $keyword_data ) {
            $term = $keyword_data['term'];
            $weight = $keyword_data['weight'];
            
            // Search in post title and content
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, post_title, post_content, post_type, post_status
                    FROM {$wpdb->posts}
                    WHERE (post_title LIKE %s OR post_content LIKE %s)
                    AND post_status = 'publish'
                    AND post_type IN ('post', 'page')
                    AND ID != %d
                    LIMIT 50",
                    '%' . $wpdb->esc_like( $term ) . '%',
                    '%' . $wpdb->esc_like( $term ) . '%',
                    $current_post_id
                )
            );
            
            foreach ( $results as $post ) {
                $post_id = $post->ID;
                
                // Check if post is set to noindex (common SEO plugins)
                if ( self::is_post_noindex( $post_id ) ) {
                    continue;
                }
                
                if ( ! isset( $candidates[ $post_id ] ) ) {
                    $candidates[ $post_id ] = array(
                        'id'              => $post_id,
                        'title'           => $post->post_title,
                        'url'             => get_permalink( $post_id ),
                        'post_type'       => $post->post_type,
                        'score'           => 0,
                        'matching_terms'  => array(),
                    );
                }
                
                // Calculate score based on where the keyword appears
                $title_matches = substr_count( strtolower( $post->post_title ), strtolower( $term ) );
                $content_matches = substr_count( strtolower( $post->post_content ), strtolower( $term ) );
                
                // Title matches are worth more
                $term_score = ( $title_matches * 10 + $content_matches ) * $weight;
                $candidates[ $post_id ]['score'] += $term_score;
                
                if ( ! in_array( $term, $candidates[ $post_id ]['matching_terms'], true ) ) {
                    $candidates[ $post_id ]['matching_terms'][] = $term;
                }
            }
        }
        
        // Sort by score (descending)
        usort( $candidates, function( $a, $b ) {
            return $b['score'] - $a['score'];
        });
        
        // Return top suggestions
        return array_slice( $candidates, 0, $limit );
    }

    /**
     * Check if a post is set to noindex
     *
     * @param int $post_id The post ID
     * @return bool True if noindex, false otherwise
     */
    private static function is_post_noindex( $post_id ) {
        // Check Yoast SEO
        $yoast_noindex = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
        if ( '1' === $yoast_noindex ) {
            return true;
        }
        
        // Check Rank Math
        $rankmath_robots = get_post_meta( $post_id, 'rank_math_robots', true );
        if ( is_array( $rankmath_robots ) && in_array( 'noindex', $rankmath_robots, true ) ) {
            return true;
        }
        
        // Check All in One SEO
        $aioseo_noindex = get_post_meta( $post_id, '_aioseo_noindex', true );
        if ( '1' === $aioseo_noindex ) {
            return true;
        }
        
        // Check SEOPress
        $seopress_noindex = get_post_meta( $post_id, '_seopress_robots_index', true );
        if ( 'yes' === $seopress_noindex ) {
            return true;
        }
        
        return false;
    }

    /**
     * Analyze content and get link suggestions
     *
     * @param int    $post_id The current post ID
     * @param string $content The post content
     * @return array Analysis results with keywords and suggestions
     */
    public static function analyze_content( $post_id, $content ) {
        // Extract keywords
        $keywords = self::extract_keywords( $content );
        
        // Find link suggestions
        $suggestions = self::find_link_suggestions( $post_id, $keywords );
        
        return array(
            'keywords'    => $keywords,
            'suggestions' => $suggestions,
        );
    }
}
