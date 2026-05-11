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
		$this->method_title       = __( 'BEpusdt USDT', 'bepusdt-woocommerce' );
		$this->method_description = __( 'Accept USDT payments through a BEpusdt backend.', 'bepusdt-woocommerce' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_setting_preserve_empty( 'title', __( 'USDT Payment', 'bepusdt-woocommerce' ) );
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
				'label'   => __( 'Enable BEpusdt USDT payment', 'bepusdt-woocommerce' ),
				'default' => 'yes',
			),
			'title'              => array(
				'title'       => __( 'Title', 'bepusdt-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Shown to customers during checkout.', 'bepusdt-woocommerce' ),
				'default'     => __( 'USDT Payment', 'bepusdt-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Description', 'bepusdt-woocommerce' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => __( 'Shown under the payment method on checkout.', 'bepusdt-woocommerce' ),
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
			'fiat'               => array(
				'title'       => __( 'Default Payment Currency', 'bepusdt-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Currency sent to BEpusdt. Leave as your WooCommerce currency unless your backend expects another fiat code.', 'bepusdt-woocommerce' ),
				'default'     => get_woocommerce_currency(),
				'desc_tip'    => true,
			),
			'trade_type'         => array(
				'title'       => __( 'USDT Network', 'bepusdt-woocommerce' ),
				'type'        => 'select',
				'default'     => 'usdt.trc20',
				'options'     => array(
					'usdt.trc20'   => __( 'USDT TRC20', 'bepusdt-woocommerce' ),
					'usdt.polygon' => __( 'USDT Polygon', 'bepusdt-woocommerce' ),
					'usdt.erc20'   => __( 'USDT ERC20', 'bepusdt-woocommerce' ),
				),
				'description' => __( 'Must be enabled in your BEpusdt backend.', 'bepusdt-woocommerce' ),
				'desc_tip'    => true,
			),
			'enabled_trade_types' => array(
				'title'       => __( 'Frontend Chain Buttons', 'bepusdt-woocommerce' ),
				'type'        => 'multiselect',
				'default'     => array( 'usdt.trc20', 'usdt.polygon', 'usdt.erc20' ),
				'options'     => $this->trade_type_options(),
				'description' => __( 'Choose which USDT chain buttons customers can click on the payment page.', 'bepusdt-woocommerce' ),
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
			'show_alt_methods'   => array(
				'title'   => __( 'Visual Payment Options', 'bepusdt-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show USDT, Visa, PayPal, and Mastercard visual options', 'bepusdt-woocommerce' ),
				'default' => 'yes',
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
			<?php if ( 'yes' === $this->get_option( 'show_alt_methods', 'yes' ) ) : ?>
				<div class="bepusdt-wc-method-grid" role="group" aria-label="<?php esc_attr_e( 'Payment methods', 'bepusdt-woocommerce' ); ?>">
					<button type="button" class="bepusdt-wc-method bepusdt-wc-method--image bepusdt-wc-method--active" data-bepusdt-primary-method aria-label="<?php esc_attr_e( 'USDT', 'bepusdt-woocommerce' ); ?>" aria-pressed="true">
						<span class="bepusdt-wc-method-card">
							<img src="<?php echo esc_url( BEPUSDT_WC_URL . 'assets/images/usdt.svg' ); ?>" alt="" loading="lazy" />
						</span>
					</button>
					<?php foreach ( $this->other_methods() as $method ) : ?>
						<button type="button" class="bepusdt-wc-method bepusdt-wc-method--image" data-bepusdt-disabled-method data-bepusdt-method-name="<?php echo esc_attr( $method['brand'] ); ?>" aria-disabled="true" aria-label="<?php echo esc_attr( $method['brand'] ); ?>" aria-pressed="false">
							<span class="bepusdt-wc-method-card">
								<img src="<?php echo esc_url( $method['image'] ); ?>" alt="" loading="lazy" />
							</span>
						</button>
					<?php endforeach; ?>
				</div>
				<div class="bepusdt-wc-notice" data-bepusdt-notice hidden></div>
			<?php endif; ?>
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

		$order->update_status( 'pending', __( 'Awaiting customer to choose a USDT network.', 'bepusdt-woocommerce' ) );
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
		$has_token   = '' !== $this->get_option( 'api_token', '' );
		$field_value  = $has_token ? '********' : '';

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
	 * Get available trade type labels.
	 *
	 * @return array
	 */
	public function trade_type_options() {
		return array(
			'usdt.trc20'   => __( 'USDT TRC20', 'bepusdt-woocommerce' ),
			'usdt.polygon' => __( 'USDT Polygon', 'bepusdt-woocommerce' ),
			'usdt.erc20'   => __( 'USDT ERC20', 'bepusdt-woocommerce' ),
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

		return $enabled ? $enabled : array( $this->get_option( 'trade_type', 'usdt.trc20' ) );
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
		$label = $this->get_trade_type_label( $trade_type );
		return preg_replace( '/^USDT\s+/i', 'USDT - ', $label );
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

	/**
	 * Visual alternative payment methods.
	 *
	 * @return array
	 */
	private function other_methods() {
		return array(
			array(
				'brand' => 'VISA',
				'image' => BEPUSDT_WC_URL . 'assets/images/visa.svg',
			),
			array(
				'brand' => 'PayPal',
				'image' => BEPUSDT_WC_URL . 'assets/images/paypal.svg',
			),
			array(
				'brand' => 'Mastercard',
				'image' => BEPUSDT_WC_URL . 'assets/images/mastercard.svg',
			),
		);
	}
}
