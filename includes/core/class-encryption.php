<?php
/**
 * Encryption helper for wallet key storage.
 * Uses AES-256-CBC with WordPress salts for key derivation.
 * Adapted from pbay-marketplace-cardano EncryptionHelper.
 */

defined( 'ABSPATH' ) || exit;

class WeldPress_Encryption {

    public static function encrypt( $plaintext ) {
        if ( empty( $plaintext ) ) {
            return '';
        }

        $key = self::derive_key();
        $iv  = openssl_random_pseudo_bytes( 16 );
        $encrypted = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $encrypted ) {
            return '';
        }

        return base64_encode( $iv . $encrypted );
    }

    public static function decrypt( $ciphertext ) {
        if ( empty( $ciphertext ) ) {
            return false;
        }

        $key  = self::derive_key();
        $data = base64_decode( $ciphertext, true );

        if ( false === $data || strlen( $data ) < 17 ) {
            return false;
        }

        $iv        = substr( $data, 0, 16 );
        $encrypted = substr( $data, 16 );
        $plaintext = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $plaintext ) {
            return false;
        }

        return $plaintext;
    }

    private static function derive_key() {
        $salt = '';

        if ( defined( 'WELDPRESS_MASTER_KEY' ) ) {
            $salt = WELDPRESS_MASTER_KEY;
        } else {
            $salt .= defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
            $salt .= defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
            $salt .= defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '';
            $salt .= defined( 'NONCE_KEY' ) ? NONCE_KEY : '';
        }

        return hash( 'sha256', $salt, true );
    }

    public static function test() {
        $test_data = 'weldpress_encryption_test_' . time();
        $encrypted = self::encrypt( $test_data );
        $decrypted = self::decrypt( $encrypted );
        return ( $decrypted === $test_data );
    }
}
