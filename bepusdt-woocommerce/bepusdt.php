<?php
/**
 * Plugin Name: BEpusdt for WooCommerce
 * Plugin URI: https://github.com/immonsterx/bepusdt-woocommerce
 * Description: Adds a lightweight BEpusdt USDT payment gateway for WooCommerce.
 * Version: 1.1.9
 * Author: Monster
 * Author URI: https://guaishoux.com/
 * Text Domain: bepusdt-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 *
 * @package BEpusdt_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BEPUSDT_WC_VERSION', '1.1.9' );
define( 'BEPUSDT_WC_FILE', __FILE__ );
define( 'BEPUSDT_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'BEPUSDT_WC_URL', plugin_dir_url( __FILE__ ) );

require_once BEPUSDT_WC_PATH . 'includes/class-bepusdt-api.php';
require_once BEPUSDT_WC_PATH . 'includes/class-bepusdt-i18n.php';
require_once BEPUSDT_WC_PATH . 'includes/class-bepusdt-woocommerce.php';

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
		if ( ! wp_next_scheduled( 'bepusdt_wc_poll_pending_orders' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'five_minutes', 'bepusdt_wc_poll_pending_orders' );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'bepusdt_wc_poll_pending_orders' );
	}
);
