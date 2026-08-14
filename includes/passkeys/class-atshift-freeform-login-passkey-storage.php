<?php
/**
 * Passkey credential storage.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores passkey credential records in user meta.
 */
class Atshift_Freeform_Login_Passkey_Storage {
	const META_KEY        = 'atshift_freeform_login_passkeys';
	const USER_HANDLE_KEY = 'atshift_freeform_login_passkey_user_handle';
	const INDEX_KEY       = 'atshift_freeform_login_passkey_index';

	/**
	 * Return stored credentials for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_credentials( $user_id ) {
		$credentials = get_user_meta( $user_id, self::META_KEY, true );

		return is_array( $credentials ) ? array_values( $credentials ) : array();
	}

	/**
	 * Return a stable opaque WebAuthn user handle.
	 *
	 * @param int $user_id User ID.
	 * @return string Binary user handle.
	 */
	public function get_user_handle( $user_id ) {
		$encoded = (string) get_user_meta( $user_id, self::USER_HANDLE_KEY, true );
		$handle  = '' !== $encoded ? $this->decode_base64url( $encoded ) : false;

		if ( is_string( $handle ) && 32 === strlen( $handle ) ) {
			return $handle;
		}

		$handle = random_bytes( 32 );
		update_user_meta( $user_id, self::USER_HANDLE_KEY, $this->encode_base64url( $handle ) );

		return $handle;
	}

	/**
	 * Return public credential descriptors for exclusion during registration.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, Webauthn\PublicKeyCredentialDescriptor>
	 */
	public function get_descriptors( $user_id ) {
		$descriptors = array();

		foreach ( $this->get_credentials( $user_id ) as $credential ) {
			if ( empty( $credential['credential_id'] ) ) {
				continue;
			}

			$raw_id     = $this->decode_base64url( (string) $credential['credential_id'] );
			$transports = isset( $credential['transports'] ) && is_array( $credential['transports'] ) ? $credential['transports'] : array();

			if ( false === $raw_id || '' === $raw_id ) {
				continue;
			}

			$descriptors[] = Webauthn\PublicKeyCredentialDescriptor::create(
				Webauthn\PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
				$raw_id,
				array_map( 'strval', $transports )
			);
		}

		return $descriptors;
	}

	/**
	 * Add or replace a credential record.
	 *
	 * @param int                         $user_id User ID.
	 * @param Webauthn\CredentialRecord   $record Credential record.
	 * @param array<string, mixed>        $normalized_record Normalized credential record.
	 * @param string                      $label User-facing label.
	 * @return array<string, mixed>
	 */
	public function save_credential( $user_id, $record, $normalized_record, $label ) {
		$credentials   = $this->get_credentials( $user_id );
		$credential_id = $this->encode_base64url( $record->publicKeyCredentialId );
		$now           = current_time( 'mysql', true );
		$label         = wp_html_excerpt( sanitize_text_field( $label ), 80, '' );

		if ( '' === $label ) {
			$label = __( 'Passkey', 'atshift-freeform-login' );
		}

		$item = array(
			'credential_id'   => $credential_id,
			'label'           => $label,
			'transports'      => array_values( array_map( 'strval', $record->transports ) ),
			'attestation_type' => $record->attestationType,
			'counter'         => (int) $record->counter,
			'backup_eligible' => null === $record->backupEligible ? null : (bool) $record->backupEligible,
			'backup_status'   => null === $record->backupStatus ? null : (bool) $record->backupStatus,
			'uv_initialized'  => null === $record->uvInitialized ? null : (bool) $record->uvInitialized,
			'record'          => $normalized_record,
			'created_at'      => $now,
			'last_used_at'    => '',
		);

		$credentials = array_values(
			array_filter(
				$credentials,
				static function ( $credential ) use ( $credential_id ) {
					return ! is_array( $credential ) || (string) ( $credential['credential_id'] ?? '' ) !== $credential_id;
				}
			)
		);
		$credentials[] = $item;

		update_user_meta( $user_id, self::META_KEY, $credentials );
		$this->set_index_owner( $credential_id, $user_id );

		return $item;
	}

	/**
	 * Delete a credential from a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $credential_id Base64url credential ID.
	 * @return bool
	 */
	public function delete_credential( $user_id, $credential_id ) {
		$credential_id = sanitize_text_field( $credential_id );
		$credentials   = $this->get_credentials( $user_id );
		$remaining     = array_values(
			array_filter(
				$credentials,
				static function ( $credential ) use ( $credential_id ) {
					return ! is_array( $credential ) || (string) ( $credential['credential_id'] ?? '' ) !== $credential_id;
				}
			)
		);

		if ( count( $remaining ) === count( $credentials ) ) {
			return false;
		}

		update_user_meta( $user_id, self::META_KEY, $remaining );
		$this->remove_index_owner( $credential_id, $user_id );

		return true;
	}

