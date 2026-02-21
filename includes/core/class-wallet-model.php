<?php
/**
 * Wallet database model — CRUD for server-side custodial wallets.
 * Custom table with encrypted mnemonic/skey, archive lifecycle.
 * Adapted from pbay-marketplace-cardano PolicyWalletModel.
 */

defined( 'ABSPATH' ) || exit;

class WeldPress_Wallet_Model {

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'weldpress_wallets';
    }

    /**
     * Create the wallets table (called on plugin activation).
     */
    public static function create_table() {
        global $wpdb;
        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id int(11) unsigned NOT NULL AUTO_INCREMENT,
            wallet_name varchar(255) NOT NULL DEFAULT 'Default Wallet',
            mnemonic_encrypted TEXT NOT NULL,
            skey_encrypted TEXT NOT NULL,
            payment_address varchar(128) NOT NULL,
            payment_keyhash varchar(64) NOT NULL,
            stake_address varchar(128) DEFAULT '',
            network varchar(20) NOT NULL DEFAULT 'preprod',
            archived tinyint(1) NOT NULL DEFAULT 0,
            archived_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_network (network),
            INDEX idx_archived (archived)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get the active (non-archived) wallet for a network.
     */
    public static function get_active_wallet( $network = 'preprod' ) {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE network = %s AND archived = 0 ORDER BY id DESC LIMIT 1",
                $network
            ),
            ARRAY_A
        );
    }

    /**
     * Get wallet by ID.
     */
    public static function get_by_id( $id ) {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ),
            ARRAY_A
        );
    }

    /**
     * Insert a new wallet.
     */
    public static function insert( $data ) {
        global $wpdb;
        $table = self::table_name();

        $wpdb->insert( $table, array(
            'wallet_name'        => $data['wallet_name'] ?? 'Default Wallet',
            'mnemonic_encrypted' => $data['mnemonic_encrypted'],
            'skey_encrypted'     => $data['skey_encrypted'],
            'payment_address'    => $data['payment_address'],
            'payment_keyhash'    => $data['payment_keyhash'],
            'stake_address'      => $data['stake_address'] ?? '',
            'network'            => $data['network'],
            'created_at'         => current_time( 'mysql' ),
        ) );

        return $wpdb->insert_id;
    }

    /**
     * Delete a wallet permanently.
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->delete( $table, array( 'id' => absint( $id ) ) );
    }

    /**
     * Archive a wallet (soft-delete).
     */
    public static function archive( $id ) {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->update(
            $table,
            array( 'archived' => 1, 'archived_at' => current_time( 'mysql' ) ),
            array( 'id' => absint( $id ) ),
            array( '%d', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Unarchive a wallet — fails if another active wallet exists for the network.
     */
    public static function unarchive( $id ) {
        global $wpdb;
        $table = self::table_name();

        $wallet = self::get_by_id( $id );
        if ( ! $wallet ) {
            return new WP_Error( 'wallet_not_found', 'Wallet not found.' );
        }

        $active = self::get_active_wallet( $wallet['network'] );
        if ( $active && $active['id'] != $id ) {
            return new WP_Error( 'active_wallet_exists', 'Another wallet is already active for ' . $wallet['network'] . '. Archive it first.' );
        }

        return $wpdb->update(
            $table,
            array( 'archived' => 0, 'archived_at' => null ),
            array( 'id' => absint( $id ) ),
            array( '%d', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Get all archived wallets, optionally filtered by network.
     */
    public static function get_archived( $network = null ) {
        global $wpdb;
        $table = self::table_name();

        if ( $network ) {
            return $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM $table WHERE archived = 1 AND network = %s ORDER BY archived_at DESC", $network ),
                ARRAY_A
            );
        }

        return $wpdb->get_results( "SELECT * FROM $table WHERE archived = 1 ORDER BY archived_at DESC", ARRAY_A );
    }
}
