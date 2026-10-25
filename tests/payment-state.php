<?php
/**
 * Payment-attempt state checks. Run with: php tests/payment-state.php
 */

define( 'ABSPATH', __DIR__ );

function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return (string) $value; }
function wc_format_decimal( $value, $decimals = 2 ) { return number_format( (float) $value, $decimals, '.', '' ); }
function wc_get_price_decimals() { return 2; }
function esc_url_raw( $value ) { return (string) $value; }
$GLOBALS['bepusdt_test_stock_restores'] = 0;
function wc_maybe_increase_stock_levels() { ++$GLOBALS['bepusdt_test_stock_restores']; }
function __( $text ) { return $text; }

class BEpusdt_Test_State_Order {
	public $meta = array();
	public $status = 'on-hold';
	public $paid = false;
	public $transaction_id = '';
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function get_total() { return '28.88'; }
	public function get_currency() { return 'USD'; }
	public function get_id() { return 42; }
	public function is_paid() { return $this->paid; }
	public function payment_complete( $transaction_id ) { $this->paid = true; $this->transaction_id = $transaction_id; $this->status = 'processing'; }
	public function add_order_note() {}
	public function has_status( $statuses ) { return in_array( $this->status, (array) $statuses, true ); }
	public function update_status( $status ) { $this->status = $status; }
	public function save() {}
}

class BEpusdt_Test_State_Api {
	public $cancelled = array();
	public function log() {}
	public function cancel_transaction( $trade_id ) { $this->cancelled[] = $trade_id; return true; }
}

function bepusdt_state_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require dirname( __DIR__ ) . '/includes/class-bepusdt-woocommerce.php';

$reflection = new ReflectionClass( 'BEpusdt_WooCommerce' );
$plugin     = $reflection->newInstanceWithoutConstructor();
$api        = new BEpusdt_Test_State_Api();
$attempts   = array(
	'old-trade' => array(
		'trade_id' => 'old-trade', 'merchant_order_id' => '42-100-0001', 'amount' => '28.88',
		'currency' => 'USD', 'trade_type' => 'usdt.trc20', 'status' => 1,
	),
	'new-trade' => array(
		'trade_id' => 'new-trade', 'merchant_order_id' => '42-200-0002', 'amount' => '28.88',
		'currency' => 'USD', 'trade_type' => 'usdc.polygon', 'status' => 1,
	),
);

$order = new BEpusdt_Test_State_Order();
$order->meta['_bepusdt_trade_id'] = 'new-trade';
$order->meta['_bepusdt_payment_attempts'] = $attempts;

$synced = $plugin->sync_order_from_status( $order, array( 'trade_id' => 'old-trade', 'order_id' => '42-100-0001', 'status' => 3 ), $api );
bepusdt_state_assert( $synced, 'a known stale expiry callback should be acknowledged' );
bepusdt_state_assert( 'on-hold' === $order->status, 'an old expiry callback must not fail the current payment attempt' );

$expired = new BEpusdt_Test_State_Order();
$expired->meta['_bepusdt_trade_id'] = 'new-trade';
$expired->meta['_bepusdt_payment_attempts'] = $attempts;
$synced = $plugin->sync_order_from_status( $expired, array( 'trade_id' => 'new-trade', 'order_id' => '42-200-0002', 'status' => 3 ), $api );
bepusdt_state_assert( $synced && 'failed' === $expired->status, 'the current expired attempt must fail the order' );
bepusdt_state_assert( 1 === $GLOBALS['bepusdt_test_stock_restores'], 'expiry must restore stock on older supported WooCommerce versions' );

$synced = $plugin->sync_order_from_status( $order, array( 'trade_id' => 'old-trade', 'order_id' => '42-100-0001', 'amount' => '28.88', 'status' => 2, 'block_transaction_id' => '0xpaid' ), $api );
bepusdt_state_assert( $synced && $order->paid, 'a valid known attempt should be allowed to complete the order' );
bepusdt_state_assert( '0xpaid' === $order->transaction_id, 'the blockchain transaction ID must be stored' );
bepusdt_state_assert( in_array( 'new-trade', $api->cancelled, true ), 'a superseded active attempt should be cancelled after another known attempt pays' );

$unknown = new BEpusdt_Test_State_Order();
$unknown->meta['_bepusdt_trade_id'] = 'new-trade';
$unknown->meta['_bepusdt_payment_attempts'] = $attempts;
$synced = $plugin->sync_order_from_status( $unknown, array( 'trade_id' => 'attacker-trade', 'amount' => '28.88', 'status' => 2 ), $api );
bepusdt_state_assert( ! $synced && ! $unknown->paid, 'an unknown transaction must not complete the order' );

$mismatch = new BEpusdt_Test_State_Order();
$mismatch->meta['_bepusdt_trade_id'] = 'new-trade';
$mismatch->meta['_bepusdt_payment_attempts'] = $attempts;
$synced = $plugin->sync_order_from_status( $mismatch, array( 'trade_id' => 'new-trade', 'amount' => '1.00', 'status' => 2 ), $api );
bepusdt_state_assert( ! $synced && ! $mismatch->paid, 'a mismatched payment amount must not complete the order' );

$identity_mismatch = new BEpusdt_Test_State_Order();
$identity_mismatch->meta['_bepusdt_trade_id'] = 'new-trade';
$identity_mismatch->meta['_bepusdt_payment_attempts'] = $attempts;
$synced = $plugin->sync_order_from_status( $identity_mismatch, array( 'trade_id' => 'new-trade', 'order_id' => '42-wrong', 'amount' => '28.88', 'status' => 2 ), $api );
bepusdt_state_assert( ! $synced && ! $identity_mismatch->paid, 'trade ID and merchant order ID must identify the same attempt' );

echo "Payment state checks passed.\n";
