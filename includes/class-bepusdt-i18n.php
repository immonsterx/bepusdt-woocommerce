<?php
/**
 * Internationalization helpers.
 *
 * @package BEpusdt_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps frontend strings aligned with the active WordPress locale.
 */
final class BEpusdt_WC_I18n {
	/**
	 * Register i18n hooks.
	 */
	public static function init() {
		add_filter( 'plugin_locale', array( __CLASS__, 'plugin_locale' ), 10, 2 );
		add_filter( 'gettext_bepusdt-woocommerce', array( __CLASS__, 'gettext_fallback' ), 10, 3 );
	}

	/**
	 * Let multilingual plugins control this plugin's frontend locale.
	 *
	 * @param string $locale Locale.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function plugin_locale( $locale, $domain ) {
		if ( 'bepusdt-woocommerce' !== $domain ) {
			return $locale;
		}

		return self::current_locale();
	}

	/**
	 * Get the locale currently active for this request.
	 *
	 * @return string
	 */
	public static function current_locale() {
		if ( function_exists( 'pll_current_language' ) ) {
			$locale = pll_current_language( 'locale' );
			if ( $locale ) {
				return $locale;
			}
		}

		if ( has_filter( 'wpml_current_language' ) ) {
			$wpml_language = apply_filters( 'wpml_current_language', null );
			$wpml_locale   = self::locale_from_language_code( $wpml_language );
			if ( $wpml_locale ) {
				return $wpml_locale;
			}
		}

		if ( function_exists( 'determine_locale' ) ) {
			return determine_locale();
		}

		return get_locale();
	}

	/**
	 * Provide a small frontend fallback for Chinese locales.
	 *
	 * @param string $translation Current translation.
	 * @param string $text Source text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function gettext_fallback( $translation, $text, $domain ) {
		if ( 'bepusdt-woocommerce' !== $domain || $translation !== $text ) {
			return $translation;
		}

		$locale = strtolower( str_replace( '-', '_', self::current_locale() ) );

		if ( 0 === strpos( $locale, 'zh_tw' ) || 0 === strpos( $locale, 'zh_hk' ) || 0 === strpos( $locale, 'zh_mo' ) || false !== strpos( $locale, 'hant' ) ) {
			$map = self::zh_tw();
		} elseif ( 0 === strpos( $locale, 'zh' ) ) {
			$map = self::zh_cn();
		} else {
			return $translation;
		}

		return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
	}

	/**
	 * Map common multilingual language codes to WordPress locales.
	 *
	 * @param string|null $language_code Language code.
	 * @return string
	 */
	private static function locale_from_language_code( $language_code ) {
		$language_code = strtolower( str_replace( '-', '_', (string) $language_code ) );

		$map = array(
			'zh'      => 'zh_CN',
			'zh_cn'   => 'zh_CN',
			'zh_hans' => 'zh_CN',
			'zh_tw'   => 'zh_TW',
			'zh_hk'   => 'zh_HK',
			'zh_mo'   => 'zh_MO',
			'zh_hant' => 'zh_TW',
		);

		if ( isset( $map[ $language_code ] ) ) {
			return $map[ $language_code ];
		}

		return false !== strpos( $language_code, '_' ) ? $language_code : '';
	}

	/**
	 * Decode an ASCII-safe unicode string.
	 *
	 * @param string $value Escaped unicode JSON string content.
	 * @return string
	 */
	private static function u( $value ) {
		$decoded = json_decode( '"' . $value . '"' );
		return is_string( $decoded ) ? $decoded : $value;
	}

