<?php
/**
 * Plugin Name: LinkPilot
 * Plugin URI: https://github.com/toxzak-svg/linkpilot
 * Description: AI-powered internal link suggestions and orphaned content rescue for WordPress
 * Version: 1.0.0
 * Author: Zachary Maronek
 * Author URI: https://github.com/toxzak-svg
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: linkpilot
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package LinkPilot
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'LINKPILOT_VERSION', '1.0.0' );
define( 'LINKPILOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LINKPILOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LINKPILOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main LinkPilot class
 */
final class LinkPilot {

    /**
     * Single instance of the class
     *
     * @var LinkPilot
     */
    private static $instance = null;

    /**
     * Get single instance of LinkPilot
     *
     * @return LinkPilot
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once LINKPILOT_PLUGIN_DIR . 'includes/class-linkpilot-analyzer.php';
        require_once LINKPILOT_PLUGIN_DIR . 'includes/class-linkpilot-orphaned.php';
        require_once LINKPILOT_PLUGIN_DIR . 'includes/class-linkpilot-rest-api.php';
        require_once LINKPILOT_PLUGIN_DIR . 'includes/class-linkpilot-admin.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize components
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'rest_api_init', array( 'LinkPilot_REST_API', 'register_routes' ) );
        add_action( 'admin_menu', array( 'LinkPilot_Admin', 'add_admin_menu' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain( 'linkpilot', false, dirname( LINKPILOT_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_editor_assets() {
        $asset_file = LINKPILOT_PLUGIN_DIR . 'build/editor.asset.php';
        
        if ( file_exists( $asset_file ) ) {
            $asset = include $asset_file;
        } else {
            $asset = array(
                'dependencies' => array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ),
                'version'      => LINKPILOT_VERSION,
            );
        }

        wp_enqueue_script(
            'linkpilot-editor',
            LINKPILOT_PLUGIN_URL . 'build/editor.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'linkpilot-editor',
            LINKPILOT_PLUGIN_URL . 'assets/css/editor.css',
            array( 'wp-components' ),
            LINKPILOT_VERSION
        );

        wp_localize_script(
            'linkpilot-editor',
            'linkpilotData',
            array(
                'restUrl' => rest_url( 'linkpilot/v1/' ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            )
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_linkpilot-orphaned' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'linkpilot-admin',
            LINKPILOT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            LINKPILOT_VERSION
        );

        wp_enqueue_script(
            'linkpilot-admin',
            LINKPILOT_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            LINKPILOT_VERSION,
            true
        );

        wp_localize_script(
            'linkpilot-admin',
            'linkpilotAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'linkpilot_admin_nonce' ),
            )
        );
    }
}

// Initialize the plugin
function linkpilot_init() {
    return LinkPilot::get_instance();
}

// Start the plugin
linkpilot_init();
