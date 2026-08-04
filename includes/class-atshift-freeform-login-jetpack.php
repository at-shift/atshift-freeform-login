<?php
/**
 * Jetpack SSO compatibility.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps Jetpack's authentication flow intact while adapting its UI.
 */
class Atshift_Freeform_Login_Jetpack {
	/** Register hooks. */
	public function __construct() {
		add_filter( 'login_body_class', array( $this, 'login_body_classes' ), 20 );
	}

	/**
	 * Determine whether Jetpack's SSO module is active.
	 *
	 * @return bool
	 */
	public static function is_sso_active() {
		$active = class_exists( 'Jetpack' )
			&& method_exists( 'Jetpack', 'is_module_active' )
			&& Jetpack::is_module_active( 'sso' );

		return (bool) apply_filters( 'atshift_freeform_login_jetpack_sso_active', $active );
	}

	/**
	 * Determine whether Jetpack disables the local username/password method.
	 *
	 * @return bool
	 */
	public static function hides_local_login() {
		if ( ! self::is_sso_active() ) {
			return false;
		}

		return (bool) apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is Jetpack's documented compatibility filter.
			'jetpack_remove_login_form',
			get_option( 'jetpack_sso_remove_login_form', false )
		);
	}

	/**
	 * Determine whether Jetpack forwards the local login screen to WordPress.com.
	 *
	 * @return bool
	 */
	public static function forwards_to_wordpress_com() {
		if ( ! self::is_sso_active() ) {
			return false;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is Jetpack's documented compatibility filter.
		return (bool) apply_filters( 'jetpack_sso_bypass_login_forward_wpcom', false );
	}

	/**
	 * Add state classes without changing Jetpack's own form behavior.
	 *
	 * @param array<int, string> $classes Login body classes.
	 * @return array<int, string>
	 */
	public function login_body_classes( $classes ) {
		$settings = Atshift_Freeform_Login_Settings::get_settings();

		if ( empty( $settings['enabled'] ) || ! self::is_sso_active() ) {
			return $classes;
		}

		$classes[] = 'atshift-freeform-login-jetpack-sso';

		if ( self::hides_local_login() ) {
			$classes[] = 'atshift-freeform-login-jetpack-sso-only';
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Build Jetpack's official SSO button for shortcode output.
	 *
	 * @param string $redirect Safe post-login redirect URL.
	 * @return string
	 */
	public static function shortcode_button( $redirect ) {
		if ( ! self::is_sso_active() ) {
			return '';
		}

		$html = (string) apply_filters( 'atshift_freeform_login_jetpack_sso_button_html', '', $redirect );

		if ( '' === $html ) {
			$class = 'Automattic\\Jetpack\\Connection\\SSO';

			if ( class_exists( $class ) && method_exists( $class, 'get_instance' ) ) {
				$instance = $class::get_instance();

				if ( is_object( $instance ) && method_exists( $instance, 'build_sso_button' ) ) {
					$html = (string) $instance->build_sso_button(
						array( 'redirect_to' => $redirect ),
						true
					);
				}
			}
		}

		if ( '' === $html ) {
			$html = sprintf(
				'<a rel="nofollow" href="%1$s" class="jetpack-sso button button-primary">%2$s</a>',
				esc_url( wp_login_url( $redirect ) ),
				esc_html__( 'Log in with WordPress.com', 'atshift-freeform-login' )
			);
		}

		return wp_kses(
			$html,
			array(
				'a'    => array(
					'class' => true,
					'href'  => true,
					'rel'   => true,
				),
				'span' => array( 'class' => true ),
			)
		);
	}

	/**
	 * Render the current Jetpack interaction on the plugin settings screen.
	 *
	 * @return void
	 */
	public static function render_admin_status() {
		if ( ! self::is_sso_active() ) {
			return;
		}

		if ( self::forwards_to_wordpress_com() ) {
			$message = __( 'Jetpack SSO currently redirects the login screen directly to WordPress.com. This login screen design is not shown during that redirect.', 'atshift-freeform-login' );
			$class   = 'notice-warning';
		} elseif ( self::hides_local_login() ) {
			$message = __( 'Jetpack SSO is set to use WordPress.com login only. The local username and password fields are hidden, and the WordPress.com login area uses this design.', 'atshift-freeform-login' );
			$class   = 'notice-info';
		} else {
			$message = __( 'Jetpack SSO was detected. The local login and WordPress.com login areas both use this design.', 'atshift-freeform-login' );
			$class   = 'notice-info';
		}

		printf(
			'<div class="notice %1$s inline atshift-freeform-login-jetpack-status"><p><strong>%2$s</strong> %3$s</p></div>',
			esc_attr( $class ),
			esc_html__( 'Jetpack SSO:', 'atshift-freeform-login' ),
			esc_html( $message )
		);
	}
}
