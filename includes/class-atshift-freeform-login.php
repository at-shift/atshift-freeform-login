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
		new Atshift_Freeform_Login_Jetpack();
		new Atshift_Freeform_Login_Settings();
		new Atshift_Freeform_Login_Screen();
		new Atshift_Freeform_Login_Shortcode();
	}
}
