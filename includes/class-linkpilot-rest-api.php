<?php
/**
 * LinkPilot REST API Class
 *
 * Handles REST API endpoints for the plugin
 *
 * @package LinkPilot
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class LinkPilot_REST_API
 */
class LinkPilot_REST_API {

    /**
     * REST API namespace
     *
     * @var string
     */
    const NAMESPACE = 'linkpilot/v1';

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Analyze content endpoint
        register_rest_route(
            self::NAMESPACE,
            '/analyze',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'analyze_content' ),
                'permission_callback' => array( __CLASS__, 'check_edit_permission' ),
                'args'                => array(
                    'post_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'content' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'wp_kses_post',
                    ),
                ),
            )
        );

        // Get orphaned posts endpoint
        register_rest_route(
            self::NAMESPACE,
            '/orphaned',
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_orphaned_posts' ),
                'permission_callback' => array( __CLASS__, 'check_edit_permission' ),
                'args'                => array(
                    'post_type' => array(
                        'default'           => array( 'post', 'page' ),
                        'type'              => 'array',
                        'sanitize_callback' => function( $value ) {
                            if ( is_string( $value ) ) {
                                return array_map( 'sanitize_key', explode( ',', $value ) );
                            }
                            return array_map( 'sanitize_key', (array) $value );
                        },
                    ),
                    'page' => array(
                        'default'           => 1,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'per_page' => array(
                        'default'           => 50,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // Get link statistics for a post
        register_rest_route(
            self::NAMESPACE,
            '/stats/(?P<post_id>\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_post_stats' ),
                'permission_callback' => array( __CLASS__, 'check_edit_permission' ),
                'args'                => array(
                    'post_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
    }

    /**
     * Check if user has permission to edit posts
     *
     * @return bool True if user can edit posts
     */
    public static function check_edit_permission() {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Analyze content and return suggestions
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public static function analyze_content( $request ) {
        $post_id = $request->get_param( 'post_id' );
        $content = $request->get_param( 'content' );

        if ( empty( $content ) ) {
            return new WP_Error(
                'empty_content',
                __( 'Content is empty. Please add some content before analyzing.', 'linkpilot' ),
                array( 'status' => 400 )
            );
        }

        // Analyze the content
        $results = LinkPilot_Analyzer::analyze_content( $post_id, $content );

        return rest_ensure_response( array(
            'success'     => true,
            'keywords'    => $results['keywords'],
            'suggestions' => $results['suggestions'],
        ) );
    }

    /**
     * Get orphaned posts
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response Response object
     */
    public static function get_orphaned_posts( $request ) {
        $post_types = $request->get_param( 'post_type' );
        $page = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );

        $results = LinkPilot_Orphaned::find_orphaned_posts( array(
            'post_type'      => $post_types,
            'posts_per_page' => $per_page,
            'paged'          => $page,
        ) );

        return rest_ensure_response( array(
            'success'      => true,
            'posts'        => $results['posts'],
            'total'        => $results['total'],
            'total_pages'  => $results['total_pages'],
            'current_page' => $results['current_page'],
        ) );
    }

    /**
     * Get link statistics for a post
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response Response object
     */
    public static function get_post_stats( $request ) {
        $post_id = $request->get_param( 'post_id' );

        $stats = LinkPilot_Orphaned::get_post_link_stats( $post_id );

        return rest_ensure_response( array(
            'success' => true,
            'stats'   => $stats,
        ) );
    }
}
