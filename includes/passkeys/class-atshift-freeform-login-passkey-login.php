<?php
/**
 * Passkey login UI integration.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds passkey login to wp-login.php and the login shortcode.
 */
class Atshift_Freeform_Login_Passkey_Login {
	/** @var Atshift_Freeform_Login_Passkey_Storage */
	private $storage;

	/**
	 * Constructor.
	 *
	 * @param Atshift_Freeform_Login_Passkey_Storage $storage Storage service.
	 */
	public function __construct( $storage ) {
		$this->storage = $storage;

		add_action( 'login_form', array( $this, 'render_login_screen_button' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_assets' ) );
		add_filter( 'atshift_freeform_login_shortcode_passkey_html', array( $this, 'shortcode_html' ), 10, 3 );
		add_filter( 'atshift_freeform_login_standalone_passkey_html', array( $this, 'standalone_shortcode_html' ), 10, 3 );
	}

	/** @return bool */
	private function is_available() {
		return Atshift_Freeform_Login_Passkey_Environment::is_available()
			&& $this->storage->has_credentials()
			&& ! Atshift_Freeform_Login_Jetpack::hides_local_login()
			&& ! Atshift_Freeform_Login_Jetpack::forwards_to_wordpress_com();
	}

	/** @return void */
	public function enqueue_login_assets() {
		if ( ! $this->is_available() ) {
			return;
		}

		wp_enqueue_style(
			'atshift-freeform-login-passkey-login',
			ATSHIFT_FREEFORM_LOGIN_URL . 'assets/passkey-login.css',
			array( 'login' ),
			ATSHIFT_FREEFORM_LOGIN_VERSION
		);
		$this->enqueue_script();
	}

	/** @return void */
	public function render_login_screen_button() {
		if ( ! $this->is_available() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only login redirect.
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : admin_url();
		$redirect = wp_validate_redirect( $redirect, admin_url() );

		echo $this->button_html( $redirect, 'atshift-freeform-login-passkey-login-screen' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by button_html().
	}

	/**
	 * Add passkeys to the shortcode without coupling its PHP 7.4 path to WebAuthn.
	 *
	 * @param string $html Existing integration HTML.
	 * @param string $redirect Safe post-login redirect.
	 * @param bool   $sso_only Whether Jetpack requires SSO.
	 * @return string
	 */
	public function shortcode_html( $html, $redirect, $sso_only ) {
		if ( $sso_only || ! $this->is_available() ) {
			return $html;
		}

		$this->enqueue_script();

		return $html . $this->button_html( $redirect, 'atshift-freeform-login-passkey-login-shortcode' );
	}

	/**
	 * Render a passkey button that can sit beside another plugin's login form.
	 *
	 * @param string $html Existing integration HTML.
	 * @param string $redirect Safe post-login redirect.
	 * @param bool   $remember Whether to request a persistent login cookie.
	 * @return string
	 */
	public function standalone_shortcode_html( $html, $redirect, $remember ) {
		if ( ! $this->is_available() ) {
			return $html;
		}

		$this->enqueue_script();

		return $html . $this->button_html( $redirect, 'atshift-freeform-login-passkey-login-standalone', false, $remember );
	}

	/** @return void */
	private function enqueue_script() {
		wp_enqueue_script(
			'atshift-freeform-login-passkey-login',
			ATSHIFT_FREEFORM_LOGIN_URL . 'assets/passkey-login.js',
			array(),
			ATSHIFT_FREEFORM_LOGIN_VERSION,
			true
		);
		wp_localize_script(
			'atshift-freeform-login-passkey-login',
			'atshiftFreeformLoginPasskeyLogin',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'atshift-freeform-login/v1/passkeys/authentication/' ) ),
				'messages' => array(
					'unsupported'    => __( 'This browser does not support passkeys.', 'atshift-freeform-login' ),
					'authenticating' => __( 'Waiting for your passkey...', 'atshift-freeform-login' ),
					'failed'         => __( 'Passkey login failed.', 'atshift-freeform-login' ),
				),
			)
		);
	}

	/**
	 * Build a login button and accessible status region.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $context_class Context class.
	 * @param bool   $include_separator Whether to append the local-login separator.
	 * @param bool   $remember Whether to request a persistent login cookie.
	 * @return string
	 */
	private function button_html( $redirect, $context_class, $include_separator = true, $remember = null ) {
		$remember_attribute = null === $remember ? '' : ' data-remember="' . ( $remember ? 'true' : 'false' ) . '"';
		$html               = sprintf(
			'<div class="atshift-freeform-login-passkey-auth %1$s" data-redirect="%2$s"%3$s><button type="button" class="button button-primary atshift-freeform-login-passkey-login-button">%4$s</button><p class="atshift-freeform-login-passkey-login-status" aria-live="polite"></p></div>',
			esc_attr( $context_class ),
			esc_url( $redirect ),
			$remember_attribute,
			esc_html__( 'Log in with a passkey', 'atshift-freeform-login' )
		);

		if ( $include_separator ) {
			$html .= '<div class="atshift-freeform-login-separator atshift-freeform-login-passkey-separator"><span>' . esc_html__( 'Or', 'atshift-freeform-login' ) . '</span></div>';
		}

		return $html;
	}
}
