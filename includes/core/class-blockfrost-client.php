<?php
/**
 * Blockfrost API client — chain queries for balance, UTxOs, asset metadata.
 * Adapted from pbay-marketplace-cardano BlockfrostAPI.
 */

defined( 'ABSPATH' ) || exit;

class WeldPress_Blockfrost_Client {

    private $settings;

    public function __construct( WeldPress_Settings $settings = null ) {
        $this->settings = $settings ?: new WeldPress_Settings();
    }

    /**
     * Get address balance — ADA + native assets.
     *
     * @param string $address Bech32 payment address.
     * @return array|WP_Error { 'lovelace' => int, 'ada' => string, 'assets' => [...] }
     */
    public function get_address_balance( $address ) {
        $result = $this->request( '/addresses/' . $address );

        if ( is_wp_error( $result ) ) {
            // 404 = address has never received funds.
            if ( $result->get_error_data() && isset( $result->get_error_data()['status'] ) && 404 === $result->get_error_data()['status'] ) {
                return array(
                    'lovelace' => 0,
                    'ada'      => '0.000000',
                    'assets'   => array(),
                );
            }
            return $result;
        }

        $lovelace = 0;
        $assets   = array();

        if ( isset( $result['amount'] ) && is_array( $result['amount'] ) ) {
            foreach ( $result['amount'] as $item ) {
                if ( 'lovelace' === $item['unit'] ) {
                    $lovelace = intval( $item['quantity'] );
                } else {
                    $assets[] = array(
                        'unit'     => $item['unit'],
                        'quantity' => intval( $item['quantity'] ),
                    );
                }
            }
        }

        $ada = number_format( $lovelace / 1000000, 6, '.', '' );

        return array(
            'lovelace' => $lovelace,
            'ada'      => $ada,
            'assets'   => $assets,
        );
    }

    /**
     * Get asset info — CIP-25 metadata, fingerprint, mint quantity.
     *
     * @param string $unit  Concatenated policy_id + hex asset name.
     * @return array|WP_Error
     */
    public function get_asset_info( $unit ) {
        $result = $this->request( '/assets/' . $unit );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $policy_id  = $result['policy_id'] ?? '';
        $asset_hex  = $result['asset_name'] ?? '';
        $asset_name = self::hex_to_ascii( $asset_hex );

        // CIP-25 on-chain metadata.
        $metadata = array();
        $image    = '';

        if ( ! empty( $result['onchain_metadata'] ) ) {
            $meta = $result['onchain_metadata'];
            $image = self::resolve_image( $meta['image'] ?? '' );

            // Flatten metadata for display.
            $metadata = self::flatten_metadata( $meta );
        }

        return array(
            'unit'           => $unit,
            'policy_id'      => $policy_id,
            'asset_name'     => $asset_name,
            'asset_name_hex' => $asset_hex,
            'fingerprint'    => $result['fingerprint'] ?? '',
            'quantity'       => intval( $result['quantity'] ?? 0 ),
            'mint_quantity'  => intval( $result['quantity'] ?? 0 ),
            'image'          => $image,
            'metadata'       => $metadata,
        );
    }

    /**
     * Enrich a list of basic assets with on-chain metadata.
     * Caps at 20 to avoid rate limits.
     *
     * @param array $assets Array of [ 'unit' => string, 'quantity' => int ].
     * @return array Enriched assets.
     */
    public function enrich_assets( array $assets ) {
        $enriched = array();
        $count    = 0;

        foreach ( $assets as $asset ) {
            if ( $count >= 20 ) {
                // Include remaining without enrichment.
                $enriched[] = array(
                    'unit'           => $asset['unit'],
                    'quantity'       => $asset['quantity'],
                    'policy_id'      => substr( $asset['unit'], 0, 56 ),
                    'asset_name'     => self::hex_to_ascii( substr( $asset['unit'], 56 ) ),
                    'asset_name_hex' => substr( $asset['unit'], 56 ),
                    'fingerprint'    => '',
                    'mint_quantity'  => 0,
                    'image'          => '',
                    'metadata'       => array(),
                );
                continue;
            }

            $info = $this->get_asset_info( $asset['unit'] );
            if ( is_wp_error( $info ) ) {
                $enriched[] = array(
                    'unit'           => $asset['unit'],
                    'quantity'       => $asset['quantity'],
                    'policy_id'      => substr( $asset['unit'], 0, 56 ),
                    'asset_name'     => self::hex_to_ascii( substr( $asset['unit'], 56 ) ),
                    'asset_name_hex' => substr( $asset['unit'], 56 ),
                    'fingerprint'    => '',
                    'mint_quantity'  => 0,
                    'image'          => '',
                    'metadata'       => array(),
                );
            } else {
                $info['quantity'] = $asset['quantity']; // Use held quantity, not total minted.
                $enriched[]       = $info;
            }
            $count++;
        }

        return $enriched;
    }

