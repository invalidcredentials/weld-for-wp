<?php
/**
 * Anvil API HTTP client — builds and submits Cardano transactions.
 */

defined( 'ABSPATH' ) || exit;

class WeldPress_Anvil_Client {

    private $settings;

    public function __construct( WeldPress_Settings $settings = null ) {
        $this->settings = $settings ?: new WeldPress_Settings();
    }

    /**
     * Build an unsigned transaction.
     *
     * @param string $change_address  Bech32 change address.
     * @param array  $outputs         Array of [ 'address' => string, 'lovelace' => int ].
     * @return array|WP_Error
     */
    public function build( $change_address, array $outputs ) {
        $payload = array(
            'changeAddress' => $change_address,
            'outputs'       => $outputs,
        );

        return $this->request( '/v2/services/transactions/build', $payload );
    }

    /**
     * Submit a signed transaction.
     *
     * @param string $transaction  Signed transaction CBOR hex.
     * @param array  $signatures   Array of witness set hex strings.
     * @return array|WP_Error
     */
    public function submit( $transaction, array $signatures = array() ) {
        $payload = array(
            'transaction' => $transaction,
        );

        if ( ! empty( $signatures ) ) {
            $payload['signatures'] = $signatures;
        }

        return $this->request( '/v2/services/transactions/submit', $payload );
    }

    /**
     * Check Anvil API health.
     *
     * @return array|WP_Error
     */
    public function health() {
        $base_url = $this->settings->get_anvil_base_url();
        $response = wp_remote_get(
            $base_url . '/v2/services/health',
            array(
                'timeout' => 15,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-Api-Key'    => $this->settings->get_anvil_api_key(),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            return new WP_Error(
                'anvil_error',
                isset( $body['message'] ) ? $body['message'] : "Anvil API returned HTTP {$code}"
            );
        }

        return $body;
    }

    /**
     * Make a POST request to the Anvil API.
     */
    private function request( $endpoint, array $payload ) {
        $api_key = $this->settings->get_anvil_api_key();

        if ( empty( $api_key ) ) {
            return new WP_Error( 'weldpress_no_api_key', 'Anvil API key is not configured.' );
        }

        $base_url = $this->settings->get_anvil_base_url();

        $response = wp_remote_post(
            $base_url . $endpoint,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-Api-Key'    => $api_key,
                ),
                'body'    => wp_json_encode( $payload ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            return new WP_Error(
                'anvil_error',
                isset( $body['message'] ) ? $body['message'] : "Anvil API returned HTTP {$code}",
                array( 'status' => $code )
            );
        }

        return $body;
    }
}
