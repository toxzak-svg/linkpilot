<?php
/**
 * LinkPilot Admin Class
 *
 * Handles admin pages and settings
 *
 * @package LinkPilot
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class LinkPilot_Admin
 */
class LinkPilot_Admin {

    /**
     * Add admin menu pages
     */
    public static function add_admin_menu() {
        add_menu_page(
            __( 'LinkPilot', 'linkpilot' ),
            __( 'LinkPilot', 'linkpilot' ),
            'edit_posts',
            'linkpilot-orphaned',
            array( __CLASS__, 'render_orphaned_page' ),
            'dashicons-admin-links',
            30
        );

        add_submenu_page(
            'linkpilot-orphaned',
            __( 'Orphaned Content', 'linkpilot' ),
            __( 'Orphaned Content', 'linkpilot' ),
            'edit_posts',
            'linkpilot-orphaned',
            array( __CLASS__, 'render_orphaned_page' )
        );
    }

    /**
     * Render the orphaned content finder page
     */
    public static function render_orphaned_page() {
        ?>
        <div class="wrap linkpilot-admin">
            <h1>
                <span class="dashicons dashicons-admin-links"></span>
                <?php esc_html_e( 'LinkPilot - Orphaned Content Finder', 'linkpilot' ); ?>
            </h1>
            
            <div class="linkpilot-description">
                <p><?php esc_html_e( 'Find posts and pages that have no incoming internal links. These "orphaned" pages may be harder to discover and could benefit from internal linking from other content.', 'linkpilot' ); ?></p>
            </div>

            <div class="linkpilot-filters">
                <label for="linkpilot-post-type"><?php esc_html_e( 'Post Type:', 'linkpilot' ); ?></label>
                <select id="linkpilot-post-type" multiple>
                    <option value="post" selected><?php esc_html_e( 'Posts', 'linkpilot' ); ?></option>
                    <option value="page" selected><?php esc_html_e( 'Pages', 'linkpilot' ); ?></option>
                    <?php
                    // Add custom post types
                    $custom_post_types = get_post_types( array(
                        'public'   => true,
                        '_builtin' => false,
                    ), 'objects' );
                    
                    foreach ( $custom_post_types as $post_type ) {
                        printf(
                            '<option value="%s">%s</option>',
                            esc_attr( $post_type->name ),
                            esc_html( $post_type->labels->name )
                        );
                    }
                    ?>
                </select>
                
                <button type="button" id="linkpilot-scan-btn" class="button button-primary">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Scan for Orphaned Content', 'linkpilot' ); ?>
                </button>
            </div>

            <div id="linkpilot-loading" class="linkpilot-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <?php esc_html_e( 'Scanning content...', 'linkpilot' ); ?>
            </div>

            <div id="linkpilot-results" class="linkpilot-results" style="display: none;">
                <div class="linkpilot-summary">
                    <span id="linkpilot-total-count">0</span> <?php esc_html_e( 'orphaned items found', 'linkpilot' ); ?>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-title"><?php esc_html_e( 'Title', 'linkpilot' ); ?></th>
                            <th class="column-type"><?php esc_html_e( 'Type', 'linkpilot' ); ?></th>
                            <th class="column-date"><?php esc_html_e( 'Date', 'linkpilot' ); ?></th>
                            <th class="column-actions"><?php esc_html_e( 'Actions', 'linkpilot' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="linkpilot-results-body">
                    </tbody>
                </table>
                
                <div id="linkpilot-pagination" class="linkpilot-pagination"></div>
            </div>

            <div id="linkpilot-no-results" class="linkpilot-no-results" style="display: none;">
                <span class="dashicons dashicons-yes-alt"></span>
                <p><?php esc_html_e( 'Great news! No orphaned content found. All your posts and pages have at least one incoming internal link.', 'linkpilot' ); ?></p>
            </div>

            <div id="linkpilot-error" class="linkpilot-error notice notice-error" style="display: none;">
                <p></p>
            </div>
        </div>
        <?php
    }
}
