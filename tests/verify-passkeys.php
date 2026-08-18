<?php
/**
 * Passkey module integration checks for a loaded WordPress test environment.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Load WordPress before this file.\n" );
}

$failures         = array();
$original_user_id = get_current_user_id();
$original_index   = get_option( Atshift_Freeform_Login_Passkey_Storage::INDEX_KEY, null );
$test_user_id     = 0;

$check = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

register_shutdown_function(
	static function () use ( &$test_user_id, $original_user_id, $original_index ) {
		if ( $test_user_id ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $test_user_id );
		}

		if ( null === $original_index ) {
			delete_option( Atshift_Freeform_Login_Passkey_Storage::INDEX_KEY );
		} else {
			update_option( Atshift_Freeform_Login_Passkey_Storage::INDEX_KEY, $original_index, false );
		}

		wp_set_current_user( $original_user_id );
	}
);

$check( Atshift_Freeform_Login_Passkey_Environment::is_available(), 'Passkeys must be available on the PHP 8.3 localhost test environment.' );

$login = 'atshift_passkey_test_' . strtolower( wp_generate_password( 8, false, false ) );
$test_user_id = wp_create_user( $login, wp_generate_password( 24 ), $login . '@example.test' );

if ( is_wp_error( $test_user_id ) ) {
	$failures[]   = 'A temporary passkey test user could not be created.';
	$test_user_id = 0;
} else {
	wp_set_current_user( $test_user_id );

	$manager    = new Webauthn\AttestationStatement\AttestationStatementSupportManager(
		array( new Webauthn\AttestationStatement\NoneAttestationStatementSupport() )
	);
	$serializer = ( new Webauthn\Denormalizer\WebauthnSerializerFactory( $manager ) )->create();
	$storage    = new Atshift_Freeform_Login_Passkey_Storage();
	$user_handle = $storage->get_user_handle( $test_user_id );
	$record      = Webauthn\CredentialRecord::create(
		random_bytes( 32 ),
		Webauthn\PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
		array( 'internal' ),
		'none',
		Webauthn\TrustPath\EmptyTrustPath::create(),
		Symfony\Component\Uid\Uuid::v4(),
		'random-public-key-for-storage-test',
		$user_handle,
		0
	);
	$item       = $storage->save_credential( $test_user_id, $record, $serializer->normalize( $record, 'json' ), 'Test device' );
	$found      = $storage->find_credential( $item['credential_id'] );

	$check( false !== $found && $test_user_id === (int) $found['user_id'], 'Credential lookup must resolve the owning user.' );
	$check( hash_equals( $user_handle, $storage->get_user_handle( $test_user_id ) ), 'A user must retain the same opaque WebAuthn handle.' );
	$check( 1 === count( $storage->get_descriptors( $test_user_id ) ), 'Stored credentials must become registration exclusion descriptors.' );

	$profile = new Atshift_Freeform_Login_Passkey_Profile( $storage );
	ob_start();
	$profile->render( get_user_by( 'id', $test_user_id ) );
	$profile_html = (string) ob_get_clean();
	$check( false !== strpos( $profile_html, 'atshift-freeform-login-passkey-add' ), 'A user must be able to add a passkey from their own profile.' );
	$check( false !== strpos( $profile_html, 'Test device' ), 'The profile must list stored passkeys.' );
	$check( false !== strpos( $profile_html, 'risk of entering a password on a fake site' ), 'The profile must explain why passkeys improve login security.' );
	$check( false !== strpos( $profile_html, 'you can add more than one' ), 'The profile must explain that multiple passkeys can be registered.' );
	$check( false !== strpos( $profile_html, 'Password login remains available' ), 'The profile must explain that password login remains available.' );
	$check( false !== strpos( $profile_html, 'Last used: Never' ), 'A new passkey must be identified as unused.' );
	$check( (bool) apply_filters( 'atshift_upf_passkeys_field_available', false ), 'Freeform Login must expose the optional UPF passkeys field.' );

	ob_start();
	$profile->render_upf_field( get_user_by( 'id', $test_user_id ), array( 'type' => 'passkeys' ), 'edit' );
	$upf_profile_html = (string) ob_get_clean();
	$check( false !== strpos( $upf_profile_html, 'atshift-freeform-login-passkeys-upf' ), 'UPF must receive the lightweight passkey management UI.' );
	$check( false === strpos( $upf_profile_html, 'atshift-freeform-login-passkey-heading' ), 'The UPF field must not repeat the standalone Passkeys heading.' );
	$check( false !== strpos( $upf_profile_html, 'Test device' ), 'The UPF field must list stored passkeys.' );

	ob_start();
	$profile->render( get_user_by( 'id', $test_user_id ) );
	$duplicate_profile_html = (string) ob_get_clean();
	$check( '' === trim( $duplicate_profile_html ), 'The standalone profile section must be suppressed after UPF renders the passkeys field.' );

	$challenges = new Atshift_Freeform_Login_Passkey_Challenges();
	$rest       = new Atshift_Freeform_Login_Passkey_REST( $storage, $challenges );
	$request    = new WP_REST_Request( 'POST', '/atshift-freeform-login/v1/passkeys/registration/options' );
	$request->set_param( 'userId', $test_user_id );
	$options_response = $rest->registration_options( $request );
	$options_data     = $options_response instanceof WP_REST_Response ? $options_response->get_data() : array();
	$check( ! empty( $options_data['requestId'] ) && ! empty( $options_data['publicKey']['challenge'] ), 'Registration options must contain a stored challenge.' );
	$check( false !== $challenges->consume_registration( $test_user_id, $options_data['requestId'] ?? '' ), 'Registration challenges must be consumable once.' );

	$auth_request = new WP_REST_Request( 'POST', '/atshift-freeform-login/v1/passkeys/authentication/options' );
	$auth_request->set_header( 'content-type', 'application/json' );
	$auth_request->set_body( wp_json_encode( array( 'redirect' => admin_url(), 'remember' => true ) ) );
	$auth_response = $rest->authentication_options( $auth_request );
	$auth_data     = $auth_response instanceof WP_REST_Response ? $auth_response->get_data() : array();
	$check( ! empty( $auth_data['requestId'] ) && ! empty( $auth_data['publicKey']['challenge'] ), 'Authentication options must contain a stored challenge.' );
	$check( false !== $challenges->consume_authentication( $auth_data['requestId'] ?? '' ), 'Authentication challenges must be consumable once.' );

	$passkey_html = apply_filters( 'atshift_freeform_login_shortcode_passkey_html', '', admin_url(), false );
	$check( false !== strpos( $passkey_html, 'atshift-freeform-login-passkey-login-button' ), 'The shortcode integration must expose a passkey login button.' );

	$record->counter = 2;
	$updated         = $storage->update_credential_record( $test_user_id, $item['credential_id'], $record, $serializer->normalize( $record, 'json' ) );
	$stored          = $storage->get_credentials( $test_user_id );
	$check( $updated && 2 === (int) $stored[0]['counter'] && '' !== $stored[0]['last_used_at'], 'Successful assertions must update the credential counter and last-used time.' );
	ob_start();
	$profile->render( get_user_by( 'id', $test_user_id ) );
	$used_profile_html = (string) ob_get_clean();
	$check( false === strpos( $used_profile_html, 'Last used: Never' ), 'A used passkey must display its last-used date and time.' );
	$check( $storage->delete_credential( $test_user_id, $item['credential_id'] ), 'Users must be able to delete a stored passkey.' );
	$check( false === $storage->find_credential( $item['credential_id'] ), 'Deleting a passkey must remove its lookup index.' );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "Freeform Login passkey checks passed.\n";
