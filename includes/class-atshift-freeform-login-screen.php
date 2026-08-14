<?php
/**
 * WordPress login screen integration.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies saved design settings without replacing WordPress authentication.
 */
class Atshift_Freeform_Login_Screen {
	/** Register hooks. */
	public function __construct() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'login_headerurl', array( $this, 'header_url' ) );
		add_filter( 'login_headertext', array( $this, 'header_text' ) );
		add_filter( 'login_message', array( $this, 'intro_message' ) );
	}

	/**
	 * Enqueue static and generated styles.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$settings = Atshift_Freeform_Login_Settings::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		wp_enqueue_style(
			'atshift-freeform-login-screen',
			ATSHIFT_FREEFORM_LOGIN_URL . 'assets/login.css',
			array( 'login' ),
			ATSHIFT_FREEFORM_LOGIN_VERSION
		);
		wp_add_inline_style( 'atshift-freeform-login-screen', $this->build_css( $settings ) );
		wp_enqueue_script(
			'atshift-freeform-login-screen',
			ATSHIFT_FREEFORM_LOGIN_URL . 'assets/login.js',
			array(),
			ATSHIFT_FREEFORM_LOGIN_VERSION,
			true
		);
		wp_localize_script(
			'atshift-freeform-login-screen',
			'atshiftFreeformLoginScreen',
			array(
				'usernamePlaceholder' => __( 'Username / Email', 'atshift-freeform-login' ),
				'passwordPlaceholder' => __( 'Password', 'atshift-freeform-login' ),
				'showFieldLabels'      => ! empty( $settings['show_field_labels'] ),
			)
		);
	}

	/**
	 * Replace the login logo URL only while customization is enabled.
	 *
	 * @param string $url Existing login logo URL.
	 * @return string
	 */
	public function header_url( $url ) {
		$settings = Atshift_Freeform_Login_Settings::get_settings();

		return empty( $settings['enabled'] ) ? $url : home_url( '/' );
	}

	/**
	 * Replace the login logo text only while customization is enabled.
	 *
	 * @param string $text Existing login logo text.
	 * @return string
	 */
	public function header_text( $text ) {
		$settings = Atshift_Freeform_Login_Settings::get_settings();

		return empty( $settings['enabled'] ) ? $text : get_bloginfo( 'name' );
	}

	/**
	 * Insert optional plain text between the brand and login form.
	 *
	 * @param string $message Existing WordPress login message.
	 * @return string
	 */
	public function intro_message( $message ) {
		$settings = Atshift_Freeform_Login_Settings::get_settings();
		$text     = isset( $settings['intro_text'] ) ? trim( (string) $settings['intro_text'] ) : '';

		if ( empty( $settings['enabled'] ) || '' === $text ) {
			return $message;
		}

		return $message . '<div class="atshift-freeform-login-intro">' . nl2br( esc_html( $text ) ) . '</div>';
	}

	/**
	 * Build CSS only from normalized settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	private function build_css( $settings ) {
		$position      = $this->position_values( $settings['form_position'] );
		$image         = 'color' !== $settings['background_media_type'] && '' !== $settings['background_image_url'] ? 'url("' . esc_url_raw( $settings['background_image_url'] ) . '")' : 'none';
		$shadow        = self::box_shadow( $settings );
		$logo_css      = $this->logo_css( $settings );
		$link_states   = apply_filters( 'atshift_freeform_login_link_states', self::interactive_color_states( $settings['link_color'] ), $settings );
		$button_states = apply_filters( 'atshift_freeform_login_button_states', self::interactive_color_states( $settings['button_background_color'] ), $settings );
		$form_text     = self::contrast_text_color( $settings['form_background_color'] );
		$intro_alignment = isset( $settings['intro_alignment'] ) ? $settings['intro_alignment'] : 'center';
		$intro_margins   = array(
			'left'   => '0 auto 18px 0',
			'center' => '0 auto 18px',
			'right'  => '0 0 18px auto',
		);
		$intro_alignment = isset( $intro_margins[ $intro_alignment ] ) ? $intro_alignment : 'center';

		$css = sprintf(
			'body.login{background-color:%1$s;background-image:%2$s;background-position:%3$s;background-size:%4$s;background-repeat:no-repeat;justify-content:%5$s;align-items:%6$s;color:%7$s}body.login #login{width:min(%8$dpx,calc(100vw - 48px))}body.login .atshift-freeform-login-intro{width:%21$d%%;max-width:100%%;margin:%23$s;color:%7$s;line-height:1.65;text-align:%24$s}body.login #loginform,body.login #lostpasswordform,body.login #registerform{background:%9$s;border:0;border-radius:8px;box-shadow:%10$s}body.login #loginform .atshift-freeform-login-passkey-separator span{background:%9$s}body.login #login_error,body.login .message,body.login .success{min-height:0;margin:0 0 18px;padding:16px 18px;color:#1d2327;background:rgba(255,255,255,.88);border:0;border-left:4px solid %15$s;border-radius:6px;box-shadow:none;line-height:1.6}body.login #login_error{border-left-color:#d63638}body.login label,body.login .forgetmenot label,body.login .language-switcher{color:%7$s}body.login .language-switcher select{font-size:14px;color:%22$s;background:%9$s;border-color:%11$s}body.login #nav a,body.login #backtoblog a,body.login .privacy-policy-page-link a,body.login #login_error a,body.login .message a,body.login .success a,body.login #jetpack-sso-wrap a:not(.button){color:%11$s!important;text-decoration-thickness:2px;text-underline-offset:3px}body.login #nav a:hover,body.login #backtoblog a:hover,body.login .privacy-policy-page-link a:hover,body.login #login_error a:hover,body.login .message a:hover,body.login .success a:hover,body.login #jetpack-sso-wrap a:not(.button):hover{color:%12$s!important;text-decoration-thickness:3px}body.login #nav a:active,body.login #backtoblog a:active,body.login .privacy-policy-page-link a:active,body.login #login_error a:active,body.login .message a:active,body.login .success a:active,body.login #jetpack-sso-wrap a:not(.button):active{color:%13$s!important}body.login #nav a:focus-visible,body.login #backtoblog a:focus-visible,body.login .privacy-policy-page-link a:focus-visible,body.login #login_error a:focus-visible,body.login .message a:focus-visible,body.login .success a:focus-visible,body.login #jetpack-sso-wrap a:not(.button):focus-visible{outline:2px solid %14$s;outline-offset:2px}body.login .button-primary{background:%15$s;border-color:%15$s;color:%16$s}body.login .button-primary:hover{background:%17$s;border-color:%17$s;color:%16$s}body.login .button-primary:active{background:%18$s;border-color:%18$s;color:%16$s}body.login .button-primary:focus-visible{background:%17$s;border-color:%17$s;color:%16$s;box-shadow:none;outline:2px solid %19$s;outline-offset:2px}body.login .language-switcher .button{background:%15$s!important;border-color:%15$s!important;color:%16$s!important}body.login .language-switcher .button:hover{background:%17$s!important;border-color:%17$s!important;color:%16$s!important}body.login .language-switcher .button:active{background:%18$s!important;border-color:%18$s!important;color:%16$s!important}body.login .language-switcher .button:focus-visible{background:%17$s!important;border-color:%17$s!important;color:%16$s!important;box-shadow:none;outline:2px solid %19$s;outline-offset:2px}body.login.atshift-freeform-login-jetpack-sso #jetpack-sso-wrap,body.login.atshift-freeform-login-jetpack-sso #jetpack-sso-wrap p,body.login.atshift-freeform-login-jetpack-sso #jetpack-sso-wrap__action>p,body.login.atshift-freeform-login-jetpack-sso #jetpack-sso-wrap__user h2,body.login.atshift-freeform-login-jetpack-sso #jetpack-sso-wrap__user p,body.login.atshift-freeform-login-jetpack-sso .jetpack-sso-or{color:%7$s!important}body.login.atshift-freeform-login-jetpack-sso .jetpack-sso-or span{background:%9$s;color:%22$s!important}%20$s',
			esc_attr( $settings['background_color'] ),
			$image,
			esc_attr( $settings['background_position'] ),
			esc_attr( $settings['background_size'] ),
			$position['justify'],
			$position['align'],
			esc_attr( $settings['label_color'] ),
			(int) $settings['form_width'],
			esc_attr( $settings['form_background_color'] ),
			$shadow,
			esc_attr( $settings['link_color'] ),
			esc_attr( $link_states['hover'] ),
			esc_attr( $link_states['active'] ),
			esc_attr( $link_states['focus'] ),
			esc_attr( $settings['button_background_color'] ),
			esc_attr( $settings['button_text_color'] ),
			esc_attr( $button_states['hover'] ),
			esc_attr( $button_states['active'] ),
			esc_attr( $button_states['focus'] ),
			$logo_css,
			(int) $settings['intro_width'],
			esc_attr( $form_text ),
			esc_attr( $intro_margins[ $intro_alignment ] ),
			esc_attr( $intro_alignment )
		);

		return apply_filters( 'atshift_freeform_login_screen_css', $css, $settings );
	}

	/**
	 * Build a sanitized box-shadow value shared by every login form surface.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	public static function box_shadow( $settings ) {
		if ( empty( $settings['form_shadow'] ) ) {
			return 'none';
		}

		return apply_filters( 'atshift_freeform_login_box_shadow', '0 18px 50px 0 rgba(0,0,0,0.18)', $settings );
	}

	/**
	 * Build logo CSS.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	private function logo_css( $settings ) {
		if ( 'none' === $settings['logo_mode'] ) {
			return 'body.login #login h1{display:none}';
		}

		if ( 'banner' === $settings['logo_mode'] && '' !== $settings['banner_logo_image_url'] ) {
			$css = sprintf(
				'body.login #login h1 a{display:block;width:100%%;max-width:100%%;height:auto;aspect-ratio:4/1;overflow:hidden;background-image:url("%1$s");background-size:cover;background-position:center;background-repeat:no-repeat;text-indent:-9999px}',
				esc_url_raw( $settings['banner_logo_image_url'] )
			);

			return apply_filters( 'atshift_freeform_login_logo_css', $css, $settings );
		}

		$css = sprintf(
			'body.login #login h1 a{background:none;width:auto;height:auto;text-indent:0;font-size:28px;font-weight:700;color:%1$s;line-height:1.25}',
			esc_attr( $settings['brand_text_color'] )
		);

		return apply_filters( 'atshift_freeform_login_logo_css', $css, $settings );
	}

	/**
	 * Map a named position to flex alignment.
	 *
	 * @param string $position Position key.
	 * @return array<string, string>
	 */
	private function position_values( $position ) {
		$parts = explode( '-', $position );
		$x     = isset( $parts[0] ) ? $parts[0] : 'center';
		$y     = isset( $parts[1] ) ? $parts[1] : 'center';
		$x_map = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
		$y_map = array( 'top' => 'flex-start', 'center' => 'center', 'bottom' => 'flex-end' );

		return array(
			'justify' => isset( $x_map[ $x ] ) ? $x_map[ $x ] : 'center',
			'align'   => isset( $y_map[ $y ] ) ? $y_map[ $y ] : 'center',
		);
	}

	/**
	 * Convert a six-digit hex color to rgba().
	 *
	 * @param string $hex Hex color.
	 * @param float  $alpha Alpha value.
	 * @return string
	 */
	public static function hex_to_rgba( $hex, $alpha ) {
		$hex   = ltrim( (string) $hex, '#' );
		$alpha = max( 0, min( 1, (float) $alpha ) );

		if ( 6 !== strlen( $hex ) ) {
			$hex = 'ffffff';
		}

		return sprintf(
			'rgba(%d,%d,%d,%.2F)',
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
			$alpha
		);
	}

	/**
	 * Create visible interaction states from one saved color.
	 *
	 * Very light colors become darker and very dark colors become lighter so
	 * white and black still provide visible hover and pressed feedback.
	 *
	 * @param string $hex Six-digit hex color.
	 * @return array<string, string>
	 */
	public static function interactive_color_states( $hex ) {
		$hex = sanitize_hex_color( $hex );

		if ( ! $hex ) {
			$hex = '#2271b1';
		}

		if ( 4 === strlen( $hex ) ) {
			$hex = sprintf( '#%1$s%1$s%2$s%2$s%3$s%3$s', $hex[1], $hex[2], $hex[3] );
		}

		$rgb       = self::hex_to_rgb( $hex );
		$luminance = ( 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2] ) / 255;

		if ( $luminance >= 0.72 ) {
			$hover  = self::mix_hex_color( $hex, '#000000', 0.15 );
			$active = self::mix_hex_color( $hex, '#000000', 0.28 );
			$focus  = self::mix_hex_color( $hex, '#000000', 0.55 );
		} elseif ( $luminance <= 0.18 ) {
			$hover  = self::mix_hex_color( $hex, '#ffffff', 0.18 );
			$active = self::mix_hex_color( $hex, '#ffffff', 0.32 );
			$focus  = self::mix_hex_color( $hex, '#ffffff', 0.50 );
		} elseif ( $luminance >= 0.48 ) {
			$hover  = self::mix_hex_color( $hex, '#000000', 0.18 );
			$active = self::mix_hex_color( $hex, '#000000', 0.30 );
			$focus  = self::mix_hex_color( $hex, '#000000', 0.42 );
		} else {
			$hover  = self::mix_hex_color( $hex, '#ffffff', 0.24 );
			$active = self::mix_hex_color( $hex, '#000000', 0.22 );
			$focus  = $hex;
		}

		return array(
			'hover'  => $hover,
			'active' => $active,
			'focus'  => $focus,
		);
	}

	/**
	 * Choose readable text for a solid UI surface.
	 *
	 * @param string $hex Surface color.
	 * @return string
	 */
	public static function contrast_text_color( $hex ) {
		$rgb       = self::hex_to_rgb( $hex );
		$luminance = ( 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2] ) / 255;

		return $luminance > 0.55 ? '#1d2327' : '#ffffff';
	}

	/**
	 * Mix two six-digit hex colors.
	 *
	 * @param string $source Source color.
	 * @param string $target Target color.
	 * @param float  $weight Target color weight from 0 to 1.
	 * @return string
	 */
	private static function mix_hex_color( $source, $target, $weight ) {
		$source = self::hex_to_rgb( $source );
		$target = self::hex_to_rgb( $target );
		$weight = max( 0, min( 1, (float) $weight ) );
		$mixed  = array();

		for ( $index = 0; $index < 3; $index++ ) {
			$mixed[] = (int) round( $source[ $index ] + ( $target[ $index ] - $source[ $index ] ) * $weight );
		}

		return sprintf( '#%02x%02x%02x', $mixed[0], $mixed[1], $mixed[2] );
	}

	/**
	 * Convert a six-digit hex color to RGB channels.
	 *
	 * @param string $hex Hex color.
	 * @return array<int, int>
	 */
	private static function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );

		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}
}
