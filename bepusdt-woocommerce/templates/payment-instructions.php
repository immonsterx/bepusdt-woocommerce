<?php
/**
 * BEpusdt payment instructions template.
 *
 * @var WC_Gateway_BEpusdt $this Gateway.
 * @var WC_Order           $order Order.
 *
 * @package BEpusdt_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$order_id        = $order->get_id();
$trade_id        = $order->get_meta( '_bepusdt_trade_id' );
$payment_error   = $order->get_meta( '_bepusdt_payment_error' );
$nonce           = wp_create_nonce( 'bepusdt_wc_check_order_' . $order_id );
$start_nonce     = wp_create_nonce( 'bepusdt_wc_start_payment_' . $order_id );
$enabled_chains  = $this->get_enabled_trade_types();

if ( $payment_error ) {
	$order->delete_meta_data( '_bepusdt_payment_error' );
	$order->save();
}
?>

<section class="bepusdt-wc-payment bepusdt-wc-payment--order-pay" data-bepusdt-payment data-order-id="<?php echo esc_attr( $order_id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
	<div class="bepusdt-wc-payment__main">
		<h2 class="bepusdt-wc-payment__title"><?php esc_html_e( 'Choose a Payment Network', 'bepusdt-woocommerce' ); ?></h2>
		<?php if ( $payment_error ) : ?>
			<div class="bepusdt-wc-payment__error" role="alert">
				<?php echo esc_html( $payment_error ); ?>
			</div>
		<?php endif; ?>
		<p class="bepusdt-wc-payment__text">
			<?php esc_html_e( 'Your order has been created. Select the network or token you want to pay with, then you will be redirected to the secure cryptocurrency checkout page.', 'bepusdt-woocommerce' ); ?>
		</p>

		<dl class="bepusdt-wc-payment__details">
			<div>
				<dt><?php esc_html_e( 'Order Number', 'bepusdt-woocommerce' ); ?></dt>
				<dd><?php echo esc_html( $order->get_order_number() ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Order Date', 'bepusdt-woocommerce' ); ?></dt>
				<dd><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Order Total', 'bepusdt-woocommerce' ); ?></dt>
				<dd><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Payment Method', 'bepusdt-woocommerce' ); ?></dt>
				<dd><?php echo esc_html( $order->get_payment_method_title() ? $order->get_payment_method_title() : __( 'Crypto Payment', 'bepusdt-woocommerce' ) ); ?></dd>
			</div>
			<?php if ( $trade_id ) : ?>
				<div>
					<dt><?php esc_html_e( 'Trade ID', 'bepusdt-woocommerce' ); ?></dt>
					<dd><?php echo esc_html( $trade_id ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>
	</div>

	<div class="bepusdt-wc-chain-panel">
		<p class="bepusdt-wc-chain-panel__title"><?php esc_html_e( 'Payment Network', 'bepusdt-woocommerce' ); ?></p>
		<div class="bepusdt-wc-chain-grid">
			<?php foreach ( $enabled_chains as $chain ) : ?>
				<?php
				$url = add_query_arg(
					array(
						'action'     => 'bepusdt_wc_start_payment',
						'order_id'   => $order_id,
						'key'        => $order->get_order_key(),
						'trade_type' => $chain,
						'_wpnonce'   => $start_nonce,
					),
					admin_url( 'admin-post.php' )
				);
				?>
				<a class="bepusdt-wc-chain-button" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
					<span class="bepusdt-wc-chain-button__name"><?php echo esc_html( $this->format_trade_type_button_label( $chain ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
		<p class="bepusdt-wc-payment__status" data-bepusdt-status aria-live="polite">
			<?php esc_html_e( 'After payment, this page will update automatically when the transaction is confirmed.', 'bepusdt-woocommerce' ); ?>
		</p>
	</div>
</section>
