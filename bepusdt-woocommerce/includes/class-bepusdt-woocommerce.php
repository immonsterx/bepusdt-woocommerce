<?php
/**
 * Plugin bootstrap.
 *
 * @package BEpusdt_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates WooCommerce hooks, assets, callbacks, and polling.
 */
final class BEpusdt_WooCommerce {
	/**
	 * Singleton instance.
	 *
	 * @var BEpusdt_WooCommerce|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return BEpusdt_WooCommerce
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'woocommerce_api_bepusdt_wc_notify', array( $this, 'handle_notify' ) );
		add_action( 'admin_post_bepusdt_wc_start_payment', array( $this, 'start_payment' ) );
		add_action( 'admin_post_nopriv_bepusdt_wc_start_payment', array( $this, 'start_payment' ) );
		add_action( 'wp_ajax_bepusdt_wc_check_order', array( $this, 'ajax_check_order' ) );
		add_action( 'wp_ajax_nopriv_bepusdt_wc_check_order', array( $this, 'ajax_check_order' ) );
		add_action( 'bepusdt_wc_poll_pending_orders', array( $this, 'poll_pending_orders' ) );
		add_shortcode( 'bepusdt_payment_button', array( $this, 'payment_button_shortcode' ) );
	}

	/**
	 * Register gateway class.
	 *
	 * @param array $gateways Gateway classes.
	 * @return array
	 */
	public function register_gateway( $gateways ) {
		$gateways[] = 'WC_Gateway_BEpusdt';
		return $gateways;
	}

	/**
	 * Add five-minute cron schedule.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_schedule( $schedules ) {
		if ( ! isset( $schedules['five_minutes'] ) ) {
			$schedules['five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every five minutes', 'bepusdt-woocommerce' ),
			);
		}

		return $schedules;
	}

	/**
	 * Load frontend CSS/JS only where WooCommerce payment details are likely shown.
	 */
	public function enqueue_frontend_assets() {
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}

		$should_load = is_checkout() || is_order_received_page() || is_wc_endpoint_url( 'order-pay' ) || is_account_page();

		if ( ! $should_load ) {
			return;
		}

		wp_enqueue_style(
			'bepusdt-wc-frontend',
			BEPUSDT_WC_URL . 'assets/css/frontend.css',
			array(),
			BEPUSDT_WC_VERSION
		);

		wp_enqueue_script(
			'bepusdt-wc-frontend',
			BEPUSDT_WC_URL . 'assets/js/frontend.js',
			array(),
			BEPUSDT_WC_VERSION,
			true
		);

		$gateway = $this->gateway();

