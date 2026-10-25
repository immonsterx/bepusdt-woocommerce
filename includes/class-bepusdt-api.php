<?php
/**
 * BEpusdt API client.
 *
 * @package BEpusdt_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small API wrapper for BEpusdt.
 */
class BEpusdt_WC_API {
	/**
	 * Gateway settings provider.
	 *
	 * @var WC_Gateway_BEpusdt
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Gateway_BEpusdt $gateway Gateway instance.
	 */
	public function __construct( $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Create a BEpusdt payment transaction.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array|WP_Error
	 */
	public function create_transaction( $order, $trade_type = '' ) {
		$timeout             = max( 120, absint( $this->gateway->get_option( 'expires_in', 1200 ) ) );
		$enabled_trade_types = $this->gateway->get_enabled_trade_types();
		$trade_type          = $trade_type ? sanitize_text_field( $trade_type ) : reset( $enabled_trade_types );

		if ( ! $trade_type || ! in_array( $trade_type, $enabled_trade_types, true ) ) {
			return new WP_Error( 'bepusdt_missing_trade_type', __( 'No cryptocurrency payment network is available.', 'bepusdt-woocommerce' ) );
		}

		$unique_suffix     = substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 );
		$merchant_order_id = sprintf( '%d-%d-%s', $order->get_id(), time(), $unique_suffix );

		$payload = array(
			'order_id'     => $merchant_order_id,
			'name'         => sprintf( __( 'WooCommerce Order #%s', 'bepusdt-woocommerce' ), $order->get_order_number() ),
			'amount'       => (float) $order->get_total(),
			'fiat'         => strtoupper( $order->get_currency() ),
			'trade_type'   => $trade_type,
			'notify_url'   => WC()->api_request_url( 'bepusdt_wc_notify' ),
			'redirect_url' => $order->get_checkout_order_received_url(),
			'timeout'      => $timeout,
		);

		$payload['signature'] = $this->sign( $payload );

		$result = $this->request( 'POST', '/api/v1/order/create-transaction', $payload );

		if ( is_array( $result ) ) {
			$result['_merchant_order_id'] = $merchant_order_id;
		}

		return $result;
	}

	/**
	 * Query a BEpusdt order status.
	 *
	 * @param string $trade_id BEpusdt trade ID.
	 * @return array|false
	 */
	public function check_status( $trade_id ) {
		$trade_id = sanitize_text_field( $trade_id );
		$result   = $this->request( 'POST', '/api/v1/pay/info', array( 'trade_id' => $trade_id ) );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Status query failed: ' . $result->get_error_message(), array( 'trade_id' => $trade_id ), 'warning' );
			return false;
		}

		if ( isset( $result['money'] ) && ! isset( $result['amount'] ) ) {
			$result['amount'] = $result['money'];
		}

