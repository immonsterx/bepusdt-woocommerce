<?php
/**
 * GitHub Release updater for the public plugin.
 *
 * @package BEpusdt_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies WordPress with updates published as GitHub Releases.
 */
final class BEpusdt_WC_Updater {

	private const SLUG           = 'bepusdt-woocommerce';
	private const UPDATE_URI     = 'https://github.com/immonsterx/bepusdt-woocommerce';
	private const RELEASE_API    = 'https://api.github.com/repos/immonsterx/bepusdt-woocommerce/releases/latest';
	private const RELEASE_ASSET  = 'bepusdt-woocommerce.zip';
	private const TRANSIENT_NAME = 'bepusdt_wc_github_release';

	/**
	 * Register update hooks.
	 */
	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'filter_update' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache_after_upgrade' ), 10, 2 );
	}

	/**
	 * Return release data to WordPress' Update URI handler.
	 *
	 * @param array|false $update      Existing update data.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin file path.
	 * @param string[]    $locales     Installed locales.
	 * @return array|false
	 */
	public static function filter_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $plugin_data, $locales );

		if ( plugin_basename( BEPUSDT_WC_PATH . 'bepusdt.php' ) !== $plugin_file ) {
			return $update;
		}

		$release = self::get_release();
		if ( ! $release ) {
			return $update;
		}

		return array(
			'id'           => self::UPDATE_URI,
			'slug'         => self::SLUG,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires_php' => '7.4',
			'autoupdate'   => false,
		);
	}

	/**
	 * Populate the standard WordPress plugin details dialog.
	 *
	 * @param mixed  $result Existing API result.
	 * @param string $action Requested API action.
	 * @param object $args   API arguments.
	 * @return mixed
	 */
	public static function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::get_release();
		if ( ! $release ) {
			return $result;
		}

		$changelog = empty( $release['notes'] )
			? esc_html__( '请前往 GitHub Release 页面查看版本说明。', 'bepusdt-woocommerce' )
			: nl2br( esc_html( $release['notes'] ) );

		return (object) array(
			'name'          => 'BEpusdt for WooCommerce',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => '<a href="https://guaishoux.com/">我是怪兽</a>',
			'homepage'      => self::UPDATE_URI,
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'download_link' => $release['package'],
			'last_updated'  => $release['published_at'],
			'sections'      => array(
				'description' => esc_html__( '为 WooCommerce 提供轻量级 BEpusdt 加密货币支付网关。', 'bepusdt-woocommerce' ),
				'changelog'   => $changelog,
			),
		);
	}

	/**
	 * Clear cached release metadata after this plugin is upgraded.
	 *
	 * @param object $upgrader Upgrader instance.
	 * @param array  $options  Upgrade context.
	 */
	public static function clear_cache_after_upgrade( $upgrader, $options ) {
		unset( $upgrader );

		if ( 'plugin' !== ( $options['type'] ?? '' ) || 'update' !== ( $options['action'] ?? '' ) ) {
			return;
		}

		$plugins = isset( $options['plugins'] ) && is_array( $options['plugins'] ) ? $options['plugins'] : array();
		if ( in_array( plugin_basename( BEPUSDT_WC_PATH . 'bepusdt.php' ), $plugins, true ) ) {
			delete_site_transient( self::TRANSIENT_NAME );
		}
	}

	/**
	 * Load and normalize the latest non-draft GitHub Release.
	 *
	 * @return array|false
	 */
	private static function get_release() {
		$cached = get_site_transient( self::TRANSIENT_NAME );
		if ( is_array( $cached ) ) {
			return ! empty( $cached['failed'] ) ? false : $cached;
		}

		$response = wp_remote_get(
			self::RELEASE_API,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'               => 'application/vnd.github+json',
					'User-Agent'            => 'BEpusdt-WooCommerce/' . BEPUSDT_WC_VERSION,
					'X-GitHub-Api-Version'  => '2022-11-28',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			self::cache_failure();
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			self::cache_failure();
			return false;
		}

		$version = isset( $data['tag_name'] ) ? ltrim( (string) $data['tag_name'], "vV \t\n\r\0\x0B" ) : '';
		if ( ! preg_match( '/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
			self::cache_failure();
			return false;
		}

		$package = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( self::RELEASE_ASSET === ( $asset['name'] ?? '' ) ) {
				$package = esc_url_raw( (string) ( $asset['browser_download_url'] ?? '' ) );
				break;
			}
		}

		if ( ! $package ) {
			self::cache_failure();
			return false;
		}

		$release = array(
			'version'      => $version,
			'url'          => esc_url_raw( (string) ( $data['html_url'] ?? self::UPDATE_URI . '/releases/latest' ) ),
			'package'      => $package,
			'published_at' => sanitize_text_field( (string) ( $data['published_at'] ?? '' ) ),
			'notes'        => sanitize_textarea_field( (string) ( $data['body'] ?? '' ) ),
		);

		set_site_transient( self::TRANSIENT_NAME, $release, 6 * HOUR_IN_SECONDS );
		return $release;
	}

	/**
	 * Avoid repeated remote requests when GitHub is unavailable or incomplete.
	 */
	private static function cache_failure() {
		set_site_transient( self::TRANSIENT_NAME, array( 'failed' => true ), 15 * MINUTE_IN_SECONDS );
	}
}
