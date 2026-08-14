<?php
/**
 * Passkey runtime checks.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether the optional passkey module can run safely.
 */
class Atshift_Freeform_Login_Passkey_Environment {
	const MINIMUM_PHP = '8.3';

	/** @var bool|null */
	private static $dependencies_loaded = null;

	/**
	 * Determine whether the current server can load passkey dependencies.
	 *
	 * @return bool
	 */
	public static function can_load_dependencies() {
		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP, '<' ) ) {
			return false;
		}

		if ( ! extension_loaded( 'json' ) || ! extension_loaded( 'openssl' ) ) {
			return false;
		}

		return file_exists( self::autoload_path() );
	}

	/**
	 * Determine whether passkeys should be available to users.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return self::load_dependencies() && self::has_secure_origin();
	}

	/**
	 * Return the user-facing reason passkeys are unavailable.
	 *
	 * @return string
	 */
	public static function unavailable_message() {
		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP, '<' ) ) {
			return sprintf(
				/* translators: %s: required PHP version. */
				__( 'Passkeys require PHP %s or newer.', 'atshift-freeform-login' ),
				self::MINIMUM_PHP
			);
		}

		if ( ! extension_loaded( 'json' ) || ! extension_loaded( 'openssl' ) ) {
			return __( 'Passkeys require the PHP JSON and OpenSSL extensions.', 'atshift-freeform-login' );
		}

		if ( ! file_exists( self::autoload_path() ) ) {
			return __( 'Passkey dependencies are not installed.', 'atshift-freeform-login' );
		}

		if ( ! self::has_secure_origin() ) {
			return __( 'Passkeys require HTTPS, except on localhost during development.', 'atshift-freeform-login' );
		}

		return __( 'Passkeys are unavailable on this server.', 'atshift-freeform-login' );
	}

	/**
	 * Load Composer dependencies when the runtime gate allows it.
	 *
	 * @return bool
	 */
	public static function load_dependencies() {
		if ( null !== self::$dependencies_loaded ) {
			return self::$dependencies_loaded;
		}

		self::$dependencies_loaded = false;

		if ( ! self::can_load_dependencies() ) {
			return false;
		}

		try {
			require_once self::autoload_path();
		} catch ( Throwable $exception ) {
			unset( $exception );
			return false;
		}

		self::$dependencies_loaded = class_exists( 'Webauthn\PublicKeyCredentialCreationOptions' )
			&& class_exists( 'Webauthn\AuthenticatorAttestationResponseValidator' )
			&& class_exists( 'Webauthn\AuthenticatorAssertionResponseValidator' );

		return self::$dependencies_loaded;
	}

	/**
	 * Return the Composer autoloader location.
	 *
	 * @return string
	 */
	private static function autoload_path() {
		return ATSHIFT_FREEFORM_LOGIN_DIR . 'vendor/autoload.php';
	}

	/**
	 * Check whether the site URL is usable by WebAuthn.
	 *
	 * @return bool
	 */
	private static function has_secure_origin() {
		$scheme = wp_parse_url( home_url( '/' ), PHP_URL_SCHEME );
		$host   = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$host   = strtolower( (string) $host );

		return 'https' === strtolower( (string) $scheme )
			|| in_array( $host, array( 'localhost', '127.0.0.1', '::1', '[::1]' ), true );
	}
}