		return $result;
	}

	/**
	 * Cancel a BEpusdt transaction that is no longer the active payment attempt.
	 *
	 * @param string $trade_id BEpusdt trade ID.
	 * @return bool
	 */
	public function cancel_transaction( $trade_id ) {
		$payload = array( 'trade_id' => sanitize_text_field( $trade_id ) );
		$payload['signature'] = $this->sign( $payload );
		$result = $this->request( 'POST', '/api/v1/order/cancel-transaction', $payload );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Unable to cancel superseded transaction.', array( 'trade_id' => $trade_id ), 'warning' );
			return false;
		}

		return true;
	}

	/**
	 * Verify BEpusdt callback signature.
	 *
	 * @param array $payload Callback payload.
	 * @return bool
	 */
	public function verify_signature( $payload ) {
		if ( empty( $payload['signature'] ) ) {
			return false;
		}

		$signature = (string) $payload['signature'];
		unset( $payload['signature'] );

		return hash_equals( $this->sign( $payload ), $signature );
	}

	/**
	 * Sign payload with BEpusdt/Epusdt rules.
	 *
	 * @param array $payload Payload.
	 * @return string
	 */
	public function sign( $payload ) {
		unset( $payload['signature'] );

		$payload = array_filter(
			$payload,
			static function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);

		ksort( $payload );

		$pieces = array();
		foreach ( $payload as $key => $value ) {
			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} elseif ( is_array( $value ) || is_object( $value ) ) {
				$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}

			$pieces[] = $key . '=' . $value;
		}

		return md5( implode( '&', $pieces ) . $this->gateway->get_api_token() );
	}

	/**
	 * Perform HTTP request.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path API path.
	 * @param array|null $payload Optional payload.
	 * @return array|WP_Error
	 */
	private function request( $method, $path, $payload = null ) {
		$base_url = untrailingslashit( $this->gateway->get_option( 'api_url', '' ) );

		if ( ! $base_url || ! wp_http_validate_url( $base_url ) ) {
			return new WP_Error( 'bepusdt_missing_api_url', __( 'BEpusdt API URL is invalid.', 'bepusdt-woocommerce' ) );
		}

		if ( ! $this->gateway->get_api_token() ) {
			return new WP_Error( 'bepusdt_missing_api_token', __( 'BEpusdt API token is missing.', 'bepusdt-woocommerce' ) );
		}

		$args = array(
			'method'      => $method,
			'timeout'     => 20,
			'redirection' => 2,
			'headers'     => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);

		if ( null !== $payload ) {
			$args['body'] = wp_json_encode( $payload );
		}

		$url      = $base_url . $path;
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'API request transport error: ' . $response->get_error_message(), $payload, 'error' );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( ! is_array( $json ) ) {
			$this->log( 'API returned invalid JSON.', array( 'code' => $code, 'body' => substr( $body, 0, 2000 ) ), 'error' );
			return new WP_Error( 'bepusdt_invalid_json', __( 'BEpusdt returned invalid JSON.', 'bepusdt-woocommerce' ) );
		}

		$status_code = isset( $json['status_code'] ) ? absint( $json['status_code'] ) : 200;
		$http_ok     = $code >= 200 && $code < 300;
		$api_ok      = 200 === $status_code;

		if ( ! $http_ok || ! $api_ok ) {
			$this->log( 'API request failed.', $json, 'error' );
			return new WP_Error( 'bepusdt_api_failed', __( 'BEpusdt request failed.', 'bepusdt-woocommerce' ) );
		}

		$data = isset( $json['data'] ) && is_array( $json['data'] ) ? $json['data'] : $json;
		$this->log( 'API request succeeded.', $data, 'debug' );

		return $data;
	}

	/**
	 * Read JSON request body.
	 *
	 * @return array
	 */
	public static function read_json_payload() {
		$raw  = file_get_contents( 'php://input' );
		$data = json_decode( $raw, true );

		if ( is_array( $data ) ) {
			return wp_unslash( $data );
		}

		return array();
	}

	/**
	 * Encrypt an API token before storing it in options.
	 *
	 * @param string $value Plain token.
	 * @return string
	 */
	public static function encrypt_secret( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value || self::is_encrypted_secret( $value ) ) {
			return $value;
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$key = hash( 'sha256', wp_salt( 'auth' ), true );
			try {
				$iv = random_bytes( 16 );
			} catch ( Exception $exception ) {
				$iv = wp_generate_password( 16, false, false );
			}
			$raw = openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

			if ( false !== $raw ) {
				return 'enc:' . base64_encode( $iv . $raw );
			}
		}

		return 'base64:' . base64_encode( $value );
	}

	/**
	 * Decrypt stored API token.
	 *
	 * @param string $value Stored token.
	 * @return string
	 */
	public static function decrypt_secret( $value ) {
		$value = (string) $value;

		if ( 0 === strpos( $value, 'enc:' ) && function_exists( 'openssl_decrypt' ) ) {
			$decoded = base64_decode( substr( $value, 4 ), true );

			if ( false !== $decoded && strlen( $decoded ) > 16 ) {
				$iv  = substr( $decoded, 0, 16 );
				$raw = substr( $decoded, 16 );
				$key = hash( 'sha256', wp_salt( 'auth' ), true );
				$out = openssl_decrypt( $raw, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

				if ( false !== $out ) {
					return $out;
				}
			}
		}

		if ( 0 === strpos( $value, 'base64:' ) ) {
			$decoded = base64_decode( substr( $value, 7 ), true );
			return false === $decoded ? '' : $decoded;
		}

		return $value;
	}

	/**
	 * Check whether a value already has an encryption marker.
	 *
	 * @param string $value Stored value.
	 * @return bool
	 */
	public static function is_encrypted_secret( $value ) {
		return 0 === strpos( (string) $value, 'enc:' ) || 0 === strpos( (string) $value, 'base64:' );
	}

	/**
	 * Log a message using WooCommerce logger with redaction.
	 *
	 * @param string $message Log message.
	 * @param mixed  $context Context.
	 * @param string $level Log level.
	 */
	public function log( $message, $context = array(), $level = 'info' ) {
		if ( 'yes' !== $this->gateway->get_option( 'debug', 'no' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->log(
			$level,
			$message . ' ' . wp_json_encode( $this->redact( $context ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			array( 'source' => 'bepusdt-woocommerce' )
		);
	}

	/**
	 * Redact sensitive values from logs.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private function redact( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				if ( preg_match( '/token|secret|key|signature/i', (string) $key ) ) {
					$value[ $key ] = '***';
				} else {
					$value[ $key ] = $this->redact( $item );
				}
			}
		} elseif ( is_string( $value ) ) {
			$value = preg_replace( '/([?&](?:key|_wpnonce|token|secret|signature)=)[^&\s]+/i', '$1***', $value );
			$value = preg_replace( '/("?(?:token|secret|key|signature)"?\s*[:=]\s*")[^"]+/i', '$1***', $value );
		}

		return $value;
	}
}
