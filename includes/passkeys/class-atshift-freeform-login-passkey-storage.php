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
	const MAX_CREDENTIALS = 5;
	const REGISTRATION_LOCK_PREFIX = 'atshift_ffl_passkey_save_';

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

	/** @return int */
	public function get_max_credentials() {
		return self::MAX_CREDENTIALS;
	}

	/** @param int $user_id User ID. @return bool */
	public function has_registration_capacity( $user_id ) {
		return count( $this->get_credentials( $user_id ) ) < self::MAX_CREDENTIALS;
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
	 * @return array<string, mixed>|WP_Error
	 */
	public function save_credential( $user_id, $record, $normalized_record, $label ) {
		$lock_token = $this->acquire_registration_lock( $user_id );

		if ( false === $lock_token ) {
			return new WP_Error( 'atshift_passkey_registration_busy', __( 'Another passkey is being registered. Please try again.', 'atshift-freeform-login' ) );
		}

		try {
			$user = get_userdata( $user_id );

			if ( ! $user instanceof WP_User || is_wp_error( apply_filters( 'wp_authenticate_user', $user, '' ) ) ) {
				return new WP_Error(
					'atshift_passkey_account_unavailable',
					__( 'This account is not available.', 'atshift-freeform-login' )
				);
			}

			$credentials   = $this->get_credentials( $user_id );
			$previous      = $credentials;
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

			$existing_count = count( $credentials );
			$credentials    = array_values(
				array_filter(
					$credentials,
					static function ( $credential ) use ( $credential_id ) {
						return ! is_array( $credential ) || (string) ( $credential['credential_id'] ?? '' ) !== $credential_id;
					}
				)
			);

			if ( count( $credentials ) === $existing_count && self::MAX_CREDENTIALS <= $existing_count ) {
				return new WP_Error(
					'atshift_passkey_limit_reached',
					sprintf(
						/* translators: %d: maximum number of passkeys. */
						__( 'You can register up to %d passkeys.', 'atshift-freeform-login' ),
						self::MAX_CREDENTIALS
					)
				);
			}

			$credentials[] = $item;
			$updated       = update_user_meta( $user_id, self::META_KEY, $credentials );

			if ( false === $updated && get_user_meta( $user_id, self::META_KEY, true ) !== $credentials ) {
				return new WP_Error( 'atshift_passkey_storage_failed', __( 'The passkey could not be saved.', 'atshift-freeform-login' ) );
			}

			if ( ! $this->set_index_owner( $credential_id, $user_id ) ) {
				$this->restore_credentials( $user_id, $previous );

				return new WP_Error( 'atshift_passkey_storage_failed', __( 'The passkey could not be saved.', 'atshift-freeform-login' ) );
			}

			return $item;
		} finally {
			$this->release_registration_lock( $user_id, $lock_token );
		}
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

		if ( ! $this->remove_index_owner( $credential_id, $user_id ) ) {
			return false;
		}

		$updated = update_user_meta( $user_id, self::META_KEY, $remaining );

		if ( false === $updated && get_user_meta( $user_id, self::META_KEY, true ) !== $remaining ) {
			$this->set_index_owner( $credential_id, $user_id );

			return false;
		}

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

	/**
	 * Restore credential metadata after a failed index update.
	 *
	 * @param int                              $user_id     User ID.
	 * @param array<int, array<string, mixed>> $credentials Previous credentials.
	 * @return void
	 */
	private function restore_credentials( $user_id, $credentials ) {
		if ( empty( $credentials ) ) {
			delete_user_meta( $user_id, self::META_KEY );
			return;
		}

		update_user_meta( $user_id, self::META_KEY, array_values( $credentials ) );
	}

	/**
	 * Assign a credential index entry to a user.
	 *
	 * @param string $credential_id Credential ID.
	 * @param int    $user_id       User ID.
	 * @return bool
	 */
	private function set_index_owner( $credential_id, $user_id ) {
		$index_key = $this->index_key( $credential_id );

		return $this->mutate_index(
			static function ( $index ) use ( $index_key, $user_id ) {
				$index[ $index_key ] = (int) $user_id;

				return $index;
			}
		);
	}

	/**
	 * Remove a credential index entry when it still belongs to the user.
	 *
	 * @param string $credential_id Credential ID.
	 * @param int    $user_id       User ID.
	 * @return bool
	 */
	private function remove_index_owner( $credential_id, $user_id ) {
		$index_key = $this->index_key( $credential_id );

		return $this->mutate_index(
			static function ( $index ) use ( $index_key, $user_id ) {
				if ( isset( $index[ $index_key ] ) && (int) $index[ $index_key ] === (int) $user_id ) {
					unset( $index[ $index_key ] );
				}

				return $index;
			}
		);
	}

	/**
	 * Update the shared index without overwriting another concurrent mutation.
	 *
	 * @param callable(array<string, int>):array<string, int> $callback Index mutation.
	 * @return bool
	 */
	private function mutate_index( $callback ) {
		global $wpdb;

		for ( $attempt = 0; $attempt < 8; $attempt++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-swap requires the current raw value; successful writes invalidate option caches below.
			$raw   = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::INDEX_KEY ) );
			$index = null === $raw ? array() : maybe_unserialize( $raw );

			if ( ! is_array( $index ) ) {
				return false;
			}

			$next = call_user_func( $callback, $index );

			if ( $next === $index ) {
				return true;
			}

			if ( null === $raw ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic create prevents a concurrent writer from being overwritten.
				$changed = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::INDEX_KEY, maybe_serialize( $next ) ) );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The raw-value condition is the compare step of this bounded CAS loop.
				$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s", maybe_serialize( $next ), self::INDEX_KEY, $raw ) );
			}

			if ( false === $changed ) {
				return false;
			}

			if ( 1 === $changed ) {
				wp_cache_delete( self::INDEX_KEY, 'options' );
				wp_cache_delete( 'alloptions', 'options' );
				wp_cache_delete( 'notoptions', 'options' );

				return true;
			}
		}

		return false;
	}

	/**
	 * Erase all passkey storage owned by a user.
	 *
	 * This method does not require WebAuthn library objects, so account cleanup
	 * remains available when the runtime requirements are not met.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function erase_user_credentials( $user_id ) {
		$user_id = absint( $user_id );

		if ( 1 > $user_id ) {
			return false;
		}

		$lock_token = $this->acquire_registration_lock( $user_id );

		if ( false === $lock_token ) {
			return false;
		}

		try {
			$index_updated = $this->mutate_index(
				static function ( $index ) use ( $user_id ) {
					return array_filter(
						$index,
						static function ( $owner ) use ( $user_id ) {
							return (int) $owner !== $user_id;
						}
					);
				}
			);

			if ( ! $index_updated ) {
				return false;
			}

			delete_user_meta( $user_id, self::META_KEY );
			delete_user_meta( $user_id, self::USER_HANDLE_KEY );

			return ! metadata_exists( 'user', $user_id, self::META_KEY ) && ! metadata_exists( 'user', $user_id, self::USER_HANDLE_KEY );
		} finally {
			$this->release_registration_lock( $user_id, $lock_token );
		}
	}

	/** @param int $user_id User ID. @return string|false */
	private function acquire_registration_lock( $user_id ) {
		$key      = self::REGISTRATION_LOCK_PREFIX . absint( $user_id );
		$existing = $this->get_registration_lock( $key );

		if ( is_array( $existing ) && ! empty( $existing['expires'] ) && absint( $existing['expires'] ) <= time() ) {
			$this->delete_registration_lock( $key );
		}

		$token = wp_generate_uuid4();
		$value = array(
			'token'   => $token,
			'expires' => time() + 30,
		);

		return $this->add_registration_lock( $key, $value ) ? $token : false;
	}

	/** @param int $user_id User ID. @param string $token Lock owner token. @return void */
	private function release_registration_lock( $user_id, $token ) {
		$key   = self::REGISTRATION_LOCK_PREFIX . absint( $user_id );
		$value = $this->get_registration_lock( $key );

		if ( is_array( $value ) && isset( $value['token'] ) && hash_equals( (string) $value['token'], (string) $token ) ) {
			$this->delete_registration_lock( $key );
		}
	}

	/** @param string $key Lock key. @return mixed */
	private function get_registration_lock( $key ) {
		return is_multisite() ? get_site_option( $key, array() ) : get_option( $key, array() );
	}

	/** @param string $key Lock key. @param array<string,mixed> $value Lock value. @return bool */
	private function add_registration_lock( $key, $value ) {
		return is_multisite() ? add_site_option( $key, $value ) : add_option( $key, $value, '', false );
	}

	/** @param string $key Lock key. @return bool */
	private function delete_registration_lock( $key ) {
		return is_multisite() ? delete_site_option( $key ) : delete_option( $key );
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