	/**
	 * Find a stored credential and its owner by credential ID.
	 *
	 * @param string $credential_id Base64url credential ID.
	 * @return array{user_id:int, credential:array<string, mixed>}|false
	 */
	public function find_credential( $credential_id ) {
		$credential_id = sanitize_text_field( $credential_id );
		$index         = $this->get_index();
		$index_key     = $this->index_key( $credential_id );
		$user_id       = isset( $index[ $index_key ] ) ? absint( $index[ $index_key ] ) : 0;

		if ( ! $user_id ) {
			return false;
		}

		foreach ( $this->get_credentials( $user_id ) as $credential ) {
			$stored_id = is_array( $credential ) ? (string) ( $credential['credential_id'] ?? '' ) : '';

			if ( '' !== $stored_id && hash_equals( $stored_id, $credential_id ) ) {
				return array(
					'user_id'    => $user_id,
					'credential' => $credential,
				);
			}
		}

		$this->remove_index_owner( $credential_id, $user_id );

		return false;
	}

	/**
	 * Persist the updated counter and backup state after authentication.
	 *
	 * @param int                       $user_id User ID.
	 * @param string                    $credential_id Base64url credential ID.
	 * @param Webauthn\CredentialRecord $record Updated record.
	 * @param array<string, mixed>      $normalized_record Normalized record.
	 * @return bool
	 */
	public function update_credential_record( $user_id, $credential_id, $record, $normalized_record ) {
		$credentials = $this->get_credentials( $user_id );
		$updated     = false;

		foreach ( $credentials as &$credential ) {
			if ( ! is_array( $credential ) || ! hash_equals( (string) ( $credential['credential_id'] ?? '' ), $credential_id ) ) {
				continue;
			}

			$credential['counter']         = (int) $record->counter;
			$credential['backup_eligible'] = null === $record->backupEligible ? null : (bool) $record->backupEligible;
			$credential['backup_status']   = null === $record->backupStatus ? null : (bool) $record->backupStatus;
			$credential['uv_initialized']  = null === $record->uvInitialized ? null : (bool) $record->uvInitialized;
			$credential['record']          = $normalized_record;
			$credential['last_used_at']    = current_time( 'mysql', true );
			$updated                       = true;
			break;
		}
		unset( $credential );

		if ( $updated ) {
			update_user_meta( $user_id, self::META_KEY, array_values( $credentials ) );
		}

		return $updated;
	}

	/**
	 * Whether at least one indexed passkey exists for this site.
	 *
	 * @return bool
	 */
	public function has_credentials() {
		return ! empty( $this->get_index() );
	}

	/** @return array<string, int> */
	private function get_index() {
		$index = get_option( self::INDEX_KEY, array() );

		return is_array( $index ) ? $index : array();
	}

	/** @param string $credential_id Credential ID. @return string */
	private function index_key( $credential_id ) {
		return hash( 'sha256', $credential_id );
	}

	/** @param string $credential_id Credential ID. @param int $user_id User ID. @return void */
	private function set_index_owner( $credential_id, $user_id ) {
		$index = $this->get_index();
		$index[ $this->index_key( $credential_id ) ] = (int) $user_id;
		update_option( self::INDEX_KEY, $index, false );
	}

	/** @param string $credential_id Credential ID. @param int $user_id User ID. @return void */
	private function remove_index_owner( $credential_id, $user_id ) {
		$index     = $this->get_index();
		$index_key = $this->index_key( $credential_id );

		if ( isset( $index[ $index_key ] ) && (int) $index[ $index_key ] === (int) $user_id ) {
			unset( $index[ $index_key ] );
			update_option( self::INDEX_KEY, $index, false );
		}
	}

	/**
	 * Encode binary data for JSON and user meta keys.
	 *
	 * @param string $value Binary value.
	 * @return string
	 */
	private function encode_base64url( $value ) {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode base64url data.
	 *
	 * @param string $value Encoded value.
	 * @return string|false
	 */
	private function decode_base64url( $value ) {
		$padding = strlen( $value ) % 4;

		if ( $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		return base64_decode( strtr( $value, '-_', '+/' ), true );
	}
}