	/**
	 * Simplified Chinese fallback strings.
	 *
	 * @return array
	 */
	private static function zh_cn() {
		return array(
			'BEpusdt Crypto' => 'BEpusdt ' . self::u( '\u52a0\u5bc6\u8d27\u5e01' ),
			'Accept cryptocurrency payments through a BEpusdt backend.' => self::u( '\u901a\u8fc7 BEpusdt \u540e\u7aef\u63a5\u53d7\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\u3002' ),
			'Crypto Payment' => self::u( '\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8' ),
			'USDT Payment' => 'USDT ' . self::u( '\u652f\u4ed8' ),
			'Enable BEpusdt cryptocurrency payment' => self::u( '\u542f\u7528 BEpusdt \u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8' ),
			'Shown to customers during checkout. Leave blank to hide it on the frontend.' => self::u( '\u5728\u7ed3\u8d26\u65f6\u663e\u793a\u7ed9\u5ba2\u6237\u3002\u7559\u7a7a\u5219\u524d\u53f0\u4e0d\u663e\u793a\u3002' ),
			'Shown under the payment method on checkout. Leave blank to hide it on the frontend.' => self::u( '\u5728\u7ed3\u8d26\u652f\u4ed8\u65b9\u5f0f\u4e0b\u65b9\u663e\u793a\u3002\u7559\u7a7a\u5219\u524d\u53f0\u4e0d\u663e\u793a\u3002' ),
			'Optional checkout description.' => self::u( '\u53ef\u9009\u7684\u7ed3\u8d26\u8bf4\u660e\u3002' ),
			'Frontend Payment Buttons' => self::u( '\u524d\u53f0\u652f\u4ed8\u6309\u94ae' ),
			'Choose which BEpusdt trade types customers can click on the payment page.' => self::u( '\u9009\u62e9\u5ba2\u6237\u53ef\u5728\u652f\u4ed8\u9875\u70b9\u51fb\u7684 BEpusdt \u4ea4\u6613\u7c7b\u578b\u3002' ),
			'USDT payment option' => 'USDT ' . self::u( '\u652f\u4ed8\u9009\u9879' ),
			'Payment Guide HTML' => self::u( '\u652f\u4ed8\u6559\u7a0b HTML' ),
			'Used to guide customers to a payment tutorial. Leave blank to hide it. Example: %s' => self::u( '\u7528\u4e8e\u5f15\u5bfc\u652f\u4ed8\u6559\u7a0b\uff0c\u7559\u7a7a\u5219\u4e0d\u663e\u793a\u3002\u793a\u4f8b\uff1a%s' ),
			'Choose a Payment Network' => self::u( '\u9009\u62e9\u652f\u4ed8\u7f51\u7edc' ),
			'Your order has been created. Select the network or token you want to pay with, then you will be redirected to the secure cryptocurrency checkout page.' => self::u( '\u8ba2\u5355\u5df2\u521b\u5efa\u3002\u8bf7\u9009\u62e9\u8981\u4f7f\u7528\u7684\u7f51\u7edc\u6216\u4ee3\u5e01\uff0c\u968f\u540e\u5c06\u8df3\u8f6c\u5230\u5b89\u5168\u7684\u52a0\u5bc6\u8d27\u5e01\u6536\u94f6\u53f0\u3002' ),
			'Order Total' => self::u( '\u8ba2\u5355\u603b\u989d' ),
			'Order Number' => self::u( '\u8ba2\u5355\u7f16\u53f7' ),
			'Order Date' => self::u( '\u8ba2\u5355\u65e5\u671f' ),
			'Payment Method' => self::u( '\u652f\u4ed8\u65b9\u5f0f' ),
			'Trade ID' => self::u( '\u4ea4\u6613 ID' ),
			'Payment Network' => self::u( '\u652f\u4ed8\u7f51\u7edc' ),
			'After payment, this page will update automatically when the transaction is confirmed.' => self::u( '\u4ed8\u6b3e\u540e\uff0c\u4ea4\u6613\u786e\u8ba4\u65f6\u6b64\u9875\u9762\u4f1a\u81ea\u52a8\u66f4\u65b0\u3002' ),
			'Payment confirmed. Refreshing order status...' => self::u( '\u4ed8\u6b3e\u5df2\u786e\u8ba4\uff0c\u6b63\u5728\u5237\u65b0\u8ba2\u5355\u72b6\u6001...' ),
			'Invalid payment request.' => self::u( '\u65e0\u6548\u7684\u652f\u4ed8\u8bf7\u6c42\u3002' ),
			'Selected payment network is not available.' => self::u( '\u6240\u9009\u652f\u4ed8\u7f51\u7edc\u4e0d\u53ef\u7528\u3002' ),
			'Unable to create cryptocurrency payment. Please try again.' => self::u( '\u65e0\u6cd5\u521b\u5efa\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\uff0c\u8bf7\u91cd\u8bd5\u3002' ),
			'Cryptocurrency payment started on %s.' => self::u( '\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\u5df2\u901a\u8fc7 %s \u53d1\u8d77\u3002' ),
			'Cryptocurrency payment confirmed.' => self::u( '\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\u5df2\u786e\u8ba4\u3002' ),
			'Cryptocurrency payment expired.' => self::u( '\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\u5df2\u8fc7\u671f\u3002' ),
			'Awaiting customer to choose a payment network.' => self::u( '\u7b49\u5f85\u5ba2\u6237\u9009\u62e9\u652f\u4ed8\u7f51\u7edc\u3002' ),
			'Pay with cryptocurrency' => self::u( '\u4f7f\u7528\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8' ),
			'A token is saved and hidden. Replace the stars only if you want to update it.' => self::u( 'Token \u5df2\u4fdd\u5b58\u5e76\u9690\u85cf\u3002\u53ea\u6709\u9700\u8981\u66f4\u65b0\u65f6\u624d\u66ff\u6362\u661f\u53f7\u3002' ),
		);
	}

