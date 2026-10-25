<?php
/**
 * Plugin Name: BEpusdt for WooCommerce
 * Plugin URI: https://github.com/immonsterx/bepusdt-woocommerce
 * Description: 为 WooCommerce 提供轻量级 BEpusdt 加密货币支付网关。
 * Version: 1.5.0
 * Author: 我是怪兽
 * Author URI: https://guaishoux.com/
 * Text Domain: bepusdt-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * Update URI: https://github.com/immonsterx/bepusdt-woocommerce
 *
 * @package BEpusdt_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BEPUSDT_WC_VERSION', '1.5.0' );
define( 'BEPUSDT_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'BEPUSDT_WC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Register the plugin cron interval before activation hooks run.
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function bepusdt_wc_add_cron_schedule( $schedules ) {
	$schedules['bepusdt_wc_five_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every five minutes', 'bepusdt-woocommerce' ),
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'bepusdt_wc_add_cron_schedule' );

/**
 * Ensure the fallback order poller exists and migrate the legacy recurrence.
 */
function bepusdt_wc_ensure_cron_event() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$event = wp_get_scheduled_event( 'bepusdt_wc_poll_pending_orders' );

	if ( $event && 'bepusdt_wc_five_minutes' !== $event->schedule ) {
		wp_unschedule_event( $event->timestamp, 'bepusdt_wc_poll_pending_orders', $event->args );
		$event = false;
	}

	if ( ! $event ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'bepusdt_wc_five_minutes', 'bepusdt_wc_poll_pending_orders' );
	}
}
add_action( 'init', 'bepusdt_wc_ensure_cron_event' );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

require_once BEPUSDT_WC_PATH . 'includes/class-bepusdt-api.php';
require_once BEPUSDT_WC_PATH . 'includes/class-bepusdt-i18n.php';
require_once BEPUSDT_WC_PATH . 'includes/class-bepusdt-updater.php';
require_once BEPUSDT_WC_PATH . 'includes/class-bepusdt-woocommerce.php';

BEpusdt_WC_Updater::init();

add_action(
	'plugins_loaded',
	static function () {
		BEpusdt_WC_I18n::init();
		load_plugin_textdomain( 'bepusdt-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'BEpusdt for WooCommerce requires WooCommerce to be installed and active.', 'bepusdt-woocommerce' ) . '</p></div>';
				}
			);
			return;
		}

		require_once BEPUSDT_WC_PATH . 'includes/class-wc-gateway-bepusdt.php';
		BEpusdt_WooCommerce::instance();
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		bepusdt_wc_ensure_cron_event();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'bepusdt_wc_poll_pending_orders' );
	}
);
