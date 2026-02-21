<?php
/**
 * Script and style enqueue logic.
 */

defined( 'ABSPATH' ) || exit;

class WeldPress_Assets {

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
    }

    public static function enqueue_frontend() {
        // Only load when shortcodes are present (or always on — configurable later).
        wp_register_script(
            'weldpress',
            WELDPRESS_URL . 'assets/js/weldpress.js',
            array(),
            WELDPRESS_VERSION,
            true
        );

        wp_register_style(
            'weldpress',
            WELDPRESS_URL . 'assets/css/weldpress.css',
            array(),
            WELDPRESS_VERSION
        );

        // Pass config to JS.
        wp_localize_script( 'weldpress', 'weldpressConfig', self::get_js_config() );
    }

    public static function enqueue_admin( $hook ) {
        if ( strpos( $hook, 'weldpress' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'weldpress-admin',
            WELDPRESS_URL . 'assets/css/weldpress-admin.css',
            array(),
            WELDPRESS_VERSION
        );

        wp_enqueue_script(
            'weldpress-admin',
            WELDPRESS_URL . 'assets/js/weldpress-admin.js',
            array(),
            WELDPRESS_VERSION,
            true
        );

        wp_localize_script( 'weldpress-admin', 'weldpressAdmin', array(
            'ajaxurl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'weldpress_admin_nonce' ),
            'settingsUrl' => admin_url( 'admin.php?page=weldpress&tab=settings' ),
        ) );
    }

    /**
     * Config passed to the frontend JS bundle via wp_localize_script.
     */
    private static function get_js_config() {
        $settings = new WeldPress_Settings();
        return array(
            'network'  => $settings->get_network(),
            'restUrl'  => esc_url_raw( rest_url( 'weldpress/v1/' ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'features' => array(
                'custodial' => $settings->is_custodial_enabled(),
            ),
        );
    }
}
