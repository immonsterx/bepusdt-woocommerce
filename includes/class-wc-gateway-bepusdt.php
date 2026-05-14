<?php
/**
 * WooCommerce gateway implementation.
 *
 * @package BEpusdt_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BEpusdt WooCommerce payment gateway.
 */
class WC_Gateway_BEpusdt extends WC_Payment_Gateway {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'bepusdt';
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = __( 'BEpusdt Crypto', 'bepusdt-woocommerce' );
		$this->method_description = __( 'Accept cryptocurrency payments through a BEpusdt backend.', 'bepusdt-woocommerce' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_setting_preserve_empty( 'title', __( 'Crypto Payment', 'bepusdt-woocommerce' ) );
		$this->description = $this->get_setting_preserve_empty( 'description', '' );
		$this->enabled     = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
	}

	/**
	 * Gateway settings.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'bepusdt-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable BEpusdt cryptocurrency payment', 'bepusdt-woocommerce' ),
				'default' => 'yes',
			),
			'title'              => array(
				'title'       => __( 'Title', 'bepusdt-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Shown to customers during checkout. Leave blank to hide it on the frontend.', 'bepusdt-woocommerce' ),
				'default'     => __( 'Crypto Payment', 'bepusdt-woocommerce' ),
				'placeholder' => __( 'Crypto Payment', 'bepusdt-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Description', 'bepusdt-woocommerce' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => __( 'Shown under the payment method on checkout. Leave blank to hide it on the frontend.', 'bepusdt-woocommerce' ),
				'placeholder' => __( 'Optional checkout description.', 'bepusdt-woocommerce' ),
				'desc_tip'    => true,
			),
			'api_url'            => array(
				'title'       => __( 'BEpusdt API URL', 'bepusdt-woocommerce' ),
				'type'        => 'url',
				'description' => __( 'Example: https://pay.example.com', 'bepusdt-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'api_token'          => array(
				'title'       => __( 'API Token / Secret', 'bepusdt-woocommerce' ),
				'type'        => 'api_token',
				'description' => __( 'Stored encrypted using WordPress salts when possible.', 'bepusdt-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'enabled_trade_types' => array(
				'title'       => __( 'Frontend Payment Buttons', 'bepusdt-woocommerce' ),
				'type'        => 'multiselect',
				'default'     => array( 'usdt.trc20', 'usdt.polygon', 'usdt.erc20' ),
				'options'     => $this->trade_type_options(),
				'description' => __( 'Choose which BEpusdt trade types customers can click on the payment page.', 'bepusdt-woocommerce' ),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
			),
			'expires_in'         => array(
				'title'             => __( 'Payment Expiration', 'bepusdt-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'Expiration time in seconds.', 'bepusdt-woocommerce' ),
				'default'           => 1200,
				'custom_attributes' => array(
					'min'  => 120,
					'step' => 60,
				),
				'desc_tip'          => true,
			),
			'payment_guide_html' => array(
				'title'       => __( 'Payment Guide HTML', 'bepusdt-woocommerce' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => sprintf(
					/* translators: %s: example HTML. */
					__( 'Used to guide customers to a payment tutorial. Leave blank to hide it. Example: %s', 'bepusdt-woocommerce' ),
					'<code>&lt;a href=&quot;https://yourdomain.com&quot; target=&quot;_blank&quot; rel=&quot;noopener noreferrer&quot;&gt;USDT payment tutorial&lt;/a&gt;</code>'
				),
				'css'         => 'min-height: 100px;',
			),
			'polling_enabled'    => array(
				'title'   => __( 'Automatic Status Polling', 'bepusdt-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Periodically query pending BEpusdt orders', 'bepusdt-woocommerce' ),
				'default' => 'yes',
			),
			'debug'              => array(
				'title'       => __( 'Debug Log', 'bepusdt-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable debug logging', 'bepusdt-woocommerce' ),
				'default'     => 'no',
				'description' => __( 'Logs are written through WooCommerce logs with sensitive values redacted.', 'bepusdt-woocommerce' ),
			),
		);
	}

	/**
	 * Payment fields shown at checkout.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}

		?>
		<div class="bepusdt-wc-checkout" data-bepusdt-checkout>
			<div class="bepusdt-wc-usdt-option" role="group" aria-label="<?php esc_attr_e( 'USDT payment option', 'bepusdt-woocommerce' ); ?>">
				<div class="bepusdt-wc-method-grid">
					<button type="button" class="bepusdt-wc-method bepusdt-wc-method--image bepusdt-wc-method--active" data-bepusdt-primary-method aria-label="<?php esc_attr_e( 'USDT', 'bepusdt-woocommerce' ); ?>" aria-pressed="true">
						<span class="bepusdt-wc-method-card">
							<img src="<?php echo esc_url( BEPUSDT_WC_URL . 'assets/images/usdt.svg' ); ?>" alt="" loading="lazy" />
						</span>
					</button>
				</div>
			</div>
			<div class="bepusdt-wc-notice" data-bepusdt-notice hidden></div>
		</div>
		<?php
	}

	/**
	 * Process payment by creating a BEpusdt transaction.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Order not found.', 'bepusdt-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_status( 'pending', __( 'Awaiting customer to choose a payment network.', 'bepusdt-woocommerce' ) );
		$order->save();

		wc_reduce_stock_levels( $order_id );
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Thank-you page output.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {
		$this->render_payment_instructions( $order_id );
	}

	/**
	 * Receipt page output.
	 *
	 * @param int $order_id Order ID.
	 */
	public function receipt_page( $order_id ) {
		$this->render_payment_instructions( $order_id );
	}

	/**
	 * Render front-end payment instructions.
	 *
	 * @param int $order_id Order ID.
	 */
	public function render_payment_instructions( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || 'bepusdt' !== $order->get_payment_method() || $order->is_paid() ) {
			return;
		}

		$template = BEPUSDT_WC_PATH . 'templates/payment-instructions.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Keep encrypted token when field is left blank.
	 *
	 * @param string $key Field key.
	 * @param string $value Submitted value.
	 * @return string
	 */
	public function validate_api_token_field( $key, $value ) {
		$value    = trim( (string) $value );
		$existing = $this->get_option( 'api_token', '' );

		if ( ( '' === $value || preg_match( '/^\*+$/', $value ) ) && $existing ) {
			return $existing;
		}

		return BEpusdt_WC_API::encrypt_secret( $value );
	}

	/**
	 * Save an empty checkout title as an intentional blank value.
	 *
	 * @param string $key Field key.
	 * @param string $value Submitted value.
	 * @return string
	 */
	public function validate_title_field( $key, $value ) {
		return wc_clean( wp_unslash( (string) $value ) );
	}

	/**
	 * Save an empty checkout description as an intentional blank value.
	 *
	 * @param string $key Field key.
	 * @param string $value Submitted value.
	 * @return string
	 */
	public function validate_description_field( $key, $value ) {
		return wp_kses_post( wp_unslash( (string) $value ) );
	}

	/**
	 * Persist blank checkout copy fields instead of falling back to old values.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();

		$this->force_save_blank_copy_field( 'title' );
		$this->force_save_blank_copy_field( 'description' );

		$this->title       = $this->get_setting_preserve_empty( 'title', __( 'Crypto Payment', 'bepusdt-woocommerce' ) );
		$this->description = $this->get_setting_preserve_empty( 'description', '' );

		return $saved;
	}

	/**
	 * Render token field without exposing stored encrypted content.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_api_token_html( $key, $data ) {
		$field_key   = $this->get_field_key( $key );
		$defaults    = array(
			'title'       => '',
			'disabled'    => false,
			'class'       => '',
			'css'         => '',
			'placeholder' => '',
			'type'        => 'password',
			'desc_tip'    => false,
			'description' => '',
		);
		$data        = wp_parse_args( $data, $defaults );
		$description = $this->get_description_html( $data );
		$token        = $this->get_api_token();
		$has_token    = '' !== $token;
		$field_value  = $has_token ? str_repeat( '*', strlen( $token ) ) : '';

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo wp_kses_post( $this->get_tooltip_html( $data ) ); ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="text" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $field_value ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" autocomplete="off" <?php disabled( $data['disabled'], true ); ?> />
					<?php if ( $has_token ) : ?>
						<p class="description"><?php esc_html_e( 'A token is saved and hidden. Replace the stars only if you want to update it.', 'bepusdt-woocommerce' ); ?></p>
					<?php endif; ?>
					<?php echo wp_kses_post( $description ); ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Return decrypted API token.
	 *
	 * @return string
	 */
	public function get_api_token() {
		return BEpusdt_WC_API::decrypt_secret( $this->get_option( 'api_token', '' ) );
	}

	/**
	 * Read a setting while treating an empty string as a real saved value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function get_setting_preserve_empty( $key, $default = '' ) {
		return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Force WooCommerce to store submitted blank values for checkout copy.
	 *
	 * @param string $key Field key.
	 */
	private function force_save_blank_copy_field( $key ) {
		$field_key = $this->get_field_key( $key );

		if ( ! isset( $_POST[ $field_key ] ) ) {
			return;
		}

		$value = wp_unslash( (string) $_POST[ $field_key ] );

		if ( 'description' === $key ) {
			$value = wp_kses_post( $value );
		} else {
			$value = wc_clean( $value );
		}

		if ( isset( $this->settings[ $key ] ) && $this->settings[ $key ] === $value ) {
			return;
		}

		$this->settings[ $key ] = $value;
		update_option( $this->get_option_key(), $this->settings );
	}

	/**
	 * Validate frontend chain multi-select.
	 *
	 * @param string $key Field key.
	 * @param mixed  $value Submitted value.
	 * @return array
	 */
	public function validate_enabled_trade_types_field( $key, $value ) {
		$value   = is_array( $value ) ? array_map( 'sanitize_text_field', wp_unslash( $value ) ) : array();
		$allowed = array_keys( $this->trade_type_options() );

		return array_values( array_intersect( $value, $allowed ) );
	}

	/**
	 * Validate the optional front-end guide HTML.
	 *
	 * @param string $key Field key.
	 * @param string $value Submitted value.
	 * @return string
	 */
	public function validate_payment_guide_html_field( $key, $value ) {
		return trim( wp_kses_post( wp_unslash( (string) $value ) ) );
	}

	/**
	 * Return optional front-end guide HTML.
	 *
	 * @return string
	 */
	public function get_payment_guide_html() {
		return wp_kses_post( $this->get_option( 'payment_guide_html', '' ) );
	}

	/**
	 * Get available trade type labels.
	 *
	 * @return array
	 */
	public function trade_type_options() {
		return array(
			'usdt.trc20'     => __( 'USDT TRC20 (Tron)', 'bepusdt-woocommerce' ),
			'usdc.trc20'     => __( 'USDC TRC20 (Tron)', 'bepusdt-woocommerce' ),
			'tron.trx'       => __( 'TRX (Tron)', 'bepusdt-woocommerce' ),
			'usdt.erc20'     => __( 'USDT ERC20 (Ethereum)', 'bepusdt-woocommerce' ),
			'usdc.erc20'     => __( 'USDC ERC20 (Ethereum)', 'bepusdt-woocommerce' ),
			'ethereum.eth'   => __( 'ETH (Ethereum)', 'bepusdt-woocommerce' ),
			'usdt.polygon'   => __( 'USDT Polygon', 'bepusdt-woocommerce' ),
			'usdc.polygon'   => __( 'USDC Polygon', 'bepusdt-woocommerce' ),
			'usdt.bep20'     => __( 'USDT BEP20 (BSC)', 'bepusdt-woocommerce' ),
			'usdc.bep20'     => __( 'USDC BEP20 (BSC)', 'bepusdt-woocommerce' ),
			'bsc.bnb'        => __( 'BNB (BSC)', 'bepusdt-woocommerce' ),
			'usdt.aptos'     => __( 'USDT Aptos', 'bepusdt-woocommerce' ),
			'usdc.aptos'     => __( 'USDC Aptos', 'bepusdt-woocommerce' ),
			'usdt.solana'    => __( 'USDT Solana', 'bepusdt-woocommerce' ),
			'usdc.solana'    => __( 'USDC Solana', 'bepusdt-woocommerce' ),
			'usdt.xlayer'    => __( 'USDT X-Layer', 'bepusdt-woocommerce' ),
			'usdc.xlayer'    => __( 'USDC X-Layer', 'bepusdt-woocommerce' ),
			'usdt.arbitrum'  => __( 'USDT Arbitrum-One', 'bepusdt-woocommerce' ),
			'usdc.arbitrum'  => __( 'USDC Arbitrum-One', 'bepusdt-woocommerce' ),
			'usdc.base'      => __( 'USDC Base', 'bepusdt-woocommerce' ),
			'usdt.plasma'    => __( 'USDT Plasma', 'bepusdt-woocommerce' ),
		);
	}

	/**
	 * Get frontend-enabled trade types.
	 *
	 * @return array
	 */
	public function get_enabled_trade_types() {
		$enabled = $this->get_option( 'enabled_trade_types', array( 'usdt.trc20', 'usdt.polygon', 'usdt.erc20' ) );

		if ( ! is_array( $enabled ) ) {
			$enabled = array_filter( array_map( 'trim', explode( ',', (string) $enabled ) ) );
		}

		$allowed = array_keys( $this->trade_type_options() );
		$enabled = array_values( array_intersect( $enabled, $allowed ) );

		return $enabled ? $enabled : array( 'usdt.trc20' );
	}

	/**
	 * Check whether a trade type is enabled for customers.
	 *
	 * @param string $trade_type Trade type.
	 * @return bool
	 */
	public function is_trade_type_enabled( $trade_type ) {
		return in_array( $trade_type, $this->get_enabled_trade_types(), true );
	}

	/**
	 * Get readable trade type label.
	 *
	 * @param string $trade_type Trade type.
	 * @return string
	 */
	public function get_trade_type_label( $trade_type ) {
		$options = $this->trade_type_options();
		return isset( $options[ $trade_type ] ) ? $options[ $trade_type ] : $trade_type;
	}

	/**
	 * Format frontend chain button label.
	 *
	 * @param string $trade_type Trade type.
	 * @return string
	 */
	public function format_trade_type_button_label( $trade_type ) {
		return $this->get_trade_type_label( $trade_type );
	}

	/**
	 * Validate API URL.
	 *
	 * @param string $key Field key.
	 * @param string $value Submitted value.
	 * @return string
	 */
	public function validate_api_url_field( $key, $value ) {
		return esc_url_raw( trim( (string) $value ) );
	}

}
