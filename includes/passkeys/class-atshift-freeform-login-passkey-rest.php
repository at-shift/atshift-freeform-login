<?php
/**
 * Passkey REST endpoints.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles passkey registration requests.
 */
class Atshift_Freeform_Login_Passkey_REST {
	const NAMESPACE = 'atshift-freeform-login/v1';

	/** @var Atshift_Freeform_Login_Passkey_Storage */
	private $storage;

	/** @var Atshift_Freeform_Login_Passkey_Challenges */
	private $challenges;

	/**
	 * Constructor.
	 *
	 * @param Atshift_Freeform_Login_Passkey_Storage    $storage Storage service.
	 * @param Atshift_Freeform_Login_Passkey_Challenges $challenges Challenge service.
	 */
	public function __construct( $storage, $challenges ) {
		$this->storage    = $storage;
		$this->challenges = $challenges;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/passkeys/registration/options',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'registration_options' ),
				'permission_callback' => array( $this, 'can_register_for_self' ),
				'args'                => array(
					'userId' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/passkeys/registration/verify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'registration_verify' ),
				'permission_callback' => array( $this, 'can_register_for_self' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/passkeys/(?P<credentialId>[A-Za-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_credential' ),
				'permission_callback' => array( $this, 'can_delete_credential' ),
				'args'                => array(
					'credentialId' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'userId'       => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/passkeys/authentication/options',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'authentication_options' ),
				'permission_callback' => '__return_true',
			),
		);
		register_rest_route(
			self::NAMESPACE,
			'/passkeys/authentication/verify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'authentication_verify' ),
				'permission_callback' => '__return_true',
			),
		);
	}

	/**
	 * Check whether the current user can register a passkey for themselves.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_register_for_self( $request ) {
		if ( ! Atshift_Freeform_Login_Passkey_Environment::is_available() ) {
			return new WP_Error( 'atshift_passkey_unavailable', Atshift_Freeform_Login_Passkey_Environment::unavailable_message(), array( 'status' => 400 ) );
		}

		$user_id = absint( $request->get_param( 'userId' ) );

		if ( ! is_user_logged_in() || get_current_user_id() !== $user_id ) {
			return new WP_Error( 'atshift_passkey_forbidden', __( 'You can only register passkeys for your own account.', 'atshift-freeform-login' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check whether the current user can delete a credential.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_delete_credential( $request ) {
		$user_id = absint( $request->get_param( 'userId' ) );

		if ( ! is_user_logged_in() || ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_user', $user_id ) ) ) {
			return new WP_Error( 'atshift_passkey_forbidden', __( 'You cannot delete this passkey.', 'atshift-freeform-login' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Return PublicKeyCredentialCreationOptions for the current user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function registration_options( $request ) {
		$user_id = absint( $request->get_param( 'userId' ) );
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'atshift_passkey_user_missing', __( 'User not found.', 'atshift-freeform-login' ), array( 'status' => 404 ) );
		}

		try {
			$serializer = $this->serializer();
			$options    = Webauthn\PublicKeyCredentialCreationOptions::create(
				Webauthn\PublicKeyCredentialRpEntity::create( get_bloginfo( 'name' ), $this->rp_id() ),
				Webauthn\PublicKeyCredentialUserEntity::create(
					$user->user_login,
					$this->storage->get_user_handle( $user_id ),
					$user->display_name
				),
				random_bytes( 32 ),
				array(
					Webauthn\PublicKeyCredentialParameters::createPk( -7 ),
					Webauthn\PublicKeyCredentialParameters::createPk( -257 ),
				),
				Webauthn\AuthenticatorSelectionCriteria::create(
					null,
					Webauthn\AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
					Webauthn\AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
				),
				Webauthn\PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
				$this->storage->get_descriptors( $user_id ),
				60000
			);
			$normalized = $serializer->normalize( $options, 'json' );
			$request_id = $this->challenges->store_registration( $user_id, $normalized );
		} catch ( Throwable $exception ) {
			do_action( 'atshift_freeform_login_passkey_error', $exception, 'registration_options' );

			return new WP_Error( 'atshift_passkey_options_failed', __( 'Passkey registration could not be started.', 'atshift-freeform-login' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'requestId' => $request_id,
				'publicKey' => $normalized,
			)
		);
	}

	/**
	 * Verify a registration response and store the credential.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function registration_verify( $request ) {
		$params     = $request->get_json_params();
		$params     = is_array( $params ) ? $params : array();
		$user_id    = absint( $params['userId'] ?? 0 );
		$request_id = sanitize_text_field( (string) ( $params['requestId'] ?? '' ) );
		$label      = sanitize_text_field( (string) ( $params['label'] ?? '' ) );
		$credential = $params['credential'] ?? null;

		if ( get_current_user_id() !== $user_id || ! is_array( $credential ) || '' === $request_id ) {
			return new WP_Error( 'atshift_passkey_bad_request', __( 'Invalid passkey request.', 'atshift-freeform-login' ), array( 'status' => 400 ) );
		}

		$stored_options = $this->challenges->consume_registration( $user_id, $request_id );

		if ( false === $stored_options ) {
			return new WP_Error( 'atshift_passkey_challenge_expired', __( 'The passkey registration request expired. Please try again.', 'atshift-freeform-login' ), array( 'status' => 400 ) );
		}

		try {
			$serializer = $this->serializer();
			$options    = $serializer->denormalize( $stored_options, Webauthn\PublicKeyCredentialCreationOptions::class, 'json' );
			$public_key_credential = $serializer->denormalize( $credential, Webauthn\PublicKeyCredential::class, 'json' );

			if ( ! $public_key_credential->response instanceof Webauthn\AuthenticatorAttestationResponse ) {
				return new WP_Error( 'atshift_passkey_bad_response', __( 'Invalid passkey response.', 'atshift-freeform-login' ), array( 'status' => 400 ) );
			}

			$factory = new Webauthn\CeremonyStep\CeremonyStepManagerFactory();
			$factory->setAllowedOrigins( array( $this->origin() ) );
			$validator = Webauthn\AuthenticatorAttestationResponseValidator::create( $factory->creationCeremony() );
			$record    = $validator->check( $public_key_credential->response, $options, $this->rp_id() );

			if ( ! hash_equals( $this->storage->get_user_handle( $user_id ), $record->userHandle ) ) {
				throw new RuntimeException( 'Credential user handle does not match the current user.' );
			}

			$item      = $this->storage->save_credential( $user_id, $record, $serializer->normalize( $record, 'json' ), $label );
		} catch ( Throwable $exception ) {
			do_action( 'atshift_freeform_login_passkey_error', $exception, 'registration_verify' );

			return new WP_Error( 'atshift_passkey_verify_failed', __( 'The passkey could not be verified.', 'atshift-freeform-login' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'credential' => $this->public_credential_item( $item ),
			)
		);
	}

	/**
	 * Delete a credential.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_credential( $request ) {
		$user_id       = absint( $request->get_param( 'userId' ) );
		$credential_id = sanitize_text_field( (string) $request->get_param( 'credentialId' ) );

		if ( ! $this->storage->delete_credential( $user_id, $credential_id ) ) {
			return new WP_Error( 'atshift_passkey_not_found', __( 'Passkey not found.', 'atshift-freeform-login' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Return request options for a username-less passkey login.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function authentication_options( $request ) {
		if ( ! Atshift_Freeform_Login_Passkey_Environment::is_available() || ! $this->storage->has_credentials() ) {
			return new WP_Error( 'atshift_passkey_unavailable', __( 'Passkey login is not available.', 'atshift-freeform-login' ), array( 'status' => 400 ) );
		}

		if ( Atshift_Freeform_Login_Jetpack::hides_local_login() || Atshift_Freeform_Login_Jetpack::forwards_to_wordpress_com() ) {
			return new WP_Error( 'atshift_passkey_sso_required', __( 'This site requires WordPress.com login.', 'atshift-freeform-login' ), array( 'status' => 403 ) );
		}

		if ( ! $this->authentication_rate_limit_allows_request() ) {
			return new WP_Error( 'atshift_passkey_rate_limited', __( 'Too many passkey requests. Please wait and try again.', 'atshift-freeform-login' ), array( 'status' => 429 ) );
		}

		$params   = $request->get_json_params();
		$params   = is_array( $params ) ? $params : array();
		$redirect = $this->safe_redirect( $params['redirect'] ?? '' );
		$remember = ! empty( $params['remember'] );

		try {
			$serializer = $this->serializer();
			$options    = Webauthn\PublicKeyCredentialRequestOptions::create(
				random_bytes( 32 ),
				$this->rp_id(),
				array(),
				Webauthn\PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
				60000
			);
			$normalized = $serializer->normalize( $options, 'json' );
			$request_id = $this->challenges->store_authentication( $normalized, $redirect, $remember );
		} catch ( Throwable $exception ) {
			do_action( 'atshift_freeform_login_passkey_error', $exception, 'authentication_options' );

			return new WP_Error( 'atshift_passkey_authentication_failed', __( 'Passkey login could not be started.', 'atshift-freeform-login' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'requestId' => $request_id,
				'publicKey' => $normalized,
			)
		);
	}

	/**
	 * Verify a passkey assertion and create a WordPress login session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function authentication_verify( $request ) {
		$params     = $request->get_json_params();
		$params     = is_array( $params ) ? $params : array();
		$request_id = sanitize_text_field( (string) ( $params['requestId'] ?? '' ) );
		$credential = $params['credential'] ?? null;

		if ( '' === $request_id || ! is_array( $credential ) ) {
			return new WP_Error( 'atshift_passkey_bad_request', __( 'Invalid passkey request.', 'atshift-freeform-login' ), array( 'status' => 400 ) );
		}

		$stored = $this->challenges->consume_authentication( $request_id );

		if ( false === $stored ) {
			return new WP_Error( 'atshift_passkey_challenge_expired', __( 'The passkey login request expired. Please try again.', 'atshift-freeform-login' ), array( 'status' => 400 ) );
		}

		$credential_id = sanitize_text_field( (string) ( $credential['id'] ?? '' ) );
		$found         = preg_match( '/^[A-Za-z0-9_-]+$/', $credential_id ) ? $this->storage->find_credential( $credential_id ) : false;

		if ( false === $found || empty( $found['credential']['record'] ) || ! is_array( $found['credential']['record'] ) ) {
			return new WP_Error( 'atshift_passkey_not_found', __( 'The selected passkey is not registered on this site.', 'atshift-freeform-login' ), array( 'status' => 400 ) );
		}

		$user_id = (int) $found['user_id'];

		try {
			$serializer            = $this->serializer();
			$options               = $serializer->denormalize( $stored['options'], Webauthn\PublicKeyCredentialRequestOptions::class, 'json' );
			$public_key_credential = $serializer->denormalize( $credential, Webauthn\PublicKeyCredential::class, 'json' );
			$record                = $serializer->denormalize( $found['credential']['record'], Webauthn\CredentialRecord::class, 'json' );

			if ( ! $public_key_credential->response instanceof Webauthn\AuthenticatorAssertionResponse ) {
				return new WP_Error( 'atshift_passkey_bad_response', __( 'Invalid passkey response.', 'atshift-freeform-login' ), array( 'status' => 400 ) );
			}

			$factory = new Webauthn\CeremonyStep\CeremonyStepManagerFactory();
			$factory->setAllowedOrigins( array( $this->origin() ) );
			$validator = Webauthn\AuthenticatorAssertionResponseValidator::create( $factory->requestCeremony() );
			$record    = $validator->check( $record, $public_key_credential->response, $options, $this->rp_id(), null );

			if ( ! $this->storage->update_credential_record( $user_id, $credential_id, $record, $serializer->normalize( $record, 'json' ) ) ) {
				throw new RuntimeException( 'Credential record could not be updated.' );
			}
		} catch ( Throwable $exception ) {
			do_action( 'atshift_freeform_login_passkey_error', $exception, 'authentication_verify' );

			return new WP_Error( 'atshift_passkey_verify_failed', __( 'The passkey could not be verified.', 'atshift-freeform-login' ), array( 'status' => 400 ) );
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'atshift_passkey_user_missing', __( 'User not found.', 'atshift-freeform-login' ), array( 'status' => 404 ) );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core authentication hook.
		$authenticated_user = apply_filters( 'authenticate', $user, $user->user_login, '' );

		if ( ! is_wp_error( $authenticated_user ) && $authenticated_user instanceof WP_User ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core authentication hook.
			$authenticated_user = apply_filters( 'wp_authenticate_user', $authenticated_user, '' );
		}

		if ( is_wp_error( $authenticated_user ) || ! $authenticated_user instanceof WP_User || (int) $authenticated_user->ID !== $user_id ) {
			return new WP_Error( 'atshift_passkey_login_denied', __( 'Login is not available for this account.', 'atshift-freeform-login' ), array( 'status' => 403 ) );
		}

		$remember = ! empty( $stored['remember'] );
		wp_set_current_user( $authenticated_user->ID );
		wp_set_auth_cookie( $authenticated_user->ID, $remember, is_ssl() );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core login hook.
		do_action( 'wp_login', $authenticated_user->user_login, $authenticated_user );

		return rest_ensure_response(
			array(
				'authenticated' => true,
				'redirect'      => $this->safe_redirect( $stored['redirect'] ?? '' ),
			)
		);
	}

	/**
	 * Build a serializer for WebAuthn objects.
	 *
	 * @return Symfony\Component\Serializer\SerializerInterface
	 */
	private function serializer() {
		$manager = new Webauthn\AttestationStatement\AttestationStatementSupportManager(
			array(
				new Webauthn\AttestationStatement\NoneAttestationStatementSupport(),
			)
		);
		$factory = new Webauthn\Denormalizer\WebauthnSerializerFactory( $manager );

		return $factory->create();
	}

	/**
	 * Return the RP ID.
	 *
	 * @return string
	 */
	private function rp_id() {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		return strtolower( preg_replace( '/:\d+$/', '', (string) $host ) );
	}

	/**
	 * Return the allowed origin.
	 *
	 * @return string
	 */
	private function origin() {
		$parts  = wp_parse_url( home_url( '/' ) );
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'https';
		$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';

		return $scheme . '://' . $host . $port;
	}

	/**
	 * Restrict a requested redirect to this WordPress installation.
	 *
	 * @param mixed $redirect Requested redirect.
	 * @return string
	 */
	private function safe_redirect( $redirect ) {
		$fallback = admin_url();
		$redirect = is_string( $redirect ) ? trim( $redirect ) : '';

		return wp_validate_redirect( $redirect, $fallback );
	}

	/**
	 * Apply a small per-address limit to unauthenticated challenge creation.
	 *
	 * @return bool
	 */
	private function authentication_rate_limit_allows_request() {
		global $wpdb;

		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$bucket  = (int) floor( time() / MINUTE_IN_SECONDS );
		$prefix  = 'atshift_ffl_passkey_rate_' . hash( 'sha256', $address ) . '_';
		$key     = $prefix . $bucket;

		if ( add_option( $key, 1, '', false ) ) {
			$this->cleanup_authentication_rate_limits( $bucket );
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic rate-limit increment cannot use the options API.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d",
				$key,
				30
			)
		);

		return 1 === $updated;
	}

	/**
	 * Remove expired database-backed rate-limit buckets occasionally.
	 *
	 * @param int $current_bucket Current minute bucket.
	 * @return void
	 */
	private function cleanup_authentication_rate_limits( $current_bucket ) {
		global $wpdb;

		if ( 1 !== wp_rand( 1, 100 ) ) {
			return;
		}

		$prefix = 'atshift_ffl_passkey_rate_';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Occasional cleanup of database-backed rate-limit buckets.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(option_name, '_', -1) AS UNSIGNED) < %d",
				$wpdb->esc_like( $prefix ) . '%',
				max( 0, $current_bucket - 2 )
			)
		);
	}

	/**
	 * Return safe credential data for the UI.
	 *
	 * @param array<string, mixed> $item Stored credential.
	 * @return array<string, mixed>
	 */
	private function public_credential_item( $item ) {
		return array(
			'credential_id' => (string) ( $item['credential_id'] ?? '' ),
			'label'         => (string) ( $item['label'] ?? __( 'Passkey', 'atshift-freeform-login' ) ),
			'created_at'    => (string) ( $item['created_at'] ?? '' ),
		);
	}
}
