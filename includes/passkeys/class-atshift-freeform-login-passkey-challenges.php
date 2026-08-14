<?php
/**
 * Passkey challenge persistence.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores short-lived registration ceremonies.
 */
class Atshift_Freeform_Login_Passkey_Challenges {
	const TRANSIENT_PREFIX = 'atshift_ffl_passkey_reg_';
	const AUTH_TRANSIENT_PREFIX = 'atshift_ffl_passkey_auth_';
	const EXPIRATION       = 300;

	/**
	 * Store registration options for later verification.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $options Normalized creation options.
	 * @return string Request ID.
	 */
	public function store_registration( $user_id, $options ) {
		$request_id = wp_generate_uuid4();
		$key        = $this->registration_key( $user_id, $request_id );

		set_transient(
			$key,
			array(
				'user_id'    => (int) $user_id,
				'options'    => $options,
				'created_at' => time(),
			),
			self::EXPIRATION
		);

		return $request_id;
	}

	/**
	 * Consume registration options once.
	 *
	 * @param int    $user_id User ID.
	 * @param string $request_id Request ID.
	 * @return array<string, mixed>|false
	 */
	public function consume_registration( $user_id, $request_id ) {
		$request_id = sanitize_text_field( $request_id );
		$key        = $this->registration_key( $user_id, $request_id );
		$value      = get_transient( $key );

		delete_transient( $key );

		if ( ! is_array( $value ) || (int) ( $value['user_id'] ?? 0 ) !== (int) $user_id || empty( $value['options'] ) || ! is_array( $value['options'] ) ) {
			return false;
		}

		return $value['options'];
	}

	/**
	 * Build the transient key.
	 *
	 * @param int    $user_id User ID.
	 * @param string $request_id Request ID.
	 * @return string
	 */
	private function registration_key( $user_id, $request_id ) {
		return self::TRANSIENT_PREFIX . (int) $user_id . '_' . hash( 'sha256', $request_id );
	}

	/**
	 * Store authentication options and the requested login behavior.
	 *
	 * @param array<string, mixed> $options Normalized request options.
	 * @param string               $redirect Validated redirect URL.
	 * @param bool                 $remember Whether to issue a persistent cookie.
	 * @return string Request ID.
	 */
	public function store_authentication( $options, $redirect, $remember ) {
		$request_id = wp_generate_uuid4();

		set_transient(
			$this->authentication_key( $request_id ),
			array(
				'options'  => $options,
				'redirect' => $redirect,
				'remember' => (bool) $remember,
			),
			self::EXPIRATION
		);

		return $request_id;
	}

	/**
	 * Consume authentication options once.
	 *
	 * @param string $request_id Request ID.
	 * @return array<string, mixed>|false
	 */
	public function consume_authentication( $request_id ) {
		$key   = $this->authentication_key( sanitize_text_field( $request_id ) );
		$value = get_transient( $key );

		delete_transient( $key );

		if ( ! is_array( $value ) || empty( $value['options'] ) || ! is_array( $value['options'] ) ) {
			return false;
		}

		return $value;
	}

	/** @param string $request_id Request ID. @return string */
	private function authentication_key( $request_id ) {
		return self::AUTH_TRANSIENT_PREFIX . hash( 'sha256', $request_id );
	}
}
