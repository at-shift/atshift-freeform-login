<?php
/**
 * Plugin bootstrap.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts the plugin modules.
 */
final class Atshift_Freeform_Login {
	/** @var self|null */
	private static $instance = null;

	/**
	 * Return the shared plugin instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create default settings without changing the active login screen.
	 *
	 * @return void
	 */
	public static function activate() {
		add_option(
			Atshift_Freeform_Login_Settings::OPTION_KEY,
			Atshift_Freeform_Login_Settings::defaults(),
			'',
			false
		);
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		new Atshift_Freeform_Login_Jetpack();
		new Atshift_Freeform_Login_Settings();
		new Atshift_Freeform_Login_Screen();
		new Atshift_Freeform_Login_Shortcode();
	}

	/**
	 * Load bundled translations for the privately distributed plugin.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- The source package includes a Japanese translation for immediate use outside WordPress.org language packs.
		load_plugin_textdomain(
			'atshift-freeform-login',
			false,
			dirname( plugin_basename( ATSHIFT_FREEFORM_LOGIN_FILE ) ) . '/languages'
		);
	}
}
