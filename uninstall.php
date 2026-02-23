<?php
/**
 * Weld Cardano uninstall — remove all plugin data.
 *
 * @package WeldPress
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove all plugin options.
$weldpress_options = array(
	'weldpress_network',
	'weldpress_anvil_key_preprod',
	'weldpress_anvil_key_mainnet',
	'weldpress_blockfrost_key_preprod',
	'weldpress_blockfrost_key_mainnet',
	'weldpress_custodial_enabled',
	'weldpress_version',
);

foreach ( $weldpress_options as $weldpress_option ) {
	delete_option( $weldpress_option );
}

// Remove all transients.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup on uninstall requires direct query.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_weldpress_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_weldpress_' ) . '%'
	)
);

// Drop the wallets custom table.
$weldpress_table = esc_sql( $wpdb->prefix . 'weldpress_wallets' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dropping plugin table on uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$weldpress_table}" );
