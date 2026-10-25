<?php
/**
 * WooCommerce Cart and Checkout blocks integration.
 *
 * @package BEpusdt_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Exposes the classic gateway to WooCommerce Checkout blocks.
 */
final class BEpusdt_WC_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'bepusdt';

	/**
	 * Gateway instance.
	 *
	 * @var WC_Gateway_BEpusdt|null
	 */
	private $gateway;

	/**
	 * Load gateway settings.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_bepusdt_settings', array() );
		$gateways       = WC()->payment_gateways()->payment_gateways();
		$this->gateway  = isset( $gateways['bepusdt'] ) && $gateways['bepusdt'] instanceof WC_Gateway_BEpusdt ? $gateways['bepusdt'] : null;
	}

	/**
	 * Whether the gateway can be offered for the current checkout.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->gateway && $this->gateway->is_available();
	}

	/**
	 * Register the block payment method script.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'bepusdt-wc-blocks',
			BEPUSDT_WC_URL . 'assets/js/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			BEPUSDT_WC_VERSION,
			true
		);

		wp_set_script_translations( 'bepusdt-wc-blocks', 'bepusdt-woocommerce', BEPUSDT_WC_PATH . 'languages' );

		return array( 'bepusdt-wc-blocks' );
	}

	/**
	 * Frontend settings exposed through wcSettings.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		if ( ! $this->gateway ) {
			return array();
		}

		return array(
			'title'       => $this->gateway->get_title(),
			'description' => $this->gateway->get_description(),
			'guideHtml'   => $this->gateway->get_payment_guide_html(),
			'iconUrl'     => add_query_arg( 'ver', BEPUSDT_WC_VERSION, BEPUSDT_WC_URL . 'assets/images/crypto-pay.svg' ),
			'icon'        => $this->gateway->get_icon_display_settings(),
			'supports'    => array_filter(
				$this->gateway->supports,
				array( $this->gateway, 'supports' )
			),
		);
	}
}