		wp_localize_script(
			'bepusdt-wc-frontend',
			'bepusdtWc',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'locale'             => BEpusdt_WC_I18n::current_locale(),
				'unsupportedMessage' => __( 'This payment method is unavailable for the current address. Please choose USDT payment.', 'bepusdt-woocommerce' ),
				'unsupportedTemplate' => __( 'This address cannot use %s payment. Please choose USDT payment.', 'bepusdt-woocommerce' ),
				'guideHtml'          => $gateway ? $gateway->get_payment_guide_html() : '',
				'paidMessage'        => __( 'Payment confirmed. Refreshing order status...', 'bepusdt-woocommerce' ),
			)
		);
	}

	/**
	 * Handle BEpusdt async callback.
	 */
	public function handle_notify() {
		$payload = BEpusdt_WC_API::read_json_payload();
		$gateway = $this->gateway();

		if ( ! $gateway ) {
			status_header( 200 );
			echo 'fail';
			exit;
		}

		$api = new BEpusdt_WC_API( $gateway );

		if ( ! $api->verify_signature( $payload ) ) {
			$api->log( 'Callback signature verification failed.', $payload, 'warning' );
			status_header( 200 );
			echo 'fail';
			exit;
		}

		$order_id = isset( $payload['order_id'] ) ? absint( $payload['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order || 'bepusdt' !== $order->get_payment_method() ) {
			$api->log( 'Callback order not found or payment method mismatch.', $payload, 'warning' );
			status_header( 200 );
			echo 'fail';
			exit;
		}

		$this->sync_order_from_status( $order, $payload, $api );

		status_header( 200 );
		echo 'success';
		exit;
	}

	/**
	 * AJAX endpoint for frontend polling.
	 */
	public function ajax_check_order() {
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$nonce    = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $order_id || ! wp_verify_nonce( $nonce, 'bepusdt_wc_check_order_' . $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'bepusdt-woocommerce' ) ), 403 );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || 'bepusdt' !== $order->get_payment_method() ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'bepusdt-woocommerce' ) ), 404 );
		}

		$gateway = $this->gateway();
		$api     = $gateway ? new BEpusdt_WC_API( $gateway ) : null;

		if ( $api && $order->get_meta( '_bepusdt_trade_id' ) && $order->has_status( array( 'pending', 'on-hold' ) ) ) {
			$status = $api->check_status( $order->get_meta( '_bepusdt_trade_id' ) );
			if ( $status ) {
				$this->sync_order_from_status( $order, $status, $api );
			}
		}

		wp_send_json_success(
			array(
				'status'       => $order->get_status(),
				'is_paid'      => $order->is_paid(),
				'redirect_url' => $order->is_paid() ? $order->get_checkout_order_received_url() : '',
			)
		);
	}

	/**
	 * Create a cryptocurrency transaction for the selected trade type and redirect to checkout.
	 */
	public function start_payment() {
		$order_id   = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		$order_key  = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		$trade_type = isset( $_GET['trade_type'] ) ? sanitize_text_field( wp_unslash( $_GET['trade_type'] ) ) : '';
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		$order = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) || ! wp_verify_nonce( $nonce, 'bepusdt_wc_start_payment_' . $order_id ) ) {
			wp_die( esc_html__( 'Invalid payment request.', 'bepusdt-woocommerce' ) );
		}

		if ( 'bepusdt' !== $order->get_payment_method() || $order->is_paid() ) {
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		$gateway = $this->gateway();

		if ( ! $gateway || ! $gateway->is_trade_type_enabled( $trade_type ) ) {
			$this->redirect_with_payment_error( $order, __( 'Selected payment network is not available.', 'bepusdt-woocommerce' ) );
		}

		$api    = new BEpusdt_WC_API( $gateway );
		$result = $api->create_transaction( $order, $trade_type );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_payment_error( $order, $result->get_error_message() );
		}

		$payment_url = isset( $result['payment_url'] ) ? esc_url_raw( $result['payment_url'] ) : '';
		$trade_id    = isset( $result['trade_id'] ) ? sanitize_text_field( $result['trade_id'] ) : '';

		if ( ! $payment_url || ! $trade_id ) {
			$api->log( 'Create transaction response missing payment_url or trade_id.', $result, 'error' );
			$message = isset( $result['message'] ) ? sanitize_text_field( $result['message'] ) : __( 'Unable to create cryptocurrency payment. Please try again.', 'bepusdt-woocommerce' );
			$this->redirect_with_payment_error( $order, $message );
		}

		$order->update_meta_data( '_bepusdt_trade_type', $trade_type );
		$order->update_meta_data( '_bepusdt_trade_id', $trade_id );
		$order->update_meta_data( '_bepusdt_payment_url', $payment_url );
		$order->update_meta_data( '_bepusdt_token', isset( $result['token'] ) ? sanitize_text_field( $result['token'] ) : '' );
		$order->update_meta_data( '_bepusdt_actual_amount', isset( $result['actual_amount'] ) ? wc_format_decimal( $result['actual_amount'], 8 ) : '' );
		$order->update_meta_data( '_bepusdt_expiration_time', isset( $result['expiration_time'] ) ? sanitize_text_field( $result['expiration_time'] ) : '' );
		$order->add_order_note( sprintf( __( 'Cryptocurrency payment started on %s.', 'bepusdt-woocommerce' ), $gateway->get_trade_type_label( $trade_type ) ) );
		$order->save();

		wp_redirect( $payment_url );
		exit;
	}

	/**
	 * Store a payment error and redirect back to the WooCommerce payment page.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $message Error message.
	 */
	private function redirect_with_payment_error( $order, $message ) {
		$message = wp_strip_all_tags( (string) $message );

		$order->update_meta_data( '_bepusdt_payment_error', $message );
		$order->save();

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, 'error' );
		}

		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	/**
	 * Poll recent pending BEpusdt orders as a fallback when callbacks are delayed.
	 */
	public function poll_pending_orders() {
		$gateway = $this->gateway();

		if ( ! $gateway || 'yes' !== $gateway->get_option( 'polling_enabled', 'yes' ) ) {
			return;
		}

		$api = new BEpusdt_WC_API( $gateway );

		$orders = wc_get_orders(
			array(
				'limit'          => 20,
				'status'         => array( 'pending', 'on-hold' ),
				'payment_method' => 'bepusdt',
				'meta_key'       => '_bepusdt_trade_id',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'objects',
			)
		);

		foreach ( $orders as $order ) {
			$trade_id = $order->get_meta( '_bepusdt_trade_id' );
			if ( ! $trade_id ) {
				continue;
			}

			$status = $api->check_status( $trade_id );
			if ( $status ) {
				$this->sync_order_from_status( $order, $status, $api );
			}
		}
	}

	/**
	 * Sync WooCommerce order from BEpusdt status payload.
	 *
	 * @param WC_Order       $order Order object.
	 * @param array          $payload Status payload.
	 * @param BEpusdt_WC_API $api API client.
	 */
	public function sync_order_from_status( $order, $payload, $api ) {
		$status = isset( $payload['status'] ) ? absint( $payload['status'] ) : 0;

		if ( isset( $payload['trade_hash'] ) ) {
			$order->update_meta_data( '_bepusdt_trade_hash', sanitize_text_field( $payload['trade_hash'] ) );
		}

		if ( isset( $payload['block_transaction_id'] ) ) {
			$order->update_meta_data( '_bepusdt_block_transaction_id', sanitize_text_field( $payload['block_transaction_id'] ) );
		}

		if ( 2 === $status && ! $order->is_paid() ) {
			$transaction_id = $payload['block_transaction_id'] ?? $payload['trade_hash'] ?? $payload['trade_id'] ?? '';
			$order->payment_complete( sanitize_text_field( $transaction_id ) );
			$order->add_order_note( __( 'Cryptocurrency payment confirmed.', 'bepusdt-woocommerce' ) );
			$api->log( 'Payment confirmed.', $payload, 'info' );
		} elseif ( 3 === $status && $order->has_status( array( 'pending', 'on-hold' ) ) ) {
			$order->update_status( 'failed', __( 'Cryptocurrency payment expired.', 'bepusdt-woocommerce' ) );
			$api->log( 'Payment expired.', $payload, 'warning' );
		}

		$order->save();
	}

	/**
	 * Render a simple payment button shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function payment_button_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'order_id' => 0,
				'label'    => __( 'Pay with cryptocurrency', 'bepusdt-woocommerce' ),
			),
			$atts,
			'bepusdt_payment_button'
		);

		$order = wc_get_order( absint( $atts['order_id'] ) );

		if ( ! $order || 'bepusdt' !== $order->get_payment_method() ) {
			return '';
		}

		$url = $order->get_meta( '_bepusdt_payment_url' );
		if ( ! $url ) {
			return '';
		}

		return sprintf(
			'<a class="button bepusdt-wc-shortcode-button" href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( $url ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * Get configured gateway object.
	 *
	 * @return WC_Gateway_BEpusdt|null
	 */
	private function gateway() {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		return isset( $gateways['bepusdt'] ) && $gateways['bepusdt'] instanceof WC_Gateway_BEpusdt ? $gateways['bepusdt'] : null;
	}
}
