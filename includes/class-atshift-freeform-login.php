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
		add_filter( 'plugin_action_links_' . plugin_basename( ATSHIFT_FREEFORM_LOGIN_FILE ), array( $this, 'filter_plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'filter_plugin_row_meta' ), 10, 4 );

		new Atshift_Freeform_Login_Jetpack();
		new Atshift_Freeform_Login_Settings();
		new Atshift_Freeform_Login_Screen();
		new Atshift_Freeform_Login_Shortcode();

		if ( class_exists( 'Atshift_Freeform_Login_Passkeys' ) ) {
			new Atshift_Freeform_Login_Passkeys();
		} else {
			new Atshift_Freeform_Login_Passkey_Profile( new Atshift_Freeform_Login_Passkey_Storage() );
		}
	}

	/**
	 * Add settings and optional Pro purchase links to the plugin row.
	 *
	 * @param array<int, string> $links Existing plugin action links.
	 * @return array<int, string>
	 */
	public function filter_plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( Atshift_Freeform_Login_Settings::admin_page_url() ),
			esc_html__( 'Settings', 'atshift-freeform-login' )
		);

		array_unshift( $links, $settings_link );

		if ( $this->is_pro_installed() ) {
			return $links;
		}

		$upgrade_url = 0 === strpos( determine_locale(), 'ja' )
			? 'https://upf.at-shift.net/freeform-login/#pricing'
			: 'https://upf.at-shift.net/en/freeform-login/#pricing';
		$pro_link    = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer"><strong>%2$s</strong></a>',
			esc_url( $upgrade_url ),
			esc_html__( 'Upgrade to Pro', 'atshift-freeform-login' )
		);

		array_splice( $links, 1, 0, array( $pro_link ) );

		return $links;
	}

	/**
	 * Determine whether the Pro add-on is installed, including when inactive.
	 *
	 * @return bool
	 */
	private function is_pro_installed() {
		if ( defined( 'ATSHIFT_FREEFORM_LOGIN_PRO_FILE' ) ) {
			return true;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_plugins() ) as $plugin_file ) {
			if ( 'atshift-freeform-login-pro.php' === basename( $plugin_file ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the plugin metadata row in the shared atshift order.
	 *
	 * @param array<int, string>   $links       Existing plugin metadata links.
	 * @param string               $plugin_file Plugin basename.
	 * @param array<string, mixed> $plugin_data Parsed plugin headers.
	 * @param string               $status      Plugin status.
	 * @return array<int, string>
	 */
	public function filter_plugin_row_meta( $links, $plugin_file, $plugin_data, $status ) {
		$original_links = $links;
		unset( $status );

		if ( plugin_basename( ATSHIFT_FREEFORM_LOGIN_FILE ) !== $plugin_file ) {
			return $original_links;
		}

		$details_url   = 'https://wordpress.org/plugins/atshift-freeform-login/';
		$translate_url = 'https://translate.wordpress.org/projects/wp-plugins/atshift-freeform-login/';
		$upgrade_url   = 0 === strpos( determine_locale(), 'ja' )
			? 'https://upf.at-shift.net/freeform-login/#pricing'
			: 'https://upf.at-shift.net/en/freeform-login/#pricing';
		$links         = array(
			 sprintf(
				/* translators: %s: Plugin version. */
				esc_html__( 'Version %s' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Reuse the WordPress core plugin-row translation.
				esc_html( isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : ATSHIFT_FREEFORM_LOGIN_VERSION )
			),
			 sprintf(
				/* translators: %s: Plugin author. */
				__( 'By %s' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Reuse the WordPress core plugin-row translation.
				'<a href="' . esc_url( 'https://cfs.at-shift.net/' ) . '" target="_blank" rel="noopener noreferrer">@shift</a>'
			),
			sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $details_url ),
				// phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Reuse the WordPress core plugin-row translation.
				esc_html__( 'View details' )
			),
			sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $translate_url ),
				esc_html__( 'Translate', 'atshift-freeform-login' )
			),
		);

		if ( ! $this->is_pro_installed() ) {
			$links[] = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $upgrade_url ),
				esc_html__( 'Upgrade to Pro', 'atshift-freeform-login' )
			);
		}

		return $links;
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