	/**
	 * Traditional Chinese fallback strings.
	 *
	 * @return array
	 */
	private static function zh_tw() {
		return array(
			'BEpusdt Crypto' => 'BEpusdt ' . self::u( '\u52a0\u5bc6\u8ca8\u5e63' ),
			'Accept cryptocurrency payments through a BEpusdt backend.' => self::u( '\u900f\u904e BEpusdt \u5f8c\u7aef\u63a5\u53d7\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\u3002' ),
			'Crypto Payment' => self::u( '\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8' ),
			'USDT Payment' => 'USDT ' . self::u( '\u652f\u4ed8' ),
			'Enable BEpusdt cryptocurrency payment' => self::u( '\u555f\u7528 BEpusdt \u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8' ),
			'Shown to customers during checkout. Leave blank to hide it on the frontend.' => self::u( '\u5728\u7d50\u5e33\u6642\u986f\u793a\u7d66\u5ba2\u6236\u3002\u7559\u7a7a\u5247\u524d\u53f0\u4e0d\u986f\u793a\u3002' ),
			'Shown under the payment method on checkout. Leave blank to hide it on the frontend.' => self::u( '\u5728\u7d50\u5e33\u652f\u4ed8\u65b9\u5f0f\u4e0b\u65b9\u986f\u793a\u3002\u7559\u7a7a\u5247\u524d\u53f0\u4e0d\u986f\u793a\u3002' ),
			'Optional checkout description.' => self::u( '\u53ef\u9078\u7684\u7d50\u5e33\u8aaa\u660e\u3002' ),
			'Frontend Payment Buttons' => self::u( '\u524d\u53f0\u652f\u4ed8\u6309\u9215' ),
			'Choose which BEpusdt trade types customers can click on the payment page.' => self::u( '\u9078\u64c7\u5ba2\u6236\u53ef\u5728\u652f\u4ed8\u9801\u9ede\u64ca\u7684 BEpusdt \u4ea4\u6613\u985e\u578b\u3002' ),
			'USDT payment option' => 'USDT ' . self::u( '\u652f\u4ed8\u9078\u9805' ),
			'Payment Guide HTML' => self::u( '\u652f\u4ed8\u6559\u5b78 HTML' ),
			'Used to guide customers to a payment tutorial. Leave blank to hide it. Example: %s' => self::u( '\u7528\u65bc\u5f15\u5c0e\u652f\u4ed8\u6559\u5b78\uff0c\u7559\u7a7a\u5247\u4e0d\u986f\u793a\u3002\u7bc4\u4f8b\uff1a%s' ),
			'Choose a Payment Network' => self::u( '\u9078\u64c7\u652f\u4ed8\u7db2\u8def' ),
			'Your order has been created. Select the network or token you want to pay with, then you will be redirected to the secure cryptocurrency checkout page.' => self::u( '\u8a02\u55ae\u5df2\u5efa\u7acb\u3002\u8acb\u9078\u64c7\u8981\u4f7f\u7528\u7684\u7db2\u8def\u6216\u4ee3\u5e63\uff0c\u96a8\u5f8c\u5c07\u8df3\u8f49\u5230\u5b89\u5168\u7684\u52a0\u5bc6\u8ca8\u5e63\u6536\u9280\u53f0\u3002' ),
			'Order Total' => self::u( '\u8a02\u55ae\u7e3d\u984d' ),
			'Order Number' => self::u( '\u8a02\u55ae\u7de8\u865f' ),
			'Order Date' => self::u( '\u8a02\u55ae\u65e5\u671f' ),
			'Payment Method' => self::u( '\u652f\u4ed8\u65b9\u5f0f' ),
			'Trade ID' => self::u( '\u4ea4\u6613 ID' ),
			'Payment Network' => self::u( '\u652f\u4ed8\u7db2\u8def' ),
			'After payment, this page will update automatically when the transaction is confirmed.' => self::u( '\u4ed8\u6b3e\u5f8c\uff0c\u4ea4\u6613\u78ba\u8a8d\u6642\u6b64\u9801\u9762\u6703\u81ea\u52d5\u66f4\u65b0\u3002' ),
			'Payment confirmed. Refreshing order status...' => self::u( '\u4ed8\u6b3e\u5df2\u78ba\u8a8d\uff0c\u6b63\u5728\u91cd\u65b0\u6574\u7406\u8a02\u55ae\u72c0\u614b...' ),
			'Invalid payment request.' => self::u( '\u7121\u6548\u7684\u652f\u4ed8\u8acb\u6c42\u3002' ),
			'Selected payment network is not available.' => self::u( '\u6240\u9078\u652f\u4ed8\u7db2\u8def\u4e0d\u53ef\u7528\u3002' ),
			'Unable to create cryptocurrency payment. Please try again.' => self::u( '\u7121\u6cd5\u5efa\u7acb\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\uff0c\u8acb\u91cd\u8a66\u3002' ),
			'Cryptocurrency payment started on %s.' => self::u( '\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\u5df2\u900f\u904e %s \u767c\u8d77\u3002' ),
			'Cryptocurrency payment confirmed.' => self::u( '\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\u5df2\u78ba\u8a8d\u3002' ),
			'Cryptocurrency payment expired.' => self::u( '\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\u5df2\u904e\u671f\u3002' ),
			'Awaiting customer to choose a payment network.' => self::u( '\u7b49\u5f85\u5ba2\u6236\u9078\u64c7\u652f\u4ed8\u7db2\u8def\u3002' ),
			'Pay with cryptocurrency' => self::u( '\u4f7f\u7528\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8' ),
			'A token is saved and hidden. Replace the stars only if you want to update it.' => self::u( 'Token \u5df2\u5132\u5b58\u4e26\u96b1\u85cf\u3002\u53ea\u6709\u9700\u8981\u66f4\u65b0\u6642\u624d\u66ff\u63db\u661f\u865f\u3002' ),
		);
	}
}
