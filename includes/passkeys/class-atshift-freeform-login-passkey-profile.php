<?php
/**
 * User profile passkey UI.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders passkey management on the user profile screen.
 */
class Atshift_Freeform_Login_Passkey_Profile {
	/** @var Atshift_Freeform_Login_Passkey_Storage */
	private $storage;

	/** @var array<int, bool> */
	private $upf_rendered_users = array();

	/**
	 * Constructor.
	 *
	 * @param Atshift_Freeform_Login_Passkey_Storage $storage Storage service.
	 */
	public function __construct( $storage ) {
		$this->storage = $storage;

		add_action( 'show_user_profile', array( $this, 'render' ), 20 );
		add_action( 'edit_user_profile', array( $this, 'render' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'atshift_upf_passkeys_field_available', array( $this, 'enable_upf_passkeys_field' ) );
		add_action( 'atshift_upf_render_passkeys_field', array( $this, 'render_upf_field' ), 10, 3 );
	}

	/**
	 * Expose the optional UPF field while this plugin is active.
	 *
	 * @param bool $available Whether another integration exposed the field.
	 * @return bool
	 */
	public function enable_upf_passkeys_field( $available ) {
		unset( $available );

		return true;
	}

	/**
	 * Enqueue the profile script only where it is needed.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'atshift-freeform-login-passkeys',
			ATSHIFT_FREEFORM_LOGIN_URL . 'assets/passkeys.css',
			array(),
			ATSHIFT_FREEFORM_LOGIN_VERSION
		);

		if ( ! Atshift_Freeform_Login_Passkey_Environment::is_available() ) {
			return;
		}

		wp_enqueue_script(
			'atshift-freeform-login-passkeys',
			ATSHIFT_FREEFORM_LOGIN_URL . 'assets/passkeys.js',
			array(),
			ATSHIFT_FREEFORM_LOGIN_VERSION,
			true
		);
		wp_localize_script(
			'atshift-freeform-login-passkeys',
			'atshiftFreeformLoginPasskeys',
			array(
				'restUrl'        => esc_url_raw( rest_url( 'atshift-freeform-login/v1/passkeys/' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'currentUserId'  => get_current_user_id(),
				'messages'       => array(
					'unsupported' => __( 'This browser does not support passkeys.', 'atshift-freeform-login' ),
					'registering' => __( 'Creating passkey...', 'atshift-freeform-login' ),
					'registered'  => __( 'Passkey registered.', 'atshift-freeform-login' ),
					'deleted'     => __( 'Passkey deleted.', 'atshift-freeform-login' ),
					'failed'      => __( 'Passkey operation failed.', 'atshift-freeform-login' ),
					'delete'      => __( 'Delete', 'atshift-freeform-login' ),
					'confirmDelete' => __( 'Delete this passkey?', 'atshift-freeform-login' ),
					'none'        => __( 'No passkeys are registered for this account.', 'atshift-freeform-login' ),
					'namePrompt'  => __( 'Passkey name', 'atshift-freeform-login' ),
					'defaultName' => __( "This device's passkey", 'atshift-freeform-login' ),
					'registeredNow' => __( 'Registered: Just now', 'atshift-freeform-login' ),
					'lastUsedNever' => __( 'Last used: Never', 'atshift-freeform-login' ),
				),
			)
		);
	}

	/**
	 * Render the passkey section.
	 *
	 * @param WP_User $user Profile user.
	 * @return void
	 */
	public function render( $user ) {
		if ( ! $user instanceof WP_User || isset( $this->upf_rendered_users[ (int) $user->ID ] ) ) {
			return;
		}

		$is_self     = get_current_user_id() === (int) $user->ID;
		$can_manage  = $is_self && Atshift_Freeform_Login_Passkey_Environment::is_available();
		$can_delete  = $can_manage || current_user_can( 'edit_user', $user->ID );
		$credentials = $this->storage->get_credentials( $user->ID );
		?>
		<h2 class="atshift-freeform-login-passkey-heading"><?php echo esc_html__( 'Passkeys', 'atshift-freeform-login' ); ?></h2>
		<div class="atshift-freeform-login-passkeys" data-user-id="<?php echo esc_attr( (string) $user->ID ); ?>">
			<?php $this->render_intro( $can_manage ); ?>

			<table class="form-table atshift-freeform-login-passkey-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Set passkeys', 'atshift-freeform-login' ); ?></th>
					<td><?php $this->render_actions( $is_self, $can_manage ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Registered passkeys', 'atshift-freeform-login' ); ?></th>
					<td><?php $this->render_history( $credentials, $can_delete ); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render passkey management inside a UPF field.
	 *
	 * @param WP_User             $user Profile user.
	 * @param array<string,mixed> $field UPF field definition.
	 * @param string              $screen UPF screen context.
	 * @return void
	 */
	public function render_upf_field( $user, $field = array(), $screen = 'edit' ) {
		unset( $field );

		if ( 'edit' !== $screen || ! $user instanceof WP_User ) {
			return;
		}

		$user_id = (int) $user->ID;
		$this->upf_rendered_users[ $user_id ] = true;
		$is_self     = get_current_user_id() === $user_id;
		$can_manage  = $is_self && Atshift_Freeform_Login_Passkey_Environment::is_available();
		$can_delete  = $can_manage || current_user_can( 'edit_user', $user_id );
		$credentials = $this->storage->get_credentials( $user_id );
		?>
		<div class="atshift-freeform-login-passkeys atshift-freeform-login-passkeys-upf" data-user-id="<?php echo esc_attr( (string) $user_id ); ?>">
			<?php $this->render_intro( $can_manage ); ?>
			<div class="atshift-freeform-login-passkey-upf-actions">
				<?php $this->render_actions( $is_self, $can_manage ); ?>
			</div>
			<div class="atshift-freeform-login-passkey-upf-history">
				<h3><?php echo esc_html__( 'Registered passkeys', 'atshift-freeform-login' ); ?></h3>
				<?php $this->render_history( $credentials, $can_delete ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the passkey introduction for users who can register credentials.
	 *
	 * @param bool $can_manage Whether registration is available.
	 * @return void
	 */
	private function render_intro( $can_manage ) {
		if ( ! $can_manage ) {
			return;
		}
		?>
		<p class="description atshift-freeform-login-passkey-intro"><?php echo esc_html__( 'Passkeys are a new way to sign in using biometric authentication and other security features on your device instead of entering a username and password. They reduce the risk of entering a password on a fake site or reusing the same password across services. Start by registering a device you use regularly. Passkeys synced to the same storage account can be used on your other devices, and you can add more than one when needed.', 'atshift-freeform-login' ); ?></p>
		<?php
	}

	/**
	 * Render registration controls and their status region.
	 *
	 * @param bool $is_self Whether this is the current user's profile.
	 * @param bool $can_manage Whether registration is available.
	 * @return void
	 */
	private function render_actions( $is_self, $can_manage ) {
		if ( ! Atshift_Freeform_Login_Passkey_Environment::is_available() ) :
			?>
			<p class="description"><?php echo esc_html( Atshift_Freeform_Login_Passkey_Environment::unavailable_message() ); ?></p>
			<?php
		elseif ( $can_manage ) :
			?>
			<p class="atshift-freeform-login-passkey-actions">
				<button type="button" class="button button-secondary atshift-freeform-login-passkey-add">
					<?php echo esc_html__( 'Add passkey', 'atshift-freeform-login' ); ?>
				</button>
			</p>
			<p class="description atshift-freeform-login-passkey-password-note"><?php echo esc_html__( 'Password login remains available after you register a passkey. To keep your account secure, use a long, strong password that you do not reuse on other services, and store it in a password manager.', 'atshift-freeform-login' ); ?></p>
			<?php
		elseif ( ! $is_self ) :
			?>
			<p class="description"><?php echo esc_html__( 'Users must add passkeys from their own profile screen.', 'atshift-freeform-login' ); ?></p>
			<?php
		endif;
		?>
		<p class="description atshift-freeform-login-passkey-status" aria-live="polite"></p>
		<?php
	}

	/**
	 * Render the registered credential list.
	 *
	 * @param array<int,array<string,mixed>> $credentials Stored credentials.
	 * @param bool                           $can_delete Whether delete controls are allowed.
	 * @return void
	 */
	private function render_history( $credentials, $can_delete ) {
		?>
		<div class="atshift-freeform-login-passkey-history">
			<ul class="atshift-freeform-login-passkey-list">
				<?php foreach ( $credentials as $credential ) : ?>
					<?php
					$credential_id = (string) ( $credential['credential_id'] ?? '' );
					$label         = (string) ( $credential['label'] ?? __( 'Passkey', 'atshift-freeform-login' ) );
					$created_at    = (string) ( $credential['created_at'] ?? '' );
					$last_used_at  = (string) ( $credential['last_used_at'] ?? '' );
					?>
					<li data-credential-id="<?php echo esc_attr( $credential_id ); ?>">
						<div class="atshift-freeform-login-passkey-details">
							<strong class="atshift-freeform-login-passkey-label"><?php echo esc_html( $label ); ?></strong>
							<div class="atshift-freeform-login-passkey-meta">
								<?php if ( '' !== $created_at ) : ?>
									<span class="description">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: passkey registration date. */
												__( 'Registered: %s', 'atshift-freeform-login' ),
												get_date_from_gmt( $created_at, get_option( 'date_format' ) )
											)
										);
										?>
									</span>
								<?php endif; ?>
								<span class="description">
									<?php
									echo esc_html(
										'' !== $last_used_at
											? sprintf(
												/* translators: %s: date and time when the passkey was last used. */
												__( 'Last used: %s', 'atshift-freeform-login' ),
												get_date_from_gmt( $last_used_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
											)
											: __( 'Last used: Never', 'atshift-freeform-login' )
									);
									?>
								</span>
							</div>
						</div>
						<?php if ( $can_delete && '' !== $credential_id ) : ?>
							<button type="button" class="button-link-delete atshift-freeform-login-passkey-delete" data-credential-id="<?php echo esc_attr( $credential_id ); ?>">
								<?php echo esc_html__( 'Delete', 'atshift-freeform-login' ); ?>
							</button>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( empty( $credentials ) ) : ?>
				<p class="description atshift-freeform-login-passkey-empty"><?php echo esc_html__( 'No passkeys are registered for this account.', 'atshift-freeform-login' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
