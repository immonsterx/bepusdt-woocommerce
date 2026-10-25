<?php
/**
 * Optional plugin data cleanup.
 *
 * @package BEpusdt_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'woocommerce_bepusdt_settings', array() );

wp_clear_scheduled_hook( 'bepusdt_wc_poll_pending_orders' );
delete_option( 'bepusdt_wc_poll_page' );

global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'bepusdt_wc_payment_lock_' ) . '%'
	)
);

if ( is_array( $settings ) && 'yes' === ( $settings['delete_data_on_uninstall'] ?? 'no' ) ) {
	delete_option( 'woocommerce_bepusdt_settings' );
}
