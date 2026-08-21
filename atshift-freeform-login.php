<?php
/**
 * Plugin Name: atshift Freeform Login
 * Plugin URI: https://upf.at-shift.net/en/freeform-login/
 * Description: Design a beautiful WordPress login screen and place a matching login form anywhere with a shortcode.
 * Version: 2.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: @shift
 * Author URI: https://cfs.at-shift.net/
 * License: GPLv2 or later
 * Text Domain: atshift-freeform-login
 * Domain Path: /languages
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ATSHIFT_FREEFORM_LOGIN_VERSION', '2.1.0' );
define( 'ATSHIFT_FREEFORM_LOGIN_FILE', __FILE__ );
define( 'ATSHIFT_FREEFORM_LOGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ATSHIFT_FREEFORM_LOGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/passkeys/class-atshift-freeform-login-passkey-environment.php';
require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/passkeys/class-atshift-freeform-login-passkey-storage.php';
require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/passkeys/class-atshift-freeform-login-passkey-profile.php';
require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/class-atshift-freeform-login-settings.php';
require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/class-atshift-freeform-login-jetpack.php';
require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/class-atshift-freeform-login-screen.php';
require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/class-atshift-freeform-login-shortcode.php';
if ( Atshift_Freeform_Login_Passkey_Environment::load_dependencies() ) {
	require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/passkeys/class-atshift-freeform-login-passkey-challenges.php';
	require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/passkeys/class-atshift-freeform-login-passkey-rest.php';
	require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/passkeys/class-atshift-freeform-login-passkey-login.php';
	require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/passkeys/class-atshift-freeform-login-passkeys.php';
}
require_once ATSHIFT_FREEFORM_LOGIN_DIR . 'includes/class-atshift-freeform-login.php';

register_activation_hook( __FILE__, array( 'Atshift_Freeform_Login', 'activate' ) );

Atshift_Freeform_Login::instance();
