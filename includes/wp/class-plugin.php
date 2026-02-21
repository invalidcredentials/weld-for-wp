<?php
/**
 * Main plugin bootstrap — wires all WP hooks.
 */

defined( 'ABSPATH' ) || exit;

class WeldPress_Plugin {

    private static $initiated = false;

    public static function init() {
        if ( self::$initiated ) {
            return;
        }
        self::$initiated = true;

        // Run DB upgrades when version changes (no deactivate/reactivate needed).
        self::maybe_upgrade();

        WeldPress_Assets::init();
        WeldPress_Shortcodes::init();
        WeldPress_REST_API::init();
        WeldPress_Wallet_Controller::init();

        if ( is_admin() ) {
            WeldPress_Admin::init();
        }
    }

    /**
     * Run upgrades if the stored version differs from the code version.
     */
    private static function maybe_upgrade() {
        $stored = get_option( 'weldpress_version', '0' );
        if ( version_compare( $stored, WELDPRESS_VERSION, '<' ) ) {
            WeldPress_Wallet_Model::create_table();
            update_option( 'weldpress_version', WELDPRESS_VERSION );
        }
    }

    public static function activate() {
        update_option( 'weldpress_version', WELDPRESS_VERSION );

        // Set defaults only if they don't exist.
        if ( false === get_option( 'weldpress_network' ) ) {
            update_option( 'weldpress_network', 'preprod' );
        }
        if ( false === get_option( 'weldpress_custodial_enabled' ) ) {
            update_option( 'weldpress_custodial_enabled', '0' );
        }

        // Create wallet table.
        WeldPress_Wallet_Model::create_table();

        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}
