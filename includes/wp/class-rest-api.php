<?php
/**
 * REST API route registration and controllers.
 */

defined( 'ABSPATH' ) || exit;

class WeldPress_REST_API {

    const NAMESPACE = 'weldpress/v1';

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route( self::NAMESPACE, '/config', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_config' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NAMESPACE, '/tx/build', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'build_tx' ),
            'permission_callback' => array( __CLASS__, 'check_authenticated' ),
            'args'                => array(
                'change_address' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'outputs' => array(
                    'required' => true,
                    'type'     => 'array',
                ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/tx/submit', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'submit_tx' ),
            'permission_callback' => array( __CLASS__, 'check_authenticated' ),
            'args'                => array(
                'transaction' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'witnesses' => array(
                    'required' => false,
                    'type'     => 'array',
                    'default'  => array(),
                ),
            ),
        ) );
    }

    /**
     * GET /config — public endpoint returning plugin configuration.
     */
    public static function get_config() {
        $settings = new WeldPress_Settings();
        return rest_ensure_response( array(
            'network'  => $settings->get_network(),
            'features' => array(
                'custodial' => $settings->is_custodial_enabled(),
            ),
        ) );
    }

    /**
     * POST /tx/build — proxy to Anvil transaction builder.
     */
    public static function build_tx( WP_REST_Request $request ) {
        $change_address = $request->get_param( 'change_address' );
        $outputs_raw    = $request->get_param( 'outputs' );

        // Validate outputs structure.
        $outputs = array();
        if ( ! is_array( $outputs_raw ) ) {
            return new WP_Error( 'invalid_outputs', 'Outputs must be an array.', array( 'status' => 400 ) );
        }

        foreach ( $outputs_raw as $output ) {
            if ( empty( $output['address'] ) || ! isset( $output['lovelace'] ) ) {
                return new WP_Error( 'invalid_output', 'Each output requires address and lovelace.', array( 'status' => 400 ) );
            }
            $outputs[] = array(
                'address'  => sanitize_text_field( $output['address'] ),
                'lovelace' => absint( $output['lovelace'] ),
            );
        }

        // Validate change address: accept bech32 (addr...) or hex (from CIP-30 wallets).
        $is_bech32 = preg_match( '/^addr(_test)?1[a-z0-9]{50,120}$/', $change_address );
        $is_hex    = preg_match( '/^[0-9a-fA-F]{58,200}$/', $change_address );
        if ( ! $is_bech32 && ! $is_hex ) {
            return new WP_Error( 'invalid_address', 'Invalid Cardano address format.', array( 'status' => 400 ) );
        }

        $client = new WeldPress_Anvil_Client();
        $result = $client->build( $change_address, $outputs );

        if ( is_wp_error( $result ) ) {
            return new WP_Error(
                'build_failed',
                $result->get_error_message(),
                array( 'status' => 502 )
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'data'    => $result,
        ) );
    }

    /**
     * POST /tx/submit — proxy to Anvil transaction submitter.
     */
    public static function submit_tx( WP_REST_Request $request ) {
        $transaction = $request->get_param( 'transaction' );
        $witnesses   = $request->get_param( 'witnesses' );

        // Validate hex string.
        if ( ! ctype_xdigit( $transaction ) ) {
            return new WP_Error( 'invalid_tx', 'Transaction must be a hex string.', array( 'status' => 400 ) );
        }

        $client = new WeldPress_Anvil_Client();
        $result = $client->submit( $transaction, $witnesses ?: array() );

        if ( is_wp_error( $result ) ) {
            return new WP_Error(
                'submit_failed',
                $result->get_error_message(),
                array( 'status' => 502 )
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'data'    => $result,
        ) );
    }

    /**
     * Permission: user must be logged in (nonce is verified automatically by WP REST).
     */
    public static function check_authenticated( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
