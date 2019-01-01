<?php
/**
 * GoDaddy Reseller Store Settings class.
 *
 * Manage custom filters for the reseller store plubin
 *
 * @class    Reseller_Store_Settings/Settings
 * @package  Reseller_Store_Settings/Settings
 * @category Class
 * @author   GoDaddy
 * @since    1.0.0
 */

namespace Reseller_Store_Settings;

use stdClass;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Settings {

	/**
	 * Post type slug.
	 *
	 * @since 0.3.3
	 *
	 * @var string
	 */
	const SLUG = 'reseller_product';

	/**
	 * Custom Post Type Page
	 *
	 * @since 0.3.3
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'edit.php?post_type=reseller_product';


	/**
	 * Settings Page
	 *
	 * @since 0.3.3
	 *
	 * @var string
	 */
	const SETTINGS_PAGE_SLUG = 'options-general.php?page=reseller-store-settings';

	/**
	 * Array of Currencies.
	 *
	 * @since 0.3.3
	 *
	 * @var array
	 */
	static $currencies = [ 'default', 'USD', 'AED', 'ARS', 'AUD', 'BRL', 'CAD', 'CHF', 'CLP', 'CNY', 'COP', 'CZK', 'DKK', 'EGP', 'EUR', 'GBP', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'JPY', 'KRW', 'MAD', 'MXN', 'MYR', 'NOK', 'NZD', 'PEN', 'PHP', 'PKR', 'PLN', 'RON', 'RUB', 'SAR', 'SEK', 'SGD', 'THB', 'TRY', 'TWD', 'UAH', 'UYU', 'VND', 'ZAR' ];

	/**
	 * Array of markests.
	 *
	 * @since 0.3.3
	 *
	 * @var array
	 */
	static $markets = [ 'default', 'da-DK', 'de-DE', 'el-GR', 'en-US', 'es-MX', 'fi-FI', 'fr-FR', 'hi-IN', 'id-ID', 'it-IT', 'ja-JP', 'ko-KR', 'mr-IN', 'nb-NO', 'nl-NL', 'pl-PL', 'pt-BR', 'pt-PT', 'ru-RU', 'sv-SE', 'ta-IN', 'th-TH', 'tr-TR', 'uk-UA', 'vi-VN', 'zh-CN', 'zh-TW' ];

	/**
	 * Class constructor.
	 *
	 * @since 0.3.3
	 */
	public function __construct() {

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'admin_init', [ $this, 'reseller_register_settings' ] );
		add_action( 'admin_menu', [ $this, 'register' ] );
		add_action( 'wp_ajax_rstore_settings_save', [ __CLASS__, 'save' ] );

		$api_tld = rstore_get_option( 'api_tld' );
		if ( ! empty( $api_tld ) ) {
			add_filter( 'rstore_api_tld', [ $this, 'api_tld_filter' ] );
			add_filter( 'rstore_domain_search_html', [ $this, 'rstore_domain_search_html' ] );
		}

		$setup_rcc = rstore_get_option( 'setup_rcc' );
		if ( ! empty( $setup_rcc ) ) {
			add_filter( 'rstore_setup_rcc', [ $this, 'setup_rcc_filter' ] );
		}

		$rstore_sync_ttl = rstore_get_option( 'rstore_sync_ttl' );
		if ( $rstore_sync_ttl ) {
			add_filter( 'rstore_sync_ttl', [ $this, 'rstore_sync_ttl_filter' ] );
		}

		add_filter( 'rstore_api_query_args', [ $this, 'rstore_api_query_args_filter' ] );

		add_action(
			'add_meta_boxes', function () {
				add_meta_box(
					'debug-' . self::SLUG, 'Debug Info', function () {
						global $post;
						echo var_dump( get_post_meta( $post->ID ) );

					}, self::SLUG, 'advanced', 'low'
				);
			}
		);

	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @action admin_enqueue_scripts
	 * @since  0.3.3
	 */
	public function admin_enqueue_scripts() {

		if ( ! ( rstore_is_admin_uri( self::PAGE_SLUG, false ) ||
			rstore_is_admin_uri( self::SETTINGS_PAGE_SLUG, false ) ) ) {

			return;

		}

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'rstore-settings', Plugin::assets_url( "js/advanced-settings{$suffix}.js" ), [ 'jquery' ], rstore()->version, true );

		wp_enqueue_script( 'rstore-magnific-popup', Plugin::assets_url( "js/magnific-popup{$suffix}.js" ), [ 'jquery' ], rstore()->version, true );

		wp_enqueue_script( 'rstore-clipboard', Plugin::assets_url( 'js/clipboard.min.js' ), [ 'jquery' ], rstore()->version, true );

		wp_enqueue_style( 'rstore-magnific-popup-css', Plugin::assets_url( "css/magnific-popup{$suffix}.css" ), rstore()->version, true );

		wp_enqueue_style( 'rstore-settings-css', Plugin::assets_url( "css/advanced-settings{$suffix}.css" ), rstore()->version, true );

	}

	/**
	 * Register the settings page.
	 *
	 * @action init
	 * @since  0.3.3
	 */
	public function register() {

		if ( function_exists( 'rstore_is_setup' ) && ( ! rstore_is_setup() || ! rstore_has_products() ) ) {

			add_options_page(
				self::SETTINGS_PAGE_SLUG,
				esc_html__( 'Reseller Store Settings', 'reseller-store-settings' ),
				'manage_options',
				'reseller-store-settings',
				[ $this, 'edit_settings' ]
			);
			return;
		}

		add_submenu_page(
			self::PAGE_SLUG,
			esc_html__( 'Reseller Store Settings', 'reseller-store-settings' ),
			esc_html__( 'Settings', 'reseller-store-settings' ),
			'manage_options',
			'reseller-store-settings',
			[ $this, 'edit_settings' ]
		);
	}

	/**
	 * Register the api tld filter
	 *
	 * @action init
	 * @since  0.3.3
	 */
	public function api_tld_filter() {

		return rstore_get_option( 'api_tld' );
	}

	/**
	 * Register the rstore domain html filter
	 *
	 * @action init
	 * @since  0.3.3
	 *
	 * @param array $html Html for domain search.
	 * @return null|string|string[]
	 */
	public function rstore_domain_search_html( $html ) {
		$pattern     = '/(<div.)(.*)(>.*<\/div>)/';
		$replacement = '${1} ${2} data-base_url="' . rstore_get_option( 'api_tld' ) . '"" ${3}';
		return preg_replace( $pattern, $replacement, $html );
	}

	/**
	 * Register the setup rcc url filter
	 *
	 * @action init
	 * @since  1.1.0
	 */
	public function setup_rcc_filter() {

		return rstore_get_option( 'setup_rcc' );
	}

	/**
	 * Register the api request args
	 *
	 * @action init
	 * @since  0.3.3
	 *
	 * @param array $args Query string args for api url.
	 * @return array
	 */
	public function rstore_api_query_args_filter( $args ) {

		$market   = rstore_get_option( 'api_market' );
		$currency = rstore_get_option( 'api_currency' );

		if ( ! empty( $market ) && 'default' !== $market ) {
			$args['marketId'] = $market;
		}

		if ( ! empty( $currency ) && 'default' !== $currency ) {
			$args['currencyType'] = $currency;
		}

		return $args;

	}

	/**
	 * Register the rstore_sync_ttl_filter filter
	 *
	 * @action init
	 * @since  0.3.3
	 */
	public function rstore_sync_ttl_filter() {

		return rstore_get_option( 'rstore_sync_ttl' );
	}

	/**
	 * Edit settings
	 *
	 * @since  0.3.3
	 */
	function edit_settings() {

		if ( ! ( rstore_is_admin_uri( self::PAGE_SLUG, false ) ||
			rstore_is_admin_uri( self::SETTINGS_PAGE_SLUG, false ) ) ) {

			return;

		}

		$this->settings_output();

	}

	/**
	 * Build settings array
	 *
	 * @since  0.3.3
	 */
	static function reseller_settings() {

		$settings   = array();
		$settings[] = array(
			'name'        => 'pl_id',
			'label'       => esc_html__( 'Private Label Id', 'reseller-store-settings' ),
			'type'        => 'number',
			'description' => esc_html__( 'The private label id that you have set for your storefront.', 'reseller-store-settings' ),
		);
		$settings[] = array(
			'name'        => 'api_currency',
			'label'       => esc_html__( 'Currency', 'reseller-store-settings' ),
			'type'        => 'select',
			'list'        => self::$currencies,
			'description' => esc_html__( 'Set the currency to display on your storefront.', 'reseller-store-settings' ),
		);
		$settings[] = array(
			'name'        => 'api_market',
			'label'       => esc_html__( 'Override Api Market', 'reseller-store-settings' ),
			'type'        => 'select',
			'list'        => self::$markets,
			'description' => esc_html__( 'Override your default language selected in the wordpress setup.', 'reseller-store-settings' ),
		);
		$settings[] = array(
			'name'        => 'sync_ttl',
			'label'       => esc_html__( 'Api Sync TTL (seconds)', 'reseller-store-settings' ),
			'type'        => 'number',
			'description' => esc_html__( 'Reseller store will check the api for changes periodically. The default is 15 minutes (900 seconds).', 'reseller-store-settings' ),
		);
		$settings[] = array(
			'name'  => 'last_sync',
			'label' => esc_html__( 'Last Api Sync', 'reseller-store-settings' ),
			'type'  => 'time',
		);
		$settings[] = array(
			'name'  => 'next_sync',
			'label' => esc_html__( 'Next Api Sync', 'reseller-store-settings' ),
			'type'  => 'time',
		);
		$settings[] = array(
			'name'        => 'api_tld',
			'label'       => esc_html__( 'Api Url', 'reseller-store-settings' ),
			'type'        => 'text',
			'description' => esc_html__( 'Set url for internal testing. (i.e. secureserver.net)', 'reseller-store-settings' ),
		);
		$settings[] = array(
			'name'        => 'setup_rcc',
			'label'       => esc_html__( 'RCC Url', 'reseller-store-settings' ),
			'type'        => 'text',
			'description' => esc_html__( 'Set url for internal testing. (i.e. https://reseller.godaddy.com)', 'reseller-store-settings' ),
		);
		return $settings;
	}

	/**
	 * Register settings
	 *
	 * @since  0.3.3
	 */
	function reseller_register_settings() {
		$settings = self::reseller_settings();
		foreach ( $settings as $setting ) {
			register_setting( 'reseller_settings', $setting['name'] );
		}
	}

	/**
	 * Admin settings ui
	 *
	 * @since  0.3.3
	 */
	function settings_output() {

		$settings = self::reseller_settings();

		?>
		<style type="text/css">
				.rstore-spinner {
			visibility: hidden;
			max-width: 20px;
			height: auto;
			margin-bottom: -4px;
		}
		</style>


		<div class="wrap">
			<h1> <?php esc_html_e( 'Reseller Store Settings', 'reseller-store-settings' ); ?> </h1>
			<form id="rstore-settings-form" >
			<table class="form-table">
			<tbody>

		<?php
		wp_nonce_field( 'rstore_settings_save', 'nonce' );

		settings_fields( 'reseller_settings' );

		foreach ( $settings as $setting ) {
			switch ( $setting['type'] ) {
				case 'text':
					echo '<tr>';
					echo '<th><label for="' . $setting['name'] . '">' . $setting['label'] . '</label></th>';
					echo '<td><input type="text" id="' . $setting['name'] . '" name="' . $setting['name'] . '" value="' . rstore_get_option( $setting['name'] ) . '" class="regular-text">';
					break;
				case 'number':
					echo '<tr>';
					echo '<th><label for="' . $setting['name'] . '">' . $setting['label'] . '</label></th>';
					echo '<td><input type="number" id="' . $setting['name'] . '" name="' . $setting['name'] . '" value="' . rstore_get_option( $setting['name'] ) . '" class="regular-text">';
					break;
				case 'time':
					$sync_time = get_date_from_gmt( date( 'Y-m-d H:i:s', rstore_get_option( $setting['name'] ) ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
					echo '<tr>';
					echo '<th><label for="' . $setting['name'] . '">' . $setting['label'] . '</label></th>';
					echo '<td><label id="' . $setting['name'] . '" >' . $sync_time . '</label>';
					break;
				case 'checkbox':
					echo '<tr>';
					echo '<th><label for="' . $setting['name'] . '">' . $setting['label'] . '</label></th>';
					echo '<td><input type="checkbox" id="' . $setting['name'] . '" name="' . $setting['name'] . '" value="1" ' . checked( rstore_get_option( $setting['name'], 0 ), 1, false ) . '  />';
					break;
				case 'select':
					echo '<tr>';

					echo '<th><label for="' . $setting['name'] . '">' . $setting['label'] . '</label></th>';
					echo '<td><select title="' . $setting['label'] . '" id="' . $setting['name'] . '" name="' . $setting['name'] . '" >';
					foreach ( $setting['list'] as $item ) {
						if ( rstore_get_option( $setting['name'] ) === $item ) {
							echo "<option selected=\"selected\" value=\"$item\">$item</option>";
						} else {
							echo "<option value=\"$item\">$item</option>";
						}
					}
					echo  '</select>';
					break;
			}
			if ( array_key_exists( 'description', $setting ) ) {
				echo '<p class="description" id="tagline-description">' . $setting['description'] . '</p></td>';
			}
			echo '</td></tr>';
		}
		?>
			</tbody>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'reseller-store-settings' ); ?></button>
				<img src="<?php echo esc_url( includes_url( 'images/spinner-2x.gif' ) ); ?>" class="rstore-spinner">
			</p>
			</form>
		</div>

		<?php

		$this->import_button();

		$this->export_button();

	}

	/**
	 * Generate import button
	 *
	 * @since  0.3.3
	 */
	function import_button() {

		$url = sprintf(
			'%s?page=reseller-store-setup&nonce=%s&rstore_plid=%s',
			admin_url( 'admin.php' ),
			wp_create_nonce( rstore_prefix( 'install-' . get_current_user_id() ) ),
			rstore_get_option( 'pl_id' )
		);

		echo sprintf(
			'<div class="wrap rstore-settings-import"><a class="button" href="%s">%s</a></div>',
			$url,
			esc_html( 'Import products', 'reseller-store-settings' )
		);

	}

	/**
	 * Generate export button
	 *
	 * @since  0.3.3
	 */
	function export_button() {
		?>
			<div class="wrap">
				<form id='rstore-settings-export'>
				<?php wp_nonce_field( 'rstore_export', 'nonce' ); ?>
					<input type="hidden" name="action" value="rstore_export">
					<button type="submit" class="button link" ><?php esc_html_e( 'Export Products', 'reseller-store-settings' ); ?></button>
				</form>

				<div id="json-generator" class="json-generator mfp-hide mfp-with-anim">
					<div class="json-content">
						<div id="header">
						</div>
					</div>
					<div class="container">
						<button id='clipboard' class="button button-primary" data-clipboard-action="copy" data-clipboard-target="#json-text"><?php esc_html_e( 'Copy to clipboard', 'reseller-store-settings' ); ?></button>
						<div id="json-content">
							<p><textarea id="json-text"> </textarea></p>
						</div>
					</div>
				</div>
		</div>

		<?php

	}

	/**
	 * Save Reseller Store Settings
	 *
	 * @action wp_ajax_rstore_settings_save
	 * @global wpdb $wpdb
	 * @since  0.3.3
	 */
	public static function save() {

		$nonce = filter_input( INPUT_POST, 'nonce' );

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'rstore_settings_save' ) ) {
			wp_send_json_error(
				esc_html__( 'Error: Invalid Session. Refresh the page and try again.', 'reseller-store-settings' )
			);
			return;
		}

		$pl_id = absint( filter_input( INPUT_POST, 'pl_id' ) );

		if ( 0 === $pl_id ) {
			wp_send_json_error(
				esc_html__( 'Error: Invalid Private Label ID.', 'reseller-store-settings' )
			);
			return;

		}

		$settings = self::reseller_settings();
		foreach ( $settings as $setting ) {

			if ( 'time' === $setting['type'] ) {
				continue;
			}

			$val = filter_input( INPUT_POST, $setting['name'] );
			if ( 'number' === $setting['type'] ) {
				$val = absint( $val );
			}

			if ( empty( $val ) ) {
				rstore_delete_option( $setting['name'] );
			} else {
				rstore_update_option( $setting['name'], $val );
			}
		}

		rstore_delete_option( 'next_sync' ); // force a rsync update.

		wp_send_json_success();
	}
}
