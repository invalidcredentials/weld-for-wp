<?php
/**
 * Admin pages and settings UI.
 */

defined( 'ABSPATH' ) || exit;

class WeldPress_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_privacy_policy' ) );
    }

    /**
     * Register privacy policy content for the WP Privacy Policy editor.
     */
    public static function register_privacy_policy() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }

        $content = '<h2>Weld Cardano</h2>' .
            '<p>This plugin connects to external Cardano blockchain services when configured by the site administrator:</p>' .
            '<ul>' .
            '<li><strong>Ada Anvil API</strong> (<a href="https://ada-anvil.io">ada-anvil.io</a>) — used for building and submitting Cardano transactions. ' .
            'Wallet addresses and transaction parameters are transmitted when a user initiates a transaction.</li>' .
            '<li><strong>Blockfrost API</strong> (<a href="https://blockfrost.io">blockfrost.io</a>) — used for querying Cardano blockchain data (balances, native asset metadata). ' .
            'Wallet addresses are transmitted when balance information is requested.</li>' .
            '</ul>' .
            '<p>No data is sent to these services until the administrator has configured API keys and a user initiates a blockchain operation.</p>' .
            '<p>If custodial wallets are enabled, wallet private keys are stored encrypted (AES-256-CBC) in the WordPress database. ' .
            'All plugin data is removed on uninstall.</p>';

        wp_add_privacy_policy_content( 'Weld Cardano', wp_kses_post( $content ) );
    }

    public static function add_menu() {
        add_menu_page(
            'Weld Cardano',
            'Weld Cardano',
            'manage_options',
            'weldpress',
            array( __CLASS__, 'render_page' ),
            'dashicons-money-alt',
            30
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'weld-cardano' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab is a display-only navigation parameter.
        $tab      = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
        $settings = new WeldPress_Settings();

        include WELDPRESS_DIR . 'templates/admin-page.php';
    }

    /**
     * Handle settings form submissions.
     */
    public static function handle_save() {
        if ( ! isset( $_POST['weldpress_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['weldpress_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.

        // Verify nonce.
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'weldpress_' . $action ) ) {
            wp_die( esc_html__( 'Security check failed.', 'weld-cardano' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'weld-cardano' ) );
        }

        switch ( $action ) {
            case 'save_settings':
                self::save_settings();
                break;

            case 'test_connection':
                self::test_connection();
                break;
        }
    }

    private static function save_settings() {
        // Nonce verified in handle_save() before this method is called.
        $network = isset( $_POST['weldpress_network'] ) ? sanitize_text_field( wp_unslash( $_POST['weldpress_network'] ) ) : 'preprod'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! in_array( $network, array( 'preprod', 'mainnet' ), true ) ) {
            $network = 'preprod';
        }
        update_option( 'weldpress_network', $network );

        // Encrypt and save API keys.
        $key_preprod = isset( $_POST['weldpress_anvil_key_preprod'] ) ? sanitize_text_field( wp_unslash( $_POST['weldpress_anvil_key_preprod'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $key_mainnet = isset( $_POST['weldpress_anvil_key_mainnet'] ) ? sanitize_text_field( wp_unslash( $_POST['weldpress_anvil_key_mainnet'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ( ! empty( $key_preprod ) ) {
            update_option( 'weldpress_anvil_key_preprod', WeldPress_Settings::encrypt( $key_preprod ) );
        }
        if ( ! empty( $key_mainnet ) ) {
            update_option( 'weldpress_anvil_key_mainnet', WeldPress_Settings::encrypt( $key_mainnet ) );
        }

        // Blockfrost API keys.
        $bf_preprod = isset( $_POST['weldpress_blockfrost_key_preprod'] ) ? sanitize_text_field( wp_unslash( $_POST['weldpress_blockfrost_key_preprod'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $bf_mainnet = isset( $_POST['weldpress_blockfrost_key_mainnet'] ) ? sanitize_text_field( wp_unslash( $_POST['weldpress_blockfrost_key_mainnet'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ( ! empty( $bf_preprod ) ) {
            update_option( 'weldpress_blockfrost_key_preprod', WeldPress_Settings::encrypt( $bf_preprod ) );
        }
        if ( ! empty( $bf_mainnet ) ) {
            update_option( 'weldpress_blockfrost_key_mainnet', WeldPress_Settings::encrypt( $bf_mainnet ) );
        }

        $custodial = isset( $_POST['weldpress_custodial_enabled'] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_option( 'weldpress_custodial_enabled', $custodial );

        set_transient( 'weldpress_admin_notice', 'Settings saved.', 30 );

        wp_safe_redirect( admin_url( 'admin.php?page=weldpress&tab=settings&saved=1' ) );
        exit;
    }

    private static function test_connection() {
        $client = new WeldPress_Anvil_Client();
        $result = $client->health();

        if ( is_wp_error( $result ) ) {
            set_transient( 'weldpress_admin_notice', 'Connection failed: ' . $result->get_error_message(), 30 );
            set_transient( 'weldpress_admin_notice_type', 'error', 30 );
        } else {
            set_transient( 'weldpress_admin_notice', 'Anvil API connection successful!', 30 );
            set_transient( 'weldpress_admin_notice_type', 'success', 30 );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=weldpress&tab=settings' ) );
        exit;
    }
}
