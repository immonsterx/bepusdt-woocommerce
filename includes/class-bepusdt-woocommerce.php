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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks_support' ) );
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
	 * Register the payment method with Cart and Checkout blocks.
	 */
	public function register_blocks_support() {
		if ( ! class_exists( '\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
			return;
		}

		require_once BEPUSDT_WC_PATH . 'includes/class-bepusdt-blocks-support.php';

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ) {
				$registry->register( new BEpusdt_WC_Blocks_Support() );
			}
		);
	}

	/**
	 * Load frontend CSS/JS only where WooCommerce payment details are likely shown.
	 */
	public function enqueue_frontend_assets() {
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}

		if ( ! $this->should_enqueue_frontend_assets() ) {
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
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'guideHtml'      => $gateway ? $gateway->get_payment_guide_html() : '',
				'paidMessage'    => __( 'Payment confirmed. Refreshing order status...', 'bepusdt-woocommerce' ),
				'expiredMessage' => __( 'The cryptocurrency payment expired. Choose a network to try again.', 'bepusdt-woocommerce' ),
			)
		);
	}

	/**
	 * Keep plugin assets away from unrelated WooCommerce pages.
	 *
	 * @return bool
	 */
	private function should_enqueue_frontend_assets() {
		if ( is_checkout() || is_order_received_page() || is_wc_endpoint_url( 'order-pay' ) ) {
			return true;
		}

		if ( is_singular() ) {
			global $post;

			return $post instanceof WP_Post && has_shortcode( $post->post_content, 'bepusdt_payment_button' );
		}

		return false;
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

		$synced = $this->sync_order_from_status( $order, $payload, $api );

		status_header( 200 );
		echo $synced ? 'success' : 'fail';
		exit;
	}

	/**
	 * AJAX endpoint for frontend polling.
	 */
	public function ajax_check_order() {
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$order_key = isset( $_POST['order_key'] ) ? wc_clean( wp_unslash( $_POST['order_key'] ) ) : '';
		$nonce    = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $order_id || ! wp_verify_nonce( $nonce, 'bepusdt_wc_check_order_' . $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'bepusdt-woocommerce' ) ), 403 );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) || 'bepusdt' !== $order->get_payment_method() ) {
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
				'should_poll'  => $order->has_status( array( 'pending', 'on-hold' ) ),
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

		if ( 'bepusdt' !== $order->get_payment_method() || ! $order->needs_payment() ) {
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		$gateway = $this->gateway();

		if ( ! $gateway || ! $gateway->is_trade_type_enabled( $trade_type ) ) {
			$this->redirect_with_payment_error( $order, __( 'Selected payment network is not available.', 'bepusdt-woocommerce' ) );
		}

		if ( ! in_array( strtoupper( $order->get_currency() ), $gateway->supported_fiat_currencies(), true ) ) {
			$this->redirect_with_payment_error( $order, __( 'The order currency is not supported by BEpusdt.', 'bepusdt-woocommerce' ) );
		}

		$current_trade_id = sanitize_text_field( $order->get_meta( '_bepusdt_trade_id' ) );
		$current_type     = sanitize_text_field( $order->get_meta( '_bepusdt_trade_type' ) );
		$current_url      = esc_url_raw( $order->get_meta( '_bepusdt_payment_url' ) );
		$current_expires  = absint( $order->get_meta( '_bepusdt_expires_at' ) );

		if ( $current_trade_id && $current_url && $trade_type === $current_type && $current_expires > time() + 30 ) {
			wp_redirect( $current_url );
			exit;
		}

		if ( ! $this->acquire_payment_lock( $order_id ) ) {
			$this->redirect_with_payment_error( $order, __( 'A cryptocurrency payment is already being created. Please wait a moment and try again.', 'bepusdt-woocommerce' ) );
		}

		$api    = new BEpusdt_WC_API( $gateway );
		$result = $api->create_transaction( $order, $trade_type );

		if ( is_wp_error( $result ) ) {
			$this->release_payment_lock( $order_id );
			$this->redirect_with_payment_error( $order, $result->get_error_message() );
		}

		$payment_url = isset( $result['payment_url'] ) ? esc_url_raw( $result['payment_url'] ) : '';
		$trade_id    = isset( $result['trade_id'] ) ? sanitize_text_field( $result['trade_id'] ) : '';

		if ( ! $payment_url || ! $trade_id ) {
			$api->log( 'Create transaction response missing payment_url or trade_id.', $result, 'error' );
			$this->release_payment_lock( $order_id );
			$this->redirect_with_payment_error( $order, __( 'Unable to create cryptocurrency payment. Please try again.', 'bepusdt-woocommerce' ) );
		}

		$duration          = isset( $result['expiration_time'] ) ? max( 120, absint( $result['expiration_time'] ) ) : max( 120, absint( $gateway->get_option( 'expires_in', 1200 ) ) );
		$expires_at        = time() + $duration;
		$merchant_order_id = isset( $result['_merchant_order_id'] ) ? sanitize_text_field( $result['_merchant_order_id'] ) : '';
		$attempt           = array(
			'trade_id'          => $trade_id,
			'merchant_order_id' => $merchant_order_id,
			'amount'            => wc_format_decimal( $order->get_total(), wc_get_price_decimals() ),
			'currency'          => strtoupper( $order->get_currency() ),
			'trade_type'        => $trade_type,
			'payment_url'       => $payment_url,
			'expires_at'        => $expires_at,
			'created_at'        => time(),
			'status'            => 1,
		);

		$this->remember_payment_attempt( $order, $attempt );

		$order->update_meta_data( '_bepusdt_trade_type', $trade_type );
		$order->update_meta_data( '_bepusdt_trade_id', $trade_id );
		$order->update_meta_data( '_bepusdt_merchant_order_id', $merchant_order_id );
		$order->update_meta_data( '_bepusdt_payment_url', $payment_url );
		$order->update_meta_data( '_bepusdt_token', isset( $result['token'] ) ? sanitize_text_field( $result['token'] ) : '' );
		$order->update_meta_data( '_bepusdt_actual_amount', isset( $result['actual_amount'] ) ? wc_format_decimal( $result['actual_amount'], 8 ) : '' );
		$order->update_meta_data( '_bepusdt_expiration_time', $duration );
		$order->update_meta_data( '_bepusdt_expires_at', $expires_at );
		$order->delete_meta_data( '_bepusdt_payment_error' );
		$order->set_status( 'on-hold' );
		$order->add_order_note( sprintf( __( 'Cryptocurrency payment started on %s.', 'bepusdt-woocommerce' ), $gateway->get_trade_type_label( $trade_type ) ) );
		$order->save();
		$this->release_payment_lock( $order_id );

		if ( $current_trade_id && $current_trade_id !== $trade_id ) {
			$api->cancel_transaction( $current_trade_id );
		}

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
		$page = max( 1, absint( get_option( 'bepusdt_wc_poll_page', 1 ) ) );

		$args = array(
				'limit'          => 50,
				'page'           => $page,
				'paginate'       => true,
				'status'         => array( 'pending', 'on-hold' ),
				'payment_method' => 'bepusdt',
				'meta_key'       => '_bepusdt_trade_id',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'return'         => 'objects',
			);
		$result = wc_get_orders( $args );

		if ( $page > 1 && empty( $result->orders ) ) {
			$args['page'] = 1;
			$page         = 1;
			$result       = wc_get_orders( $args );
		}

		$orders    = isset( $result->orders ) ? $result->orders : array();
		$next_page = isset( $result->max_num_pages ) && $page < absint( $result->max_num_pages ) ? $page + 1 : 1;
		update_option( 'bepusdt_wc_poll_page', $next_page, false );

		foreach ( $orders as $order ) {
			$trade_id = $order->get_meta( '_bepusdt_trade_id' );
			if ( ! $trade_id ) {
				continue;
			}

			$expires_at = absint( $order->get_meta( '_bepusdt_expires_at' ) );
			if ( $expires_at && time() > $expires_at + 5 * MINUTE_IN_SECONDS ) {
				$this->sync_order_from_status(
					$order,
					array(
						'trade_id' => $trade_id,
						'order_id' => $order->get_meta( '_bepusdt_merchant_order_id' ),
						'status'   => 3,
					),
					$api
				);
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
	 * @return bool Whether the payload belongs to a known payment attempt.
	 */
	public function sync_order_from_status( $order, $payload, $api ) {
		$status  = isset( $payload['status'] ) ? absint( $payload['status'] ) : 0;
		$attempt = $this->find_payment_attempt( $order, $payload );

		if ( ! in_array( $status, array( 1, 2, 3 ), true ) || ! $attempt ) {
			$api->log( 'Ignored status payload for an unknown payment attempt.', $payload, 'warning' );
			return false;
		}

		if ( 2 === $status && ! $this->payment_amount_matches( $attempt, $payload ) ) {
			$api->log( 'Ignored payment confirmation with an unexpected amount or currency.', $payload, 'error' );
			return false;
		}

		if ( 1 === $status && 1 === absint( $attempt['status'] ?? 0 ) ) {
			return true;
		}

		$attempt['status']     = $status;
		$attempt['updated_at'] = time();
		$this->remember_payment_attempt( $order, $attempt );

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

			$current_trade_id = sanitize_text_field( $order->get_meta( '_bepusdt_trade_id' ) );
			if ( $current_trade_id && $current_trade_id !== $attempt['trade_id'] ) {
				$api->cancel_transaction( $current_trade_id );
			}
		} elseif ( 3 === $status && $attempt['trade_id'] === $order->get_meta( '_bepusdt_trade_id' ) && $order->has_status( array( 'pending', 'on-hold' ) ) ) {
			$order->update_status( 'failed', __( 'Cryptocurrency payment expired.', 'bepusdt-woocommerce' ) );
			wc_maybe_increase_stock_levels( $order->get_id() );
			$api->log( 'Payment expired.', $payload, 'warning' );
		}

		$order->save();
		return true;
	}

	/**
	 * Keep a bounded history of payment attempts for callback validation.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $attempt Payment attempt.
	 */
	private function remember_payment_attempt( $order, $attempt ) {
		$trade_id = isset( $attempt['trade_id'] ) ? sanitize_text_field( $attempt['trade_id'] ) : '';
		if ( ! $trade_id ) {
			return;
		}

		$attempts = $order->get_meta( '_bepusdt_payment_attempts' );
		$attempts = is_array( $attempts ) ? $attempts : array();
		$attempts[ $trade_id ] = array(
			'trade_id'          => $trade_id,
			'merchant_order_id' => isset( $attempt['merchant_order_id'] ) ? sanitize_text_field( $attempt['merchant_order_id'] ) : '',
			'amount'            => isset( $attempt['amount'] ) ? wc_format_decimal( $attempt['amount'], wc_get_price_decimals() ) : '',
			'currency'          => isset( $attempt['currency'] ) ? strtoupper( sanitize_text_field( $attempt['currency'] ) ) : '',
			'trade_type'        => isset( $attempt['trade_type'] ) ? sanitize_text_field( $attempt['trade_type'] ) : '',
			'payment_url'       => isset( $attempt['payment_url'] ) ? esc_url_raw( $attempt['payment_url'] ) : '',
			'expires_at'        => isset( $attempt['expires_at'] ) ? absint( $attempt['expires_at'] ) : 0,
			'created_at'        => isset( $attempt['created_at'] ) ? absint( $attempt['created_at'] ) : time(),
			'updated_at'        => isset( $attempt['updated_at'] ) ? absint( $attempt['updated_at'] ) : time(),
			'status'            => isset( $attempt['status'] ) ? absint( $attempt['status'] ) : 1,
		);

		if ( count( $attempts ) > 10 ) {
			$attempts = array_slice( $attempts, -10, null, true );
		}

		$order->update_meta_data( '_bepusdt_payment_attempts', $attempts );
	}

	/**
	 * Resolve a callback or polling payload to a payment attempt.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $payload Status payload.
	 * @return array|false
	 */
	private function find_payment_attempt( $order, $payload ) {
		$trade_id         = isset( $payload['trade_id'] ) ? sanitize_text_field( $payload['trade_id'] ) : '';
		$merchant_order_id = isset( $payload['order_id'] ) ? sanitize_text_field( $payload['order_id'] ) : '';
		$attempts          = $order->get_meta( '_bepusdt_payment_attempts' );
		$attempts          = is_array( $attempts ) ? $attempts : array();

		if ( $trade_id && isset( $attempts[ $trade_id ] ) && is_array( $attempts[ $trade_id ] ) ) {
			if ( $merchant_order_id && ! empty( $attempts[ $trade_id ]['merchant_order_id'] ) && $merchant_order_id !== $attempts[ $trade_id ]['merchant_order_id'] ) {
				return false;
			}
			return $attempts[ $trade_id ];
		}

		if ( $merchant_order_id ) {
			foreach ( $attempts as $attempt ) {
				if ( is_array( $attempt ) && $merchant_order_id === ( $attempt['merchant_order_id'] ?? '' ) && ( ! $trade_id || $trade_id === ( $attempt['trade_id'] ?? '' ) ) ) {
					return $attempt;
				}
			}
		}

		$current_trade_id = sanitize_text_field( $order->get_meta( '_bepusdt_trade_id' ) );
		$current_order_id = sanitize_text_field( $order->get_meta( '_bepusdt_merchant_order_id' ) );
		$has_identifier = $trade_id || $merchant_order_id;
		$trade_matches  = ! $trade_id || $trade_id === $current_trade_id;
		$order_matches  = ! $merchant_order_id || ! $current_order_id || $merchant_order_id === $current_order_id;
		if ( $has_identifier && $trade_matches && $order_matches && ( $current_order_id || $trade_id ) ) {
			return array(
				'trade_id'          => $current_trade_id ?: $trade_id,
				'merchant_order_id' => $current_order_id ?: $merchant_order_id,
				'amount'            => wc_format_decimal( $order->get_total(), wc_get_price_decimals() ),
				'currency'          => strtoupper( $order->get_currency() ),
				'trade_type'        => sanitize_text_field( $order->get_meta( '_bepusdt_trade_type' ) ),
				'payment_url'       => esc_url_raw( $order->get_meta( '_bepusdt_payment_url' ) ),
				'expires_at'        => absint( $order->get_meta( '_bepusdt_expires_at' ) ),
				'created_at'        => 0,
				'status'            => 1,
			);
		}

		return false;
	}

	/**
	 * Confirm a successful status payload matches the recorded amount and fiat.
	 *
	 * @param array $attempt Recorded attempt.
	 * @param array $payload Status payload.
	 * @return bool
	 */
	private function payment_amount_matches( $attempt, $payload ) {
		if ( isset( $payload['amount'] ) && '' !== (string) $attempt['amount'] ) {
			$expected = (float) $attempt['amount'];
			$received = (float) $payload['amount'];
			if ( abs( $expected - $received ) > 0.0001 ) {
				return false;
			}
		}

		if ( ! empty( $payload['fiat'] ) && ! empty( $attempt['currency'] ) && strtoupper( sanitize_text_field( $payload['fiat'] ) ) !== $attempt['currency'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Acquire a short database-backed lock for payment creation.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	private function acquire_payment_lock( $order_id ) {
		$key       = 'bepusdt_wc_payment_lock_' . absint( $order_id );
		$timestamp = absint( get_option( $key, 0 ) );

		if ( $timestamp && $timestamp > time() - 30 ) {
			return false;
		}

		if ( $timestamp ) {
			delete_option( $key );
		}

		return add_option( $key, time(), '', false );
	}

	/**
	 * Release the payment creation lock.
	 *
	 * @param int $order_id Order ID.
	 */
	private function release_payment_lock( $order_id ) {
		delete_option( 'bepusdt_wc_payment_lock_' . absint( $order_id ) );
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

		$can_view = $order && ( current_user_can( 'manage_woocommerce' ) || ( $order->get_user_id() && get_current_user_id() === $order->get_user_id() ) );

		if ( ! $can_view || 'bepusdt' !== $order->get_payment_method() || ! $order->needs_payment() ) {
			return '';
		}

		$url = $order->get_meta( '_bepusdt_payment_url' );
		if ( ! $url ) {
			return '';
		}

		return sprintf(
			'<a class="button bepusdt-wc-shortcode-button" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
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