    /**
     * Convert hex-encoded asset name to ASCII.
     */
    public static function hex_to_ascii( $hex ) {
        if ( empty( $hex ) ) {
            return '';
        }
        $str = '';
        for ( $i = 0; $i < strlen( $hex ) - 1; $i += 2 ) {
            $char = chr( hexdec( substr( $hex, $i, 2 ) ) );
            // Only keep printable ASCII.
            $str .= ( ord( $char ) >= 32 && ord( $char ) <= 126 ) ? $char : '?';
        }
        return $str;
    }

    /**
     * Resolve an image URL from CIP-25 metadata.
     * Handles IPFS URIs, raw CIDs, and array-chunked strings.
     */
    public static function resolve_image( $image ) {
        if ( empty( $image ) ) {
            return '';
        }

        // CIP-25 allows chunked strings in arrays.
        if ( is_array( $image ) ) {
            $image = implode( '', $image );
        }

        return self::ipfs_to_http( $image );
    }

    /**
     * Convert IPFS URI or raw CID to HTTP gateway URL.
     */
    public static function ipfs_to_http( $uri ) {
        if ( empty( $uri ) ) {
            return '';
        }

        // ipfs:// protocol.
        if ( 0 === strpos( $uri, 'ipfs://' ) ) {
            $cid = substr( $uri, 7 );
            // Remove leading ipfs/ if doubled.
            if ( 0 === strpos( $cid, 'ipfs/' ) ) {
                $cid = substr( $cid, 5 );
            }
            return 'https://ipfs.io/ipfs/' . $cid;
        }

        // Raw CID (starts with Qm or bafy).
        if ( preg_match( '/^(Qm[a-zA-Z0-9]{44}|bafy[a-zA-Z0-9]+)/', $uri ) ) {
            return 'https://ipfs.io/ipfs/' . $uri;
        }

        // Already HTTP(S).
        return $uri;
    }

    /**
     * Flatten nested CIP-25 metadata into dot-notation key/value pairs.
     * Skips 'image' since we handle it separately.
     */
    public static function flatten_metadata( $data, $prefix = '' ) {
        $result = array();

        if ( ! is_array( $data ) ) {
            return $result;
        }

        foreach ( $data as $key => $value ) {
            // Skip image — handled separately.
            if ( '' === $prefix && 'image' === $key ) {
                continue;
            }

            $full_key = $prefix ? $prefix . '.' . $key : $key;

            if ( is_array( $value ) && ! isset( $value[0] ) ) {
                // Associative array — recurse.
                $result = array_merge( $result, self::flatten_metadata( $value, $full_key ) );
            } elseif ( is_array( $value ) ) {
                // Sequential array — join (CIP-25 chunked strings).
                $result[ $full_key ] = implode( '', $value );
            } else {
                $result[ $full_key ] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Make a GET request to the Blockfrost API.
     */
    private function request( $endpoint ) {
        $api_key = $this->settings->get_blockfrost_api_key();

        if ( empty( $api_key ) ) {
            return new WP_Error( 'weldpress_no_blockfrost_key', 'Blockfrost API key is not configured.' );
        }

        $base_url = $this->settings->get_blockfrost_base_url();

        $response = wp_remote_get(
            $base_url . $endpoint,
            array(
                'timeout' => 15,
                'headers' => array(
                    'project_id' => $api_key,
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
                'blockfrost_error',
                isset( $body['message'] ) ? $body['message'] : "Blockfrost returned HTTP {$code}",
                array( 'status' => $code )
            );
        }

        return $body;
    }
}
