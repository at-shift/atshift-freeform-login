<?php
/**
 * Frontend login shortcode.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a WordPress-authenticated login form.
 */
class Atshift_Freeform_Login_Shortcode {
	/** Register hooks. */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
	}

	/** @return void */
	public function register() {
		add_shortcode( 'atshift_login', array( $this, 'render' ) );
	}

	/** @return void */
	public function maybe_enqueue_assets() {
		global $post;

		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'atshift_login' ) ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string, mixed> $attributes Shortcode attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'redirect'           => '',
				'show_lost_password' => 'true',
				'remember'           => 'true',
				'jetpack'            => 'auto',
				'class'              => '',
			),
			is_array( $attributes ) ? $attributes : array(),
			'atshift_login'
		);

		$this->enqueue_assets();

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();

			return sprintf(
				'<div class="atshift-freeform-login-logged-in">%1$s <a href="%2$s">%3$s</a></div>',
				/* translators: %s: Display name of the currently logged-in user. */
				esc_html( sprintf( __( 'You are logged in as %s.', 'atshift-freeform-login' ), $user->display_name ) ),
				esc_url( wp_logout_url( self::safe_redirect( $attributes['redirect'] ) ) ),
				esc_html__( 'Log out', 'atshift-freeform-login' )
			);
		}

		$settings      = Atshift_Freeform_Login_Settings::get_settings();
		$link_states   = apply_filters( 'atshift_freeform_login_link_states', Atshift_Freeform_Login_Screen::interactive_color_states( $settings['link_color'] ), $settings );
		$button_states = apply_filters( 'atshift_freeform_login_button_states', Atshift_Freeform_Login_Screen::interactive_color_states( $settings['button_background_color'] ), $settings );
		$redirect      = self::safe_redirect( $attributes['redirect'] );
		$show_lost     = self::to_bool( $attributes['show_lost_password'] );
		$remember      = self::to_bool( $attributes['remember'] );
		$custom_class  = self::class_names( $attributes['class'] );
		$jetpack_mode  = self::jetpack_mode( $attributes['jetpack'] );
		$requires_sso  = Atshift_Freeform_Login_Jetpack::hides_local_login() || Atshift_Freeform_Login_Jetpack::forwards_to_wordpress_com();
		$jetpack_html  = 'hide' === $jetpack_mode && ! $requires_sso ? '' : Atshift_Freeform_Login_Jetpack::shortcode_button( $redirect );
		$sso_only      = '' !== $jetpack_html
			&& $requires_sso;
		$form          = $sso_only
			? ''
			: wp_login_form(
				array(
					'echo'           => false,
					'redirect'       => $redirect,
					'remember'       => $remember,
					'value_remember' => false,
				)
			);

		if ( ! is_string( $form ) || ( '' === $form && '' === $jetpack_html ) ) {
			return '';
		}

		$style = sprintf(
			'--atshift-login-width:%1$dpx;--atshift-login-bg:%2$s;--atshift-login-radius:8px;--atshift-login-label:%3$s;--atshift-login-link:%4$s;--atshift-login-link-hover:%5$s;--atshift-login-link-active:%6$s;--atshift-login-link-focus:%7$s;--atshift-login-button:%8$s;--atshift-login-button-hover:%9$s;--atshift-login-button-active:%10$s;--atshift-login-button-focus:%11$s;--atshift-login-button-text:%12$s;--atshift-login-shadow:%13$s;--atshift-login-border-width:0;--atshift-login-border-color:transparent;',
			(int) $settings['form_width'],
			esc_attr( $settings['form_background_color'] ),
			esc_attr( $settings['label_color'] ),
			esc_attr( $settings['link_color'] ),
			esc_attr( $link_states['hover'] ),
			esc_attr( $link_states['active'] ),
			esc_attr( $link_states['focus'] ),
			esc_attr( $settings['button_background_color'] ),
			esc_attr( $button_states['hover'] ),
			esc_attr( $button_states['active'] ),
			esc_attr( $button_states['focus'] ),
			esc_attr( $settings['button_text_color'] ),
			esc_attr( Atshift_Freeform_Login_Screen::box_shadow( $settings ) )
		);
		$style = apply_filters( 'atshift_freeform_login_shortcode_style', $style, $settings );

		$lost_password = $show_lost && ! $sso_only
			? '<p class="atshift-freeform-login-lost"><a href="' . esc_url( wp_lostpassword_url( $redirect ) ) . '">' . esc_html__( 'Lost your password?', 'atshift-freeform-login' ) . '</a></p>'
			: '';
		$jetpack = '';
		$passkey = (string) apply_filters( 'atshift_freeform_login_shortcode_passkey_html', '', $redirect, $sso_only );

		if ( '' !== $jetpack_html ) {
			$jetpack = '<div class="atshift-freeform-login-jetpack">' . $jetpack_html . '</div>';

			if ( ! $sso_only ) {
				$jetpack .= '<div class="atshift-freeform-login-separator"><span>' . esc_html__( 'Or', 'atshift-freeform-login' ) . '</span></div>';
			}
		}

		return '<div class="atshift-freeform-login ' . esc_attr( $custom_class ) . '" style="' . esc_attr( $style ) . '">' . $jetpack . $passkey . $form . $lost_password . '</div>';
	}

	/** @return void */
	private function enqueue_assets() {
		$settings = Atshift_Freeform_Login_Settings::get_settings();

		wp_enqueue_style(
			'atshift-freeform-login-frontend',
			ATSHIFT_FREEFORM_LOGIN_URL . 'assets/frontend.css',
			array(),
			ATSHIFT_FREEFORM_LOGIN_VERSION
		);
		wp_enqueue_script(
			'atshift-freeform-login-frontend',
			ATSHIFT_FREEFORM_LOGIN_URL . 'assets/frontend.js',
			array(),
			ATSHIFT_FREEFORM_LOGIN_VERSION,
			true
		);
		wp_localize_script(
			'atshift-freeform-login-frontend',
			'atshiftFreeformLoginFrontend',
			array(
				'usernamePlaceholder' => __( 'Username / Email', 'atshift-freeform-login' ),
				'passwordPlaceholder' => __( 'Password', 'atshift-freeform-login' ),
				'showFieldLabels'      => ! empty( $settings['show_field_labels'] ),
			)
		);
	}

	/**
	 * Restrict redirects to the current site.
	 *
	 * @param mixed $redirect Requested redirect.
	 * @return string
	 */
	private static function safe_redirect( $redirect ) {
		$fallback = home_url( '/' );
		$redirect = is_string( $redirect ) ? trim( $redirect ) : '';

		if ( '' === $redirect ) {
			return $fallback;
		}

		if ( 0 === strpos( $redirect, '/' ) && 0 !== strpos( $redirect, '//' ) ) {
			$redirect = home_url( $redirect );
		}

		return wp_validate_redirect( $redirect, $fallback );
	}

	/** @return bool */
	private static function to_bool( $value ) {
		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Normalize the optional Jetpack integration mode.
	 *
	 * @param mixed $mode Raw shortcode value.
	 * @return string
	 */
	private static function jetpack_mode( $mode ) {
		$mode = strtolower( trim( (string) $mode ) );

		return in_array( $mode, array( 'auto', 'hide' ), true ) ? $mode : 'auto';
	}

	/**
	 * Sanitize a space-separated class list.
	 *
	 * @param mixed $classes Raw classes.
	 * @return string
	 */
	private static function class_names( $classes ) {
		$classes = preg_split( '/\s+/', (string) $classes );
		$classes = array_filter( array_map( 'sanitize_html_class', is_array( $classes ) ? $classes : array() ) );

		return implode( ' ', $classes );
	}
}
