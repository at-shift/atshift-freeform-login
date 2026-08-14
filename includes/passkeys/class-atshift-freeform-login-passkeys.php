<?php
/**
 * Optional passkey module bootstrap.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts passkey-related hooks.
 */
class Atshift_Freeform_Login_Passkeys {
	/** @var Atshift_Freeform_Login_Passkey_Storage */
	private $storage;

	/** Register hooks. */
	public function __construct() {
		$this->storage = new Atshift_Freeform_Login_Passkey_Storage();

		new Atshift_Freeform_Login_Passkey_REST(
			$this->storage,
			new Atshift_Freeform_Login_Passkey_Challenges()
		);
		new Atshift_Freeform_Login_Passkey_Profile( $this->storage );
		new Atshift_Freeform_Login_Passkey_Login( $this->storage );

		add_action( 'init', array( $this, 'register_hooks' ) );
	}

	/**
	 * Notify integrations after the passkey module has loaded.
	 *
	 * @return void
	 */
	public function register_hooks() {
		do_action( 'atshift_freeform_login_passkeys_loaded' );
	}
}
