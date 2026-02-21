<?php
/**
 * Settings accessor — reads WP options, provides typed getters.
 */

defined( 'ABSPATH' ) || exit;

class WeldPress_Settings {

    public function get_network() {
        $network = get_option( 'weldpress_network', 'preprod' );
        return in_array( $network, array( 'preprod', 'mainnet' ), true ) ? $network : 'preprod';
    }

    public function get_anvil_api_key() {
        $network = $this->get_network();
        $key     = get_option( "weldpress_anvil_key_{$network}", '' );
        return $this->maybe_decrypt( $key );
    }

    public function get_anvil_base_url() {
        $network = $this->get_network();
        return "https://{$network}.api.ada-anvil.app";
    }

    public function is_custodial_enabled() {
        return (bool) get_option( 'weldpress_custodial_enabled', false );
    }

    public function get_blockfrost_api_key() {
        $network = $this->get_network();
        $key     = get_option( "weldpress_blockfrost_key_{$network}", '' );
        return $this->maybe_decrypt( $key );
    }

    public function get_blockfrost_base_url() {
        $network = $this->get_network();
        if ( 'mainnet' === $network ) {
            return 'https://cardano-mainnet.blockfrost.io/api/v0';
        }
        return 'https://cardano-preprod.blockfrost.io/api/v0';
    }

    /**
     * Encrypt a value for storage using WP auth salts + sodium if available.
     */
    public static function encrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
            // Fallback: base64 encode (not truly secure, but better than plaintext).
            return 'b64:' . base64_encode( $value );
        }

        $key   = self::derive_key();
        $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $encrypted = sodium_crypto_secretbox( $value, $nonce, $key );

        sodium_memzero( $key );

        return 'enc:' . base64_encode( $nonce . $encrypted );
    }

    /**
     * Decrypt a stored value.
     */
    public static function decrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        // Base64 fallback.
        if ( 0 === strpos( $value, 'b64:' ) ) {
            return base64_decode( substr( $value, 4 ) );
        }

        // Sodium encrypted.
        if ( 0 === strpos( $value, 'enc:' ) ) {
            if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
                return ''; // Cannot decrypt without sodium.
            }

            $raw   = base64_decode( substr( $value, 4 ) );
            $nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $encrypted = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $key   = self::derive_key();

            $decrypted = sodium_crypto_secretbox_open( $encrypted, $nonce, $key );
            sodium_memzero( $key );

            return false !== $decrypted ? $decrypted : '';
        }

        // Plaintext (legacy or unencrypted).
        return $value;
    }

    private function maybe_decrypt( $value ) {
        return self::decrypt( $value );
    }

    /**
     * Derive a 32-byte encryption key from WP salts.
     */
    private static function derive_key() {
        $salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'weldpress-fallback-key';
        $salt .= defined( 'AUTH_SALT' ) ? AUTH_SALT : '';

        // If site defines a master key, use it.
        if ( defined( 'WELDPRESS_MASTER_KEY' ) ) {
            $salt = WELDPRESS_MASTER_KEY;
        }

        return hash( 'sha256', $salt, true );
    }
}
