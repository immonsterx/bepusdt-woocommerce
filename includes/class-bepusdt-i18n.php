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
			'Crypto Payment' => self::u( '\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8' ),
			'Cryptocurrency' => self::u( '\u52a0\u5bc6\u8d27\u5e01' ),
			'USDT payment option' => 'USDT ' . self::u( '\u652f\u4ed8\u9009\u9879' ),
			'Payment Information' => self::u( '\u4ed8\u6b3e\u4fe1\u606f' ),
			'Your order has been created. Select the network or token you want to pay with, then you will be redirected to the secure cryptocurrency checkout page.' => self::u( '\u8ba2\u5355\u5df2\u521b\u5efa\u3002\u8bf7\u9009\u62e9\u8981\u4f7f\u7528\u7684\u7f51\u7edc\u6216\u4ee3\u5e01\uff0c\u968f\u540e\u5c06\u8df3\u8f6c\u5230\u5b89\u5168\u7684\u52a0\u5bc6\u8d27\u5e01\u6536\u94f6\u53f0\u3002' ),
			'Order Total' => self::u( '\u8ba2\u5355\u603b\u989d' ),
			'Order Number' => self::u( '\u8ba2\u5355\u7f16\u53f7' ),
			'Order Date' => self::u( '\u8ba2\u5355\u65e5\u671f' ),
			'Payment Method' => self::u( '\u652f\u4ed8\u65b9\u5f0f' ),
			'Trade ID' => self::u( '\u4ea4\u6613 ID' ),
			'Currency and Network' => self::u( '\u5e01\u79cd\u548c\u7f51\u7edc' ),
			'After payment, this page will update automatically when the transaction is confirmed.' => self::u( '\u4ed8\u6b3e\u540e\uff0c\u4ea4\u6613\u786e\u8ba4\u65f6\u6b64\u9875\u9762\u4f1a\u81ea\u52a8\u66f4\u65b0\u3002' ),
			'Payment confirmed. Refreshing order status...' => self::u( '\u4ed8\u6b3e\u5df2\u786e\u8ba4\uff0c\u6b63\u5728\u5237\u65b0\u8ba2\u5355\u72b6\u6001...' ),
			'The cryptocurrency payment expired. Choose a network to try again.' => self::u( '\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\u5df2\u8fc7\u671f\u3002\u8bf7\u9009\u62e9\u7f51\u7edc\u540e\u91cd\u8bd5\u3002' ),
			'Invalid payment request.' => self::u( '\u65e0\u6548\u7684\u652f\u4ed8\u8bf7\u6c42\u3002' ),
			'Invalid request.' => self::u( '\u65e0\u6548\u7684\u8bf7\u6c42\u3002' ),
			'Order not found.' => self::u( '\u672a\u627e\u5230\u8ba2\u5355\u3002' ),
			'Selected payment network is not available.' => self::u( '\u6240\u9009\u652f\u4ed8\u7f51\u7edc\u4e0d\u53ef\u7528\u3002' ),
			'No cryptocurrency payment network is available.' => self::u( '\u5f53\u524d\u6ca1\u6709\u53ef\u7528\u7684\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\u7f51\u7edc\u3002' ),
			'The order currency is not supported by BEpusdt.' => self::u( '\u8be5\u8ba2\u5355\u5e01\u79cd\u4e0d\u53d7 BEpusdt \u652f\u6301\u3002' ),
			'A cryptocurrency payment is already being created. Please wait a moment and try again.' => self::u( '\u6b63\u5728\u521b\u5efa\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\uff0c\u8bf7\u7a0d\u540e\u91cd\u8bd5\u3002' ),
			'Unable to create cryptocurrency payment. Please try again.' => self::u( '\u65e0\u6cd5\u521b\u5efa\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\uff0c\u8bf7\u91cd\u8bd5\u3002' ),
			'BEpusdt API URL is invalid.' => self::u( 'BEpusdt API \u5730\u5740\u65e0\u6548\u3002' ),
			'BEpusdt API token is missing.' => self::u( '\u7f3a\u5c11 BEpusdt API \u4ee4\u724c\u3002' ),
			'BEpusdt returned invalid JSON.' => self::u( 'BEpusdt \u8fd4\u56de\u4e86\u65e0\u6548\u7684 JSON\u3002' ),
			'BEpusdt request failed.' => self::u( 'BEpusdt \u8bf7\u6c42\u5931\u8d25\u3002' ),
			'WooCommerce Order #%s' => self::u( 'WooCommerce \u8ba2\u5355 #%s' ),
			'Cryptocurrency payment started on %s.' => self::u( '\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\u5df2\u901a\u8fc7 %s \u53d1\u8d77\u3002' ),
			'Cryptocurrency payment confirmed.' => self::u( '\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\u5df2\u786e\u8ba4\u3002' ),
			'Cryptocurrency payment expired.' => self::u( '\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8\u5df2\u8fc7\u671f\u3002' ),
			'Awaiting customer to choose a payment network.' => self::u( '\u7b49\u5f85\u5ba2\u6237\u9009\u62e9\u652f\u4ed8\u7f51\u7edc\u3002' ),
			'Pay with cryptocurrency' => self::u( '\u4f7f\u7528\u52a0\u5bc6\u8d27\u5e01\u652f\u4ed8' ),
			'Every five minutes' => self::u( '\u6bcf\u4e94\u5206\u949f' ),
			'BEpusdt for WooCommerce requires WooCommerce to be installed and active.' => self::u( 'BEpusdt for WooCommerce \u9700\u8981\u5b89\u88c5\u5e76\u542f\u7528 WooCommerce\u3002' ),
		);
	}

	/**
	 * Traditional Chinese fallback strings.
	 *
	 * @return array
	 */
	private static function zh_tw() {
		return array(
			'Crypto Payment' => self::u( '\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8' ),
			'Cryptocurrency' => self::u( '\u52a0\u5bc6\u8ca8\u5e63' ),
			'USDT payment option' => 'USDT ' . self::u( '\u652f\u4ed8\u9078\u9805' ),
			'Payment Information' => self::u( '\u4ed8\u6b3e\u8cc7\u8a0a' ),
			'Your order has been created. Select the network or token you want to pay with, then you will be redirected to the secure cryptocurrency checkout page.' => self::u( '\u8a02\u55ae\u5df2\u5efa\u7acb\u3002\u8acb\u9078\u64c7\u8981\u4f7f\u7528\u7684\u7db2\u8def\u6216\u4ee3\u5e63\uff0c\u96a8\u5f8c\u5c07\u8df3\u8f49\u5230\u5b89\u5168\u7684\u52a0\u5bc6\u8ca8\u5e63\u6536\u9280\u53f0\u3002' ),
			'Order Total' => self::u( '\u8a02\u55ae\u7e3d\u984d' ),
			'Order Number' => self::u( '\u8a02\u55ae\u7de8\u865f' ),
			'Order Date' => self::u( '\u8a02\u55ae\u65e5\u671f' ),
			'Payment Method' => self::u( '\u652f\u4ed8\u65b9\u5f0f' ),
			'Trade ID' => self::u( '\u4ea4\u6613 ID' ),
			'Currency and Network' => self::u( '\u5e63\u7a2e\u548c\u7db2\u8def' ),
			'After payment, this page will update automatically when the transaction is confirmed.' => self::u( '\u4ed8\u6b3e\u5f8c\uff0c\u4ea4\u6613\u78ba\u8a8d\u6642\u6b64\u9801\u9762\u6703\u81ea\u52d5\u66f4\u65b0\u3002' ),
			'Payment confirmed. Refreshing order status...' => self::u( '\u4ed8\u6b3e\u5df2\u78ba\u8a8d\uff0c\u6b63\u5728\u91cd\u65b0\u6574\u7406\u8a02\u55ae\u72c0\u614b...' ),
			'The cryptocurrency payment expired. Choose a network to try again.' => self::u( '\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\u5df2\u904e\u671f\u3002\u8acb\u9078\u64c7\u7db2\u8def\u5f8c\u91cd\u8a66\u3002' ),
			'Invalid payment request.' => self::u( '\u7121\u6548\u7684\u652f\u4ed8\u8acb\u6c42\u3002' ),
			'Invalid request.' => self::u( '\u7121\u6548\u7684\u8acb\u6c42\u3002' ),
			'Order not found.' => self::u( '\u627e\u4e0d\u5230\u8a02\u55ae\u3002' ),
			'Selected payment network is not available.' => self::u( '\u6240\u9078\u652f\u4ed8\u7db2\u8def\u4e0d\u53ef\u7528\u3002' ),
			'No cryptocurrency payment network is available.' => self::u( '\u76ee\u524d\u6c92\u6709\u53ef\u7528\u7684\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\u7db2\u8def\u3002' ),
			'The order currency is not supported by BEpusdt.' => self::u( '\u8a72\u8a02\u55ae\u5e63\u7a2e\u4e0d\u53d7 BEpusdt \u652f\u63f4\u3002' ),
			'A cryptocurrency payment is already being created. Please wait a moment and try again.' => self::u( '\u6b63\u5728\u5efa\u7acb\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\uff0c\u8acb\u7a0d\u5f8c\u91cd\u8a66\u3002' ),
			'Unable to create cryptocurrency payment. Please try again.' => self::u( '\u7121\u6cd5\u5efa\u7acb\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\uff0c\u8acb\u91cd\u8a66\u3002' ),
			'BEpusdt API URL is invalid.' => self::u( 'BEpusdt API \u5730\u5740\u7121\u6548\u3002' ),
			'BEpusdt API token is missing.' => self::u( '\u7f3a\u5c11 BEpusdt API \u4ee4\u724c\u3002' ),
			'BEpusdt returned invalid JSON.' => self::u( 'BEpusdt \u56de\u50b3\u4e86\u7121\u6548\u7684 JSON\u3002' ),
			'BEpusdt request failed.' => self::u( 'BEpusdt \u8acb\u6c42\u5931\u6557\u3002' ),
			'WooCommerce Order #%s' => self::u( 'WooCommerce \u8a02\u55ae #%s' ),
			'Cryptocurrency payment started on %s.' => self::u( '\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\u5df2\u900f\u904e %s \u767c\u8d77\u3002' ),
			'Cryptocurrency payment confirmed.' => self::u( '\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\u5df2\u78ba\u8a8d\u3002' ),
			'Cryptocurrency payment expired.' => self::u( '\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8\u5df2\u904e\u671f\u3002' ),
			'Awaiting customer to choose a payment network.' => self::u( '\u7b49\u5f85\u5ba2\u6236\u9078\u64c7\u652f\u4ed8\u7db2\u8def\u3002' ),
			'Pay with cryptocurrency' => self::u( '\u4f7f\u7528\u52a0\u5bc6\u8ca8\u5e63\u652f\u4ed8' ),
			'Every five minutes' => self::u( '\u6bcf\u4e94\u5206\u9418' ),
			'BEpusdt for WooCommerce requires WooCommerce to be installed and active.' => self::u( 'BEpusdt for WooCommerce \u9700\u8981\u5b89\u88dd\u4e26\u555f\u7528 WooCommerce\u3002' ),
		);
	}
}
