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
		$this->method_title       = __( 'BEpusdt 加密货币支付', 'bepusdt-woocommerce' );
		$this->method_description = __( '通过 BEpusdt 后端接收加密货币付款。', 'bepusdt-woocommerce' );
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
				'title'   => __( '启用/禁用', 'bepusdt-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( '启用 BEpusdt 加密货币支付', 'bepusdt-woocommerce' ),
				'default' => 'yes',
			),
			'title'              => array(
				'title'       => __( '支付方式标题', 'bepusdt-woocommerce' ),
				'type'        => 'text',
				'description' => __( '显示在结账页，留空则不在前台显示。', 'bepusdt-woocommerce' ),
				'default'     => __( 'Crypto Payment', 'bepusdt-woocommerce' ),
				'placeholder' => __( '加密货币支付', 'bepusdt-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( '支付方式描述', 'bepusdt-woocommerce' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => __( '显示在结账页支付方式下方，留空则不在前台显示。', 'bepusdt-woocommerce' ),
				'placeholder' => __( '可选的结账说明', 'bepusdt-woocommerce' ),
				'desc_tip'    => true,
			),
			'api_url'            => array(
				'title'       => __( 'BEpusdt API 地址', 'bepusdt-woocommerce' ),
				'type'        => 'url',
				'description' => __( '示例：https://pay.example.com', 'bepusdt-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'api_token'          => array(
				'title'       => __( 'API 令牌/密钥', 'bepusdt-woocommerce' ),
				'type'        => 'api_token',
				'description' => __( '尽可能使用 WordPress 盐值加密存储。', 'bepusdt-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'enabled_trade_types' => array(
				'title'       => __( '前台支付按钮', 'bepusdt-woocommerce' ),
				'type'        => 'multiselect',
				'default'     => array( 'usdt.trc20', 'usdt.polygon', 'usdt.erc20' ),
				'options'     => $this->trade_type_options(),
				'description' => __( '选择客户可在付款信息卡片中点击的 BEpusdt 币种和网络。', 'bepusdt-woocommerce' ),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
			),
			'icon_auto_size'     => array(
				'title'       => __( '支付图标自动适配', 'bepusdt-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( '参考当前主题中其他支付方式图标的显示尺寸', 'bepusdt-woocommerce' ),
				'default'     => 'yes',
				'description' => __( '如果主题中没有可参考的图标，则使用插件默认的自适应尺寸。', 'bepusdt-woocommerce' ),
				'desc_tip'    => true,
			),
			'icon_width'         => array(
				'title'             => __( '支付图标宽度', 'bepusdt-woocommerce' ),
				'type'              => 'number',
				'description'       => __( '单位为像素；留空则自动。只填宽度或高度时会保留 SVG 原始宽高比。', 'bepusdt-woocommerce' ),
				'default'           => '',
				'custom_attributes' => array( 'min' => 12, 'max' => 600, 'step' => 1 ),
				'desc_tip'          => true,
			),
			'icon_height'        => array(
				'title'             => __( '支付图标高度', 'bepusdt-woocommerce' ),
				'type'              => 'number',
				'description'       => __( '单位为像素；留空则自动。', 'bepusdt-woocommerce' ),
				'default'           => '',
				'custom_attributes' => array( 'min' => 12, 'max' => 300, 'step' => 1 ),
				'desc_tip'          => true,
			),
			'icon_fit'           => array(
				'title'       => __( '支付图标宽高比', 'bepusdt-woocommerce' ),
				'type'        => 'select',
				'default'     => 'contain',
				'options'     => array(
					'contain' => __( '保留原始比例（完整显示）', 'bepusdt-woocommerce' ),
					'cover'   => __( '保留原始比例（允许裁切）', 'bepusdt-woocommerce' ),
					'fill'    => __( '按设定宽高拉伸', 'bepusdt-woocommerce' ),
				),
				'description' => __( '同时设置宽度和高度时生效。', 'bepusdt-woocommerce' ),
				'desc_tip'    => true,
			),
			'expires_in'         => array(
				'title'             => __( '支付有效期', 'bepusdt-woocommerce' ),
				'type'              => 'number',
				'description'       => __( '支付有效期，单位为秒。', 'bepusdt-woocommerce' ),
				'default'           => 1200,
				'custom_attributes' => array(
					'min'  => 120,
					'step' => 60,
				),
				'desc_tip'          => true,
			),
			'payment_guide_html' => array(
				'title'       => __( '支付教程 HTML', 'bepusdt-woocommerce' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => sprintf(
					/* translators: %s: example HTML. */
					__( '用于显示支付教程，留空则不显示。示例：%s', 'bepusdt-woocommerce' ),
					'<code>&lt;a href=&quot;https://yourdomain.com&quot; target=&quot;_blank&quot; rel=&quot;noopener noreferrer&quot;&gt;USDT 支付教程&lt;/a&gt;</code>'
				),
				'css'         => 'min-height: 100px;',
			),
			'polling_enabled'    => array(
				'title'   => __( '自动状态轮询', 'bepusdt-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( '定时查询待支付的 BEpusdt 订单', 'bepusdt-woocommerce' ),
				'default' => 'yes',
			),
			'debug'              => array(
				'title'       => __( '调试日志', 'bepusdt-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( '启用调试日志', 'bepusdt-woocommerce' ),
				'default'     => 'no',
				'description' => __( '日志通过 WooCommerce 日志系统写入，敏感信息会被屏蔽。', 'bepusdt-woocommerce' ),
			),
			'delete_data_on_uninstall' => array(
				'title'       => __( '卸载数据', 'bepusdt-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( '卸载插件时删除 BEpusdt 网关设置', 'bepusdt-woocommerce' ),
				'default'     => 'no',
				'description' => __( '订单中的历史付款记录将保留，避免破坏财务审计数据。', 'bepusdt-woocommerce' ),
			),
		);
	}

	/**
	 * Limit the gateway to complete, supported configurations.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}

		$currency = strtoupper( get_woocommerce_currency() );
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
			if ( $order ) {
				$currency = strtoupper( $order->get_currency() );
			}
		}

		return '' !== $this->get_api_token()
			&& wp_http_validate_url( untrailingslashit( $this->get_option( 'api_url', '' ) ) )
			&& in_array( $currency, $this->supported_fiat_currencies(), true )
			&& ! empty( $this->get_enabled_trade_types() );
	}

	/**
	 * Payment fields shown at checkout.
	 */
	public function payment_fields() {
		$description = $this->get_description();

		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}

		$icon_url      = add_query_arg( 'ver', BEPUSDT_WC_VERSION, BEPUSDT_WC_URL . 'assets/images/crypto-pay.svg' );
		$icon_settings = $this->get_icon_display_settings();
		$style         = $this->get_icon_style_attribute( $icon_settings );

		?>
		<div class="bepusdt-wc-checkout" data-bepusdt-checkout data-icon-auto="<?php echo esc_attr( $icon_settings['auto'] ? 'yes' : 'no' ); ?>" data-icon-width="<?php echo esc_attr( $icon_settings['width'] ); ?>" data-icon-height="<?php echo esc_attr( $icon_settings['height'] ); ?>"<?php echo $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
			<div class="bepusdt-wc-usdt-option" role="group" aria-label="<?php esc_attr_e( 'USDT payment option', 'bepusdt-woocommerce' ); ?>">
				<div class="bepusdt-wc-method-grid">
					<button type="button" class="bepusdt-wc-method bepusdt-wc-method--image bepusdt-wc-method--active" data-bepusdt-primary-method aria-label="<?php esc_attr_e( 'USDT', 'bepusdt-woocommerce' ); ?>" aria-pressed="true">
						<span class="bepusdt-wc-method-card">
							<img src="<?php echo esc_url( $icon_url ); ?>" alt="" loading="lazy" />
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

		if ( ! $order || 'bepusdt' !== $order->get_payment_method() || ! $order->needs_payment() ) {
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

		if ( $saved ) {
			$this->sync_submitted_copy_fields();
		} else {
			$this->init_settings();
		}

		$this->title       = $this->get_setting_preserve_empty( 'title', __( 'Crypto Payment', 'bepusdt-woocommerce' ) );
		$this->description = $this->get_setting_preserve_empty( 'description', '' );

		return $saved;
	}

	/**
	 * Return the checkout title without allowing translation filters to revive cleared text.
	 *
	 * @return string
	 */
	public function get_title() {
		if ( $this->is_setting_intentionally_blank( 'title' ) ) {
			return '';
		}

		return parent::get_title();
	}

	/**
	 * Return the checkout description without allowing translation filters to revive cleared text.
	 *
	 * @return string
	 */
	public function get_description() {
		if ( $this->is_setting_intentionally_blank( 'description' ) ) {
			return '';
		}

		return parent::get_description();
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
		$field_value  = $has_token ? str_repeat( '*', 12 ) : '';

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo wp_kses_post( $this->get_tooltip_html( $data ) ); ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="password" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $field_value ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" autocomplete="new-password" <?php disabled( $data['disabled'], true ); ?> />
					<?php if ( $has_token ) : ?>
						<p class="description"><?php esc_html_e( '令牌已保存并隐藏，仅在需要更新时替换星号。', 'bepusdt-woocommerce' ); ?></p>
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
	 * Persist the submitted checkout copy on top of the freshly saved settings.
	 */
	private function sync_submitted_copy_fields() {
		$settings = get_option( $this->get_option_key(), array() );
		$settings = is_array( $settings ) ? $settings : array();
		$changed  = false;

		foreach ( array( 'title', 'description' ) as $key ) {
			$field_key = $this->get_field_key( $key );

			if ( ! array_key_exists( $field_key, $_POST ) ) {
				continue;
			}

			$value = wp_unslash( (string) $_POST[ $field_key ] );
			$value = 'description' === $key ? wp_kses_post( $value ) : wc_clean( $value );

			if ( ! array_key_exists( $key, $settings ) || $settings[ $key ] !== $value ) {
				$settings[ $key ] = $value;
				$changed          = true;
			}
		}

		if ( $changed ) {
			update_option( $this->get_option_key(), $settings );
		}

		$this->settings = $settings;
	}

	/**
	 * Check whether checkout copy exists in settings and was deliberately cleared.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	private function is_setting_intentionally_blank( $key ) {
		return array_key_exists( $key, $this->settings )
			&& '' === trim( wp_strip_all_tags( (string) $this->settings[ $key ] ) );
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
		$html = wp_kses_post( $this->get_option( 'payment_guide_html', '' ) );

		if ( '' === trim( $html ) ) {
			return '';
		}

		$source_html = $html;
		do_action( 'wpml_register_single_string', 'bepusdt-woocommerce', 'Payment Guide HTML', $source_html );
		$html = apply_filters( 'wpml_translate_single_string', $source_html, 'bepusdt-woocommerce', 'Payment Guide HTML' );

		if ( function_exists( 'pll_register_string' ) ) {
			pll_register_string( 'BEpusdt Payment Guide HTML', $source_html, 'bepusdt-woocommerce', true );
		}
		if ( function_exists( 'pll__' ) ) {
			$html = pll__( $source_html );
		}

		return wp_kses_post( apply_filters( 'bepusdt_wc_payment_guide_html', $html, $this ) );
	}

	/**
	 * Get available trade type labels.
	 *
	 * @return array
	 */
	public function trade_type_options() {
		return apply_filters( 'bepusdt_wc_trade_type_options', array(
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
			'usdt.ton'       => __( 'USDT TON', 'bepusdt-woocommerce' ),
			'ton.gram'       => __( 'GRAM (TON)', 'bepusdt-woocommerce' ),
		) );
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

		return $enabled;
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
	 * Validate API URL.
	 *
	 * @param string $key Field key.
	 * @param string $value Submitted value.
	 * @return string
	 */
	public function validate_api_url_field( $key, $value ) {
		$url      = untrailingslashit( esc_url_raw( trim( (string) $value ) ) );
		$existing = $this->get_option( 'api_url', '' );

		if ( ! $url ) {
			return '';
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( ! wp_http_validate_url( $url ) || preg_match( '#/api(?:/v\d+)?/?$#i', $path ) ) {
			WC_Admin_Settings::add_error( __( 'BEpusdt API 地址必须是服务根地址，不能以 /api 或 /api/v1 结尾。', 'bepusdt-woocommerce' ) );
			return $existing;
		}

		return $url;
	}

	/**
	 * Validate optional icon dimensions.
	 *
	 * @param string $key Field key.
	 * @param mixed  $value Submitted value.
	 * @return string
	 */
	public function validate_icon_width_field( $key, $value ) {
		return $this->validate_icon_dimension( $value, 12, 600 );
	}

	/**
	 * Validate optional icon height.
	 *
	 * @param string $key Field key.
	 * @param mixed  $value Submitted value.
	 * @return string
	 */
	public function validate_icon_height_field( $key, $value ) {
		return $this->validate_icon_dimension( $value, 12, 300 );
	}

	/**
	 * Return sanitized icon display settings for classic and block checkout.
	 *
	 * @return array
	 */
	public function get_icon_display_settings() {
		$fit = $this->get_option( 'icon_fit', 'contain' );

		return array(
			'auto'   => 'yes' === $this->get_option( 'icon_auto_size', 'yes' ),
			'width'  => $this->validate_icon_dimension( $this->get_option( 'icon_width', '' ), 12, 600 ),
			'height' => $this->validate_icon_dimension( $this->get_option( 'icon_height', '' ), 12, 300 ),
			'fit'    => in_array( $fit, array( 'contain', 'cover', 'fill' ), true ) ? $fit : 'contain',
		);
	}

	/**
	 * Supported BEpusdt fiat currencies.
	 *
	 * @return array
	 */
	public function supported_fiat_currencies() {
		return apply_filters( 'bepusdt_wc_supported_fiat_currencies', array( 'CNY', 'USD', 'EUR', 'GBP', 'JPY' ) );
	}

	/**
	 * Build scoped CSS variables for the payment icon.
	 *
	 * @param array $settings Icon settings.
	 * @return string
	 */
	private function get_icon_style_attribute( $settings ) {
		$parts = array( '--bepusdt-icon-fit:' . $settings['fit'] );

		if ( '' !== $settings['width'] ) {
			$parts[] = '--bepusdt-icon-width:' . absint( $settings['width'] ) . 'px';
		}

		if ( '' !== $settings['height'] ) {
			$parts[] = '--bepusdt-icon-height:' . absint( $settings['height'] ) . 'px';
		}

		return implode( ';', $parts );
	}

	/**
	 * Sanitize an optional pixel dimension.
	 *
	 * @param mixed $value Submitted value.
	 * @param int   $minimum Minimum value.
	 * @param int   $maximum Maximum value.
	 * @return string
	 */
	private function validate_icon_dimension( $value, $minimum, $maximum ) {
		if ( '' === trim( (string) $value ) ) {
			return '';
		}

		return (string) min( $maximum, max( $minimum, absint( $value ) ) );
	}

}
