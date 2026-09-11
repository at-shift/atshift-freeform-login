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
		add_action( 'delete_user', array( $this, 'erase_deleted_user_passkeys' ) );
		add_action( 'deleted_user', array( $this, 'erase_deleted_user_passkeys' ) );
		add_action( 'wpmu_delete_user', array( $this, 'erase_network_deleted_user_passkeys' ) );
		add_filter( 'atshift_members_erase_user_data', array( $this, 'erase_member_passkeys' ), 10, 2 );
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
	 * Remove passkey data before atshift Members deletes an account.
	 *
	 * Returning an error keeps the Members erasure job pending so it can retry
	 * without reporting an incomplete account deletion as successful.
	 *
	 * @param WP_Error $errors  Existing erasure errors.
	 * @param int      $user_id User ID.
	 * @return WP_Error
	 */
	public function erase_member_passkeys( $errors, $user_id ) {
		if ( ! $errors instanceof WP_Error ) {
			$errors = new WP_Error();
		}

		$storage = new Atshift_Freeform_Login_Passkey_Storage();

		if ( ! $storage->erase_user_credentials( $user_id ) ) {
			$errors->add(
				'atshift_passkey_erasure_pending',
				__( 'Passkey cleanup is pending.', 'atshift-freeform-login' )
			);
		}

		return $errors;
	}

	/**
	 * Remove passkey data during ordinary WordPress user deletion.
	 *
	 * This runs both before and after core deletion. The second idempotent pass
	 * retries index cleanup if a concurrent update prevented the first pass.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function erase_deleted_user_passkeys( $user_id ) {
		if ( is_multisite() ) {
			return;
		}

		$storage = new Atshift_Freeform_Login_Passkey_Storage();
		$storage->erase_user_credentials( $user_id );
	}

	/**
	 * Remove passkey data when a user is deleted from a multisite network.
	 *
	 * Removing a user from one site must not erase their network-wide passkeys,
	 * so multisite cleanup is attached only to the network deletion hook.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function erase_network_deleted_user_passkeys( $user_id ) {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			try {
				$storage = new Atshift_Freeform_Login_Passkey_Storage();
				$storage->erase_user_credentials( $user_id );
			} finally {
				restore_current_blog();
			}
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
			? 'https://plugins.at-shift.net/freeform-login/#pricing'
			: 'https://plugins.at-shift.net/en/freeform-login/#pricing';
		$pro_link    = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer"><strong>%2$s</strong></a>',
			esc_url( $upgrade_url ),
			esc_html__( 'Purchase Pro add-on', 'atshift-freeform-login' )
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

		$details_url = 'https://wordpress.org/plugins/atshift-freeform-login/';
		$upgrade_url = 0 === strpos( determine_locale(), 'ja' )
			? 'https://plugins.at-shift.net/freeform-login/#pricing'
			: 'https://plugins.at-shift.net/en/freeform-login/#pricing';
		$links         = array(
			 sprintf(
				/* translators: %s: Plugin version. */
				esc_html__( 'Version %s' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Reuse the WordPress core plugin-row translation.
				esc_html( isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : ATSHIFT_FREEFORM_LOGIN_VERSION )
			),
			 sprintf(
				/* translators: %s: Plugin author. */
				__( 'By %s' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Reuse the WordPress core plugin-row translation.
				'<a href="' . esc_url( 'https://plugins.at-shift.net/' ) . '" target="_blank" rel="noopener noreferrer">@shift</a>'
			),
			sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $details_url ),
				// phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Reuse the WordPress core plugin-row translation.
				esc_html__( 'View details' )
			),
		);

		if ( ! $this->is_pro_installed() ) {
			$links[] = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $upgrade_url ),
				esc_html__( 'Purchase Pro add-on', 'atshift-freeform-login' )
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
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- The source package includes bundled translations for immediate use outside WordPress.org language packs.
		load_plugin_textdomain(
			'atshift-freeform-login',
			false,
			dirname( plugin_basename( ATSHIFT_FREEFORM_LOGIN_FILE ) ) . '/languages'
		);
	}
}
