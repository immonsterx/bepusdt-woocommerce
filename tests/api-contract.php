<?php
/**
 * Lightweight API contract checks. Run with: php tests/api-contract.php
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['bepusdt_test_request'] = array();

class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

function __( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return (string) $value; }
function wp_generate_uuid4() { return '12345678-1234-1234-1234-123456789abc'; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function wp_http_validate_url( $value ) { return filter_var( $value, FILTER_VALIDATE_URL ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_request( $url, $args ) {
	$GLOBALS['bepusdt_test_request'] = array( 'url' => $url, 'args' => $args );
	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'status_code' => 200, 'data' => array( 'trade_id' => 'trade-test', 'payment_url' => 'https://pay.example/pay/checkout/trade-test', 'money' => '28.88', 'status' => 1 ) ) ) );
}
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_salt() { return 'test-salt'; }
function wp_generate_password( $length ) { return str_repeat( 'x', $length ); }

class BEpusdt_Test_WC {
	public function api_request_url() { return 'https://shop.example/wc-api/bepusdt_wc_notify'; }
}
function WC() { return new BEpusdt_Test_WC(); }

class BEpusdt_Test_Gateway {
	public function get_option( $key, $default = '' ) {
		$options = array( 'expires_in' => 1200, 'api_url' => 'https://pay.example', 'debug' => 'no' );
		return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}
	public function get_enabled_trade_types() { return array( 'usdt.trc20' ); }
	public function get_api_token() { return 'secret'; }
}

class BEpusdt_Test_Order {
	public function get_id() { return 42; }
	public function get_order_number() { return 'WC-42'; }
	public function get_total() { return '28.88'; }
	public function get_currency() { return 'USD'; }
	public function get_checkout_order_received_url() { return 'https://shop.example/order-received/42/?key=wc_order_secret'; }
}

function bepusdt_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require dirname( __DIR__ ) . '/includes/class-bepusdt-api.php';

$api    = new BEpusdt_WC_API( new BEpusdt_Test_Gateway() );
$result = $api->create_transaction( new BEpusdt_Test_Order(), 'usdt.trc20' );
$body   = json_decode( $GLOBALS['bepusdt_test_request']['args']['body'], true );

bepusdt_test_assert( 'POST' === $GLOBALS['bepusdt_test_request']['args']['method'], 'create transaction must use POST' );
bepusdt_test_assert( 'https://pay.example/api/v1/order/create-transaction' === $GLOBALS['bepusdt_test_request']['url'], 'create transaction endpoint must match the official API' );
bepusdt_test_assert( 'USD' === $body['fiat'], 'the WooCommerce order currency must be sent' );
bepusdt_test_assert( 1 === preg_match( '/^42-\d+-12345678$/', $body['order_id'] ), 'merchant order IDs must include a collision-resistant suffix' );
bepusdt_test_assert( $body['order_id'] === $result['_merchant_order_id'], 'the exact merchant order ID must be retained for callback validation' );

$status = $api->check_status( 'trade-test' );
$body   = json_decode( $GLOBALS['bepusdt_test_request']['args']['body'], true );

bepusdt_test_assert( 'POST' === $GLOBALS['bepusdt_test_request']['args']['method'], 'status checks must use POST' );
bepusdt_test_assert( 'https://pay.example/api/v1/pay/info' === $GLOBALS['bepusdt_test_request']['url'], 'status checks must use the current BEpusdt endpoint' );
bepusdt_test_assert( 'trade-test' === $body['trade_id'], 'status checks must send the trade ID' );
bepusdt_test_assert( '28.88' === $status['amount'], 'the current API money field must be normalized to amount' );

echo "API contract checks passed.\n";
