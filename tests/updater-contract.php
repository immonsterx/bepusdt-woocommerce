<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'BEPUSDT_WC_PATH', '/srv/wp-content/plugins/bepusdt-woocommerce/' );
define( 'BEPUSDT_WC_VERSION', '1.5.0' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['bepusdt_test_hooks']       = array();
$GLOBALS['bepusdt_test_transients']  = array();
$GLOBALS['bepusdt_test_remote_body'] = array();
$GLOBALS['bepusdt_test_remote_calls'] = 0;

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['bepusdt_test_hooks'][ $hook ] = array( $callback, $priority, $accepted_args );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	add_filter( $hook, $callback, $priority, $accepted_args );
}

function plugin_basename( $path ) {
	$path = str_replace( '\\', '/', $path );
	return implode( '/', array_slice( explode( '/', trim( $path, '/' ) ), -2 ) );
}

function get_site_transient( $name ) {
	return $GLOBALS['bepusdt_test_transients'][ $name ] ?? false;
}

function set_site_transient( $name, $value, $expiration ) {
	$GLOBALS['bepusdt_test_transients'][ $name ] = $value;
	$GLOBALS['bepusdt_test_transients'][ $name . '_expiration' ] = $expiration;
	return true;
}

function delete_site_transient( $name ) {
	unset( $GLOBALS['bepusdt_test_transients'][ $name ], $GLOBALS['bepusdt_test_transients'][ $name . '_expiration' ] );
	return true;
}

function wp_remote_get( $url, $args ) {
	++$GLOBALS['bepusdt_test_remote_calls'];
	assert( 'https://api.github.com/repos/immonsterx/bepusdt-woocommerce/releases/latest' === $url );
	assert( 'application/vnd.github+json' === $args['headers']['Accept'] );
	return array(
		'response' => array( 'code' => 200 ),
		'body'     => json_encode( $GLOBALS['bepusdt_test_remote_body'] ),
	);
}

function is_wp_error( $value ) {
	return false;
}

function wp_remote_retrieve_response_code( $response ) {
	return $response['response']['code'];
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}

function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
}

function sanitize_text_field( $text ) {
	return trim( strip_tags( $text ) );
}

function sanitize_textarea_field( $text ) {
	return trim( strip_tags( $text ) );
}

function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( $text, $domain ) {
	return esc_html( $text );
}

function bepusdt_test_release( $tag = 'v1.6.0', $asset = 'bepusdt-woocommerce.zip' ) {
	return array(
		'tag_name'     => $tag,
		'draft'        => false,
		'prerelease'   => false,
		'html_url'     => 'https://github.com/immonsterx/bepusdt-woocommerce/releases/tag/' . $tag,
		'published_at' => '2026-10-25T00:00:00Z',
		'body'         => "Release notes\nSecond line",
		'assets'       => $asset ? array(
			array(
				'name'                 => $asset,
				'browser_download_url' => 'https://github.com/immonsterx/bepusdt-woocommerce/releases/download/' . $tag . '/' . $asset,
			),
		) : array(),
	);
}

require_once dirname( __DIR__ ) . '/includes/class-bepusdt-updater.php';

BEpusdt_WC_Updater::init();

assert( isset( $GLOBALS['bepusdt_test_hooks']['update_plugins_github.com'] ) );
assert( 4 === $GLOBALS['bepusdt_test_hooks']['update_plugins_github.com'][2] );
assert( isset( $GLOBALS['bepusdt_test_hooks']['plugins_api'] ) );

$GLOBALS['bepusdt_test_remote_body'] = bepusdt_test_release();
$update = BEpusdt_WC_Updater::filter_update( false, array(), 'bepusdt-woocommerce/bepusdt.php', array( 'zh_CN' ) );

assert( '1.6.0' === $update['version'] );
assert( 'bepusdt-woocommerce' === $update['slug'] );
assert( 'https://github.com/immonsterx/bepusdt-woocommerce/releases/download/v1.6.0/bepusdt-woocommerce.zip' === $update['package'] );
assert( 6 * HOUR_IN_SECONDS === $GLOBALS['bepusdt_test_transients']['bepusdt_wc_github_release_expiration'] );
assert( 1 === $GLOBALS['bepusdt_test_remote_calls'] );

$cached_update = BEpusdt_WC_Updater::filter_update( false, array(), 'bepusdt-woocommerce/bepusdt.php', array() );
assert( $update === $cached_update );
assert( 1 === $GLOBALS['bepusdt_test_remote_calls'] );

$unrelated = array( 'unchanged' => true );
assert( $unrelated === BEpusdt_WC_Updater::filter_update( $unrelated, array(), 'another/plugin.php', array() ) );

$information = BEpusdt_WC_Updater::plugin_information( false, 'plugin_information', (object) array( 'slug' => 'bepusdt-woocommerce' ) );
assert( '1.6.0' === $information->version );
assert( false !== strpos( $information->sections['changelog'], '<br />' ) );

BEpusdt_WC_Updater::clear_cache_after_upgrade(
	null,
	array(
		'type'    => 'plugin',
		'action'  => 'update',
		'plugins' => array( 'bepusdt-woocommerce/bepusdt.php' ),
	)
);
assert( false === get_site_transient( 'bepusdt_wc_github_release' ) );

$GLOBALS['bepusdt_test_remote_body'] = bepusdt_test_release( 'v1.6.1', '' );
assert( false === BEpusdt_WC_Updater::filter_update( false, array(), 'bepusdt-woocommerce/bepusdt.php', array() ) );
assert( 15 * MINUTE_IN_SECONDS === $GLOBALS['bepusdt_test_transients']['bepusdt_wc_github_release_expiration'] );
assert( 2 === $GLOBALS['bepusdt_test_remote_calls'] );

assert( false === BEpusdt_WC_Updater::filter_update( false, array(), 'bepusdt-woocommerce/bepusdt.php', array() ) );
assert( 2 === $GLOBALS['bepusdt_test_remote_calls'] );

echo "Updater contract checks passed.\n";
