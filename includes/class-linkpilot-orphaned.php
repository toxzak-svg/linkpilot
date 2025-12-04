<?php
/**
 * LinkPilot Orphaned Content Class
 *
 * Handles finding posts with zero incoming internal links
 *
 * @package LinkPilot
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class LinkPilot_Orphaned
 */
class LinkPilot_Orphaned {

    /**
     * Find orphaned posts (posts with no incoming internal links)
     *
     * @param array $args Query arguments
     * @return array Array of orphaned posts
     */
    public static function find_orphaned_posts( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'post_type'      => array( 'post', 'page' ),
            'posts_per_page' => 50,
            'paged'          => 1,
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        // Get all published posts
        $post_types = $args['post_type'];
        if ( ! is_array( $post_types ) ) {
            $post_types = array( $post_types );
        }
        
        $post_type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        
        // Get site URL for matching internal links
        $site_url = home_url();
        
        // Get all published posts of the specified types, including content
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $all_posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_content, post_type, post_date, guid
                FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                AND post_type IN ({$post_type_placeholders})
                ORDER BY post_date DESC",
                ...$post_types
            )
        );
        
        if ( empty( $all_posts ) ) {
            return array(
                'posts'       => array(),
                'total'       => 0,
                'total_pages' => 0,
            );
        }
        
        // Build a map of post URLs to post IDs
        $url_to_id_map = array();
        $id_to_post_map = array();
        
        foreach ( $all_posts as $post ) {
            $permalink = get_permalink( $post->ID );
            $url_to_id_map[ $permalink ] = $post->ID;
            $id_to_post_map[ $post->ID ] = $post;
            
            // Also add variations without trailing slash
            $url_without_slash = rtrim( $permalink, '/' );
            $url_to_id_map[ $url_without_slash ] = $post->ID;
        }
        
        // Find posts that link to each post
        $incoming_links = array();
        
        foreach ( $all_posts as $source_post ) {
            $content = $source_post->post_content;
            
            if ( empty( $content ) ) {
                continue;
            }
            
            // Find all internal links in the content
            $internal_links = self::extract_internal_links( $content, $site_url );
            
            foreach ( $internal_links as $link_url ) {
                // Normalize the URL
                $normalized_url = rtrim( $link_url, '/' );
                
                // Check if this URL maps to a post
                if ( isset( $url_to_id_map[ $normalized_url ] ) ) {
                    $target_post_id = $url_to_id_map[ $normalized_url ];
                    
                    // Don't count self-links
                    if ( $target_post_id !== $source_post->ID ) {
                        if ( ! isset( $incoming_links[ $target_post_id ] ) ) {
                            $incoming_links[ $target_post_id ] = array();
                        }
                        $incoming_links[ $target_post_id ][] = $source_post->ID;
                    }
                }
                
                // Also check with trailing slash
                $url_with_slash = $normalized_url . '/';
                if ( isset( $url_to_id_map[ $url_with_slash ] ) ) {
                    $target_post_id = $url_to_id_map[ $url_with_slash ];
                    
                    if ( $target_post_id !== $source_post->ID ) {
                        if ( ! isset( $incoming_links[ $target_post_id ] ) ) {
                            $incoming_links[ $target_post_id ] = array();
                        }
                        if ( ! in_array( $source_post->ID, $incoming_links[ $target_post_id ], true ) ) {
                            $incoming_links[ $target_post_id ][] = $source_post->ID;
                        }
                    }
                }
            }
        }
        
        // Find orphaned posts (posts with no incoming links)
        $orphaned_posts = array();
        
        foreach ( $all_posts as $post ) {
            $incoming_count = isset( $incoming_links[ $post->ID ] ) ? count( $incoming_links[ $post->ID ] ) : 0;
            
            if ( 0 === $incoming_count ) {
                $orphaned_posts[] = array(
                    'id'             => $post->ID,
                    'title'          => $post->post_title,
                    'post_type'      => $post->post_type,
                    'date'           => $post->post_date,
                    'url'            => get_permalink( $post->ID ),
                    'edit_url'       => get_edit_post_link( $post->ID, 'raw' ),
                    'incoming_links' => 0,
                );
            }
        }
        
        // Paginate results
        $total = count( $orphaned_posts );
        $total_pages = ceil( $total / $args['posts_per_page'] );
        $offset = ( $args['paged'] - 1 ) * $args['posts_per_page'];
        
        $paginated_posts = array_slice( $orphaned_posts, $offset, $args['posts_per_page'] );
        
        return array(
            'posts'       => $paginated_posts,
            'total'       => $total,
            'total_pages' => $total_pages,
            'current_page' => $args['paged'],
        );
    }

    /**
     * Extract internal links from content
     *
     * @param string $content  The post content
     * @param string $site_url The site URL
     * @return array Array of internal link URLs
     */
    private static function extract_internal_links( $content, $site_url ) {
        $links = array();
        
        // Match href attributes
        if ( preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
            foreach ( $matches[1] as $url ) {
                // Check if it's an internal link
                if ( self::is_internal_link( $url, $site_url ) ) {
                    $links[] = $url;
                }
            }
        }
        
        return array_unique( $links );
    }

    /**
     * Check if a URL is an internal link
     *
     * @param string $url      The URL to check
     * @param string $site_url The site URL
     * @return bool True if internal, false otherwise
     */
    private static function is_internal_link( $url, $site_url ) {
        // Skip empty URLs, anchors, and special protocols
        if ( empty( $url ) || 
             0 === strpos( $url, '#' ) || 
             0 === strpos( $url, 'mailto:' ) ||
             0 === strpos( $url, 'tel:' ) ||
             0 === strpos( $url, 'javascript:' ) ) {
            return false;
        }
        
        // Parse the URL
        $parsed_url = wp_parse_url( $url );
        $parsed_site = wp_parse_url( $site_url );
        
        // If no host, it's a relative URL (internal)
        if ( ! isset( $parsed_url['host'] ) ) {
            // But skip certain paths
            if ( isset( $parsed_url['path'] ) ) {
                $path = $parsed_url['path'];
                // Skip wp-content, wp-admin, wp-includes, and feed links
                if ( preg_match( '#^/(wp-content|wp-admin|wp-includes|feed)/#', $path ) ) {
                    return false;
                }
            }
            return true;
        }
        
        // Check if the host matches the site host
        return $parsed_url['host'] === $parsed_site['host'];
    }

    /**
     * Get link statistics for a single post
     *
     * @param int $post_id The post ID
     * @return array Statistics about incoming and outgoing links
     */
    public static function get_post_link_stats( $post_id ) {
        global $wpdb;
        
        $site_url = home_url();
        
        // Get the post content
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $content = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
                $post_id
            )
        );
        
        // Count outgoing internal links
        $outgoing_links = array();
        if ( ! empty( $content ) ) {
            $outgoing_links = self::extract_internal_links( $content, $site_url );
        }
        
        // Count incoming links (search for this post's URL in other posts)
        $permalink = get_permalink( $post_id );
        $permalink_escaped = $wpdb->esc_like( $permalink );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $incoming_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts}
                WHERE post_content LIKE %s
                AND post_status = 'publish'
                AND post_type IN ('post', 'page')
                AND ID != %d",
                '%' . $permalink_escaped . '%',
                $post_id
            )
        );
        
        return array(
            'outgoing_count' => count( array_unique( $outgoing_links ) ),
            'incoming_count' => (int) $incoming_count,
            'outgoing_links' => array_unique( $outgoing_links ),
        );
    }
}
