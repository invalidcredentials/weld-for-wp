<?php
/**
 * Wallet controller — generate, archive, send, balance AJAX handlers.
 * Adapted from pbay-marketplace-cardano PolicyWalletController.
 */

defined( 'ABSPATH' ) || exit;

class WeldPress_Wallet_Controller {

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'handle_form_submissions' ) );
        add_action( 'wp_ajax_weldpress_archive_wallet', array( __CLASS__, 'ajax_archive_wallet' ) );
        add_action( 'wp_ajax_weldpress_unarchive_wallet', array( __CLASS__, 'ajax_unarchive_wallet' ) );
        add_action( 'wp_ajax_weldpress_delete_archived_wallet', array( __CLASS__, 'ajax_delete_archived_wallet' ) );
        add_action( 'wp_ajax_weldpress_send_ada', array( __CLASS__, 'ajax_send_ada' ) );
        add_action( 'wp_ajax_weldpress_get_wallet_balance', array( __CLASS__, 'ajax_get_wallet_balance' ) );
    }

    /**
     * Handle POST form submissions (generate / delete).
     */
    public static function handle_form_submissions() {
        if ( ! isset( $_POST['weldpress_wallet_action'] ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['weldpress_wallet_action'] ) );

        if ( 'generate' === $action ) {
            check_admin_referer( 'weldpress_generate_wallet' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'Insufficient permissions' );
            }
            self::generate_wallet();
        }

        if ( 'delete' === $action ) {
            check_admin_referer( 'weldpress_delete_wallet' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'Insufficient permissions' );
            }
            self::delete_wallet();
        }
    }

    /**
     * Generate a new custodial wallet using PHP-Cardano.
     */
    private static function generate_wallet() {
        $wallet_name = sanitize_text_field( wp_unslash( $_POST['wallet_name'] ?? 'Default Wallet' ) );
        $settings    = new WeldPress_Settings();
        $network     = $settings->get_network();

        // Load PHP-Cardano.
        require_once WELDPRESS_DIR . 'includes/cardano/Ed25519Compat.php';
        require_once WELDPRESS_DIR . 'includes/cardano/Ed25519Pure.php';
        require_once WELDPRESS_DIR . 'includes/cardano/CardanoWalletPHP.php';

        try {
            Ed25519Compat::init();
            $result = CardanoWalletPHP::generateWallet( $network );
        } catch ( Throwable $e ) {
            set_transient( 'weldpress_admin_notice', 'Wallet generation failed: ' . $e->getMessage(), 30 );
            set_transient( 'weldpress_admin_notice_type', 'error', 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=weldpress&tab=wallet&error=1' ) );
            exit;
        }

        if ( empty( $result['mnemonic'] ) || empty( $result['payment_skey_extended'] ) ) {
            set_transient( 'weldpress_admin_notice', 'Invalid wallet data returned.', 30 );
            set_transient( 'weldpress_admin_notice_type', 'error', 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=weldpress&tab=wallet&error=1' ) );
            exit;
        }

        // Encrypt sensitive data.
        $mnemonic_encrypted = WeldPress_Encryption::encrypt( $result['mnemonic'] );
        $skey_encrypted     = WeldPress_Encryption::encrypt( $result['payment_skey_extended'] );

        if ( empty( $mnemonic_encrypted ) || empty( $skey_encrypted ) ) {
            set_transient( 'weldpress_admin_notice', 'Failed to encrypt wallet data.', 30 );
            set_transient( 'weldpress_admin_notice_type', 'error', 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=weldpress&tab=wallet&error=1' ) );
            exit;
        }

        $payment_address = $result['addresses']['payment_address'] ?? '';
        $stake_address   = $result['addresses']['stake_address'] ?? '';
        $payment_keyhash = $result['payment_keyhash'] ?? '';

        $wallet_id = WeldPress_Wallet_Model::insert( array(
            'wallet_name'        => $wallet_name,
            'mnemonic_encrypted' => $mnemonic_encrypted,
            'skey_encrypted'     => $skey_encrypted,
            'payment_address'    => $payment_address,
            'payment_keyhash'    => $payment_keyhash,
            'stake_address'      => $stake_address,
            'network'            => $network,
        ) );

        if ( $wallet_id ) {
            // Store mnemonic for ONE-TIME display (5 minutes).
            set_transient( 'weldpress_wallet_mnemonic_' . get_current_user_id(), $result['mnemonic'], 300 );
            set_transient( 'weldpress_admin_notice', 'Wallet created. Address: ' . $payment_address, 30 );
            set_transient( 'weldpress_admin_notice_type', 'success', 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=weldpress&tab=wallet&created=1' ) );
        } else {
            set_transient( 'weldpress_admin_notice', 'Failed to save wallet to database.', 30 );
            set_transient( 'weldpress_admin_notice_type', 'error', 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=weldpress&tab=wallet&error=1' ) );
        }
        exit;
    }

    /**
     * Delete wallet (form POST).
     */
    private static function delete_wallet() {
        $wallet_id = absint( $_POST['wallet_id'] ?? 0 );
        if ( $wallet_id > 0 ) {
            WeldPress_Wallet_Model::delete( $wallet_id );
            set_transient( 'weldpress_admin_notice', 'Wallet deleted.', 30 );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=weldpress&tab=wallet' ) );
        exit;
    }

    // ── AJAX handlers ────────────────────────────────────

    public static function ajax_archive_wallet() {
        check_ajax_referer( 'weldpress_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }

        $wallet_id = absint( $_POST['wallet_id'] ?? 0 );
        $result    = WeldPress_Wallet_Model::archive( $wallet_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => 'Wallet archived' ) );
    }

    public static function ajax_unarchive_wallet() {
        check_ajax_referer( 'weldpress_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }

        $wallet_id = absint( $_POST['wallet_id'] ?? 0 );
        $result    = WeldPress_Wallet_Model::unarchive( $wallet_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => 'Wallet restored' ) );
    }

    public static function ajax_delete_archived_wallet() {
        check_ajax_referer( 'weldpress_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }

        $wallet_id = absint( $_POST['wallet_id'] ?? 0 );
        $wallet    = WeldPress_Wallet_Model::get_by_id( $wallet_id );

        if ( ! $wallet ) {
            wp_send_json_error( array( 'message' => 'Wallet not found' ) );
        }

        if ( ! $wallet['archived'] ) {
            wp_send_json_error( array( 'message' => 'Cannot delete active wallet. Archive it first.' ) );
        }

        WeldPress_Wallet_Model::delete( $wallet_id );
        wp_send_json_success( array( 'message' => 'Wallet deleted permanently' ) );
    }

    /**
     * AJAX: Server-side send ADA from custodial wallet.
     * Build → Sign (PHP-Cardano) → Submit (Anvil).
     */
    public static function ajax_send_ada() {
        check_ajax_referer( 'weldpress_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }

        $recipient  = sanitize_text_field( wp_unslash( $_POST['recipient_address'] ?? '' ) );
        $ada_amount = floatval( $_POST['ada_amount'] ?? 0 );

        if ( empty( $recipient ) || ! preg_match( '/^addr(_test)?1[a-z0-9]+$/i', $recipient ) ) {
            wp_send_json_error( array( 'message' => 'Invalid recipient address.' ) );
        }

        if ( $ada_amount < 1 ) {
            wp_send_json_error( array( 'message' => 'Minimum send amount is 1 ADA.' ) );
        }

        $lovelace = intval( $ada_amount * 1000000 );
        $settings = new WeldPress_Settings();
        $network  = $settings->get_network();
        $wallet   = WeldPress_Wallet_Model::get_active_wallet( $network );

        if ( ! $wallet ) {
            wp_send_json_error( array( 'message' => 'No active wallet for ' . $network ) );
        }

        // Decrypt signing key.
        $skey_hex = WeldPress_Encryption::decrypt( $wallet['skey_encrypted'] );
        if ( empty( $skey_hex ) ) {
            wp_send_json_error( array( 'message' => 'Could not decrypt wallet signing key.' ) );
        }

        // Build transaction via Anvil.
        $client       = new WeldPress_Anvil_Client();
        $build_result = $client->build( $wallet['payment_address'], array(
            array( 'address' => $recipient, 'lovelace' => $lovelace ),
        ) );

        if ( is_wp_error( $build_result ) ) {
            $msg = $build_result->get_error_message();
            if ( stripos( $msg, 'insufficient' ) !== false ) {
                $msg = 'Insufficient funds. Check your wallet balance.';
            }
            wp_send_json_error( array( 'message' => 'Build failed: ' . $msg ) );
        }

        $unsigned_tx = $build_result['complete'] ?? null;
        if ( ! $unsigned_tx ) {
            wp_send_json_error( array( 'message' => 'No transaction hex returned from Anvil.' ) );
        }

        // Sign with PHP-Cardano.
        require_once WELDPRESS_DIR . 'includes/cardano/Ed25519Compat.php';
        require_once WELDPRESS_DIR . 'includes/cardano/Ed25519Pure.php';
        require_once WELDPRESS_DIR . 'includes/cardano/CardanoTransactionSignerPHP.php';

        $sign_result = CardanoTransactionSignerPHP::signTransaction( $unsigned_tx, $skey_hex );

        if ( ! $sign_result || ! $sign_result['success'] ) {
            wp_send_json_error( array( 'message' => 'Signing failed: ' . ( $sign_result['error'] ?? 'Unknown' ) ) );
        }

        // Submit.
        $submit_result = $client->submit( $unsigned_tx, array( $sign_result['witnessSetHex'] ) );

        if ( is_wp_error( $submit_result ) ) {
            wp_send_json_error( array( 'message' => 'Submit failed: ' . $submit_result->get_error_message() ) );
        }

        $tx_hash     = $submit_result['txHash'] ?? $submit_result['hash'] ?? '';
        $explorer_url = ( 'mainnet' === $network )
            ? 'https://cardanoscan.io/transaction/' . $tx_hash
            : 'https://preprod.cardanoscan.io/transaction/' . $tx_hash;

        wp_send_json_success( array(
            'message'      => $ada_amount . ' ADA sent successfully!',
            'tx_hash'      => $tx_hash,
            'explorer_url' => $explorer_url,
        ) );
    }

    /**
     * AJAX: Fetch wallet balance from Blockfrost.
     * Returns ADA balance + enriched native assets.
     */
    public static function ajax_get_wallet_balance() {
        check_ajax_referer( 'weldpress_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }

        $settings = new WeldPress_Settings();
        $network  = $settings->get_network();
        $wallet   = WeldPress_Wallet_Model::get_active_wallet( $network );

        if ( ! $wallet ) {
            wp_send_json_error( array( 'message' => 'No active wallet.' ) );
        }

        $blockfrost = new WeldPress_Blockfrost_Client( $settings );
        $balance    = $blockfrost->get_address_balance( $wallet['payment_address'] );

        if ( is_wp_error( $balance ) ) {
            wp_send_json_error( array(
                'message' => $balance->get_error_message(),
                'code'    => $balance->get_error_code(),
            ) );
        }

        // Enrich native assets with metadata (images, names, etc).
        $enriched_assets = array();
        if ( ! empty( $balance['assets'] ) ) {
            $enriched_assets = $blockfrost->enrich_assets( $balance['assets'] );
        }

        wp_send_json_success( array(
            'lovelace' => $balance['lovelace'],
            'ada'      => $balance['ada'],
            'assets'   => $enriched_assets,
        ) );
    }
}
