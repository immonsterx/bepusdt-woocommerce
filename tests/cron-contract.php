<?php
/**
 * Activation-time cron registration checks. Run with: php tests/cron-contract.php
 */

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['bepusdt_test_schedule'] = null;

class WooCommerce {}

function __( $text ) { return $text; }
function add_filter() {}
function add_action() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function plugin_dir_path() { return dirname( __DIR__ ) . '/'; }
function plugin_dir_url() { return 'https://shop.example/wp-content/plugins/bepusdt-woocommerce/'; }
function wp_get_scheduled_event() { return false; }
function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	$GLOBALS['bepusdt_test_schedule'] = compact( 'timestamp', 'recurrence', 'hook' );
	return true;
}

require dirname( __DIR__ ) . '/bepusdt.php';

$schedules = bepusdt_wc_add_cron_schedule( array() );
if ( empty( $schedules['bepusdt_wc_five_minutes'] ) || 300 !== $schedules['bepusdt_wc_five_minutes']['interval'] ) {
	fwrite( STDERR, "FAIL: the namespaced five-minute recurrence was not registered.\n" );
	exit( 1 );
}

bepusdt_wc_ensure_cron_event();
if ( 'bepusdt_wc_five_minutes' !== $GLOBALS['bepusdt_test_schedule']['recurrence'] || 'bepusdt_wc_poll_pending_orders' !== $GLOBALS['bepusdt_test_schedule']['hook'] ) {
	fwrite( STDERR, "FAIL: activation did not schedule the poller with the registered recurrence.\n" );
	exit( 1 );
}

echo "Cron activation contract checks passed.\n";
