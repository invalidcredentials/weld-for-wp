<?php
/**
 * Weld for WP uninstall — remove all plugin data.
 *
 * @package WeldPress
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove all plugin options.
$options = array(
    'weldpress_network',
    'weldpress_anvil_key_preprod',
    'weldpress_anvil_key_mainnet',
    'weldpress_blockfrost_key_preprod',
    'weldpress_blockfrost_key_mainnet',
    'weldpress_custodial_enabled',
    'weldpress_version',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove all transients.
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_weldpress_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_weldpress_' ) . '%'
    )
);

// Drop the wallets custom table.
$table = $wpdb->prefix . 'weldpress_wallets';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
