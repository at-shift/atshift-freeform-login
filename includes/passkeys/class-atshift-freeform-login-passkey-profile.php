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

	/** @var bool */
	private $management_assets_enqueued = false;

	/**
	 * Constructor.
	 *
	 * @param Atshift_Freeform_Login_Passkey_Storage $storage Storage service.
	 */
	public function __construct( $storage ) {
		$this->storage = $storage;

		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'show_user_profile', array( $this, 'render' ), 20 );
		add_action( 'edit_user_profile', array( $this, 'render' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'template_redirect', array( $this, 'maybe_protect_shortcode_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ) );
		add_filter( 'atshift_freeform_login_passkeys_available', array( $this, 'passkeys_available' ) );
		add_filter( 'atshift_upf_passkeys_field_available', array( $this, 'enable_upf_passkeys_field' ) );
		add_filter( 'atshift_freeform_login_passkey_profile_available', array( $this, 'profile_available' ) );
		add_action( 'atshift_upf_render_passkeys_field', array( $this, 'render_upf_field' ), 10, 3 );
	}

	/**
	 * Let integrations check whether the passkey runtime is available.
	 *
	 * @param bool $available Previous availability value.
	 * @return bool
	 */
	public function passkeys_available( $available ) {
		unset( $available );

		return Atshift_Freeform_Login_Passkey_Environment::is_available();
	}

	/**
	 * Register the current-user passkey management shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode() {
		add_shortcode( 'atshift_passkey_profile', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Let account-page integrations hide controls when passkeys cannot be used.
	 *
	 * @param bool $available Previous availability value.
	 * @return bool
	 */
	public function profile_available( $available ) {
		unset( $available );

		return is_user_logged_in() && Atshift_Freeform_Login_Passkey_Environment::is_available();
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

		$this->enqueue_management_assets();
	}

	/**
	 * Enqueue assets early when the shortcode is present in post content.
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_assets() {
		global $post;

		if ( is_user_logged_in() && $post instanceof WP_Post && has_shortcode( $post->post_content, 'atshift_passkey_profile' ) ) {
			$this->enqueue_management_assets();
		}
	}

	/**
	 * Keep personalized passkey pages out of caches and search indexes.
	 *
	 * The surrounding membership plugin remains responsible for requiring a
	 * login because it may provide its own login or account recovery flow.
	 *
	 * @return void
	 */
	public function maybe_protect_shortcode_page() {
		global $post;

		if ( ! $post instanceof WP_Post || ! has_shortcode( $post->post_content, 'atshift_passkey_profile' ) ) {
			return;
		}

		self::prevent_page_cache();
		add_filter( 'wp_robots', array( $this, 'filter_shortcode_page_robots' ) );
	}

	/**
	 * Mark a passkey management page as private to search engines.
	 *
	 * @param array<string, bool> $robots Existing robots directives.
	 * @return array<string, bool>
	 */
	public function filter_shortcode_page_robots( $robots ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;

		return $robots;
	}

	/**
	 * Enqueue shared passkey management assets.
	 *
	 * @return void
	 */
	private function enqueue_management_assets() {
		if ( $this->management_assets_enqueued ) {
			return;
		}

		$this->management_assets_enqueued = true;
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
					'registrationCount' =>
						/* translators: 1: registered passkey count, 2: maximum passkey count. */
						__( 'Registered: %1$d/%2$d', 'atshift-freeform-login' ),
				),
			)
		);
	}

	/**
	 * Render passkey management for the currently logged-in user.
	 *
	 * @param array<string, mixed> $attributes Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $attributes ) {
		self::prevent_page_cache();

		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user = wp_get_current_user();

		if ( ! $user instanceof WP_User || 0 === (int) $user->ID ) {
			return '';
		}

		$attributes = shortcode_atts(
			array(
				'heading' => 'true',
				'class'   => '',
			),
			is_array( $attributes ) ? $attributes : array(),
			'atshift_passkey_profile'
		);

		$this->enqueue_management_assets();

		$user_id     = (int) $user->ID;
		$can_manage  = Atshift_Freeform_Login_Passkey_Environment::is_available();
		$credentials = $this->storage->get_credentials( $user_id );
		$classes     = 'atshift-freeform-login-passkeys atshift-freeform-login-passkeys-shortcode';
		$custom      = self::class_names( $attributes['class'] );

		if ( '' !== $custom ) {
			$classes .= ' ' . $custom;
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-user-id="<?php echo esc_attr( (string) $user_id ); ?>" data-max-passkeys="<?php echo esc_attr( (string) $this->storage->get_max_credentials() ); ?>">
			<?php if ( self::to_bool( $attributes['heading'] ) ) : ?>
				<h2 class="atshift-freeform-login-passkey-heading"><?php echo esc_html__( 'Passkeys', 'atshift-freeform-login' ); ?></h2>
			<?php endif; ?>

			<?php $this->render_intro( $can_manage ); ?>

			<div class="atshift-freeform-login-passkey-shortcode-section">
				<h3><?php echo esc_html__( 'Set passkeys', 'atshift-freeform-login' ); ?></h3>
				<div><?php $this->render_actions( true, $can_manage, count( $credentials ) ); ?></div>
			</div>
			<div class="atshift-freeform-login-passkey-shortcode-section">
				<h3><?php echo esc_html__( 'Registered passkeys', 'atshift-freeform-login' ); ?></h3>
				<div><?php $this->render_history( $credentials, $can_manage ); ?></div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
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
		$can_delete  = Atshift_Freeform_Login_Passkey_Environment::current_user_can_delete_for_user( $user->ID );
		$credentials = $this->storage->get_credentials( $user->ID );
		?>
		<h2 class="atshift-freeform-login-passkey-heading"><?php echo esc_html__( 'Passkeys', 'atshift-freeform-login' ); ?></h2>
		<div class="atshift-freeform-login-passkeys" data-user-id="<?php echo esc_attr( (string) $user->ID ); ?>" data-max-passkeys="<?php echo esc_attr( (string) $this->storage->get_max_credentials() ); ?>">
			<?php $this->render_intro( $can_manage ); ?>

			<table class="form-table atshift-freeform-login-passkey-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Set passkeys', 'atshift-freeform-login' ); ?></th>
					<td><?php $this->render_actions( $is_self, $can_manage, count( $credentials ) ); ?></td>
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
		$can_delete  = Atshift_Freeform_Login_Passkey_Environment::current_user_can_delete_for_user( $user_id );
		$credentials = $this->storage->get_credentials( $user_id );
		?>
		<div class="atshift-freeform-login-passkeys atshift-freeform-login-passkeys-upf" data-user-id="<?php echo esc_attr( (string) $user_id ); ?>" data-max-passkeys="<?php echo esc_attr( (string) $this->storage->get_max_credentials() ); ?>">
			<?php $this->render_intro( $can_manage ); ?>
			<div class="atshift-freeform-login-passkey-upf-actions">
				<?php $this->render_actions( $is_self, $can_manage, count( $credentials ) ); ?>
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
	 * @param int  $credential_count Number of registered credentials.
	 * @return void
	 */
	private function render_actions( $is_self, $can_manage, $credential_count = 0 ) {
		$maximum = $this->storage->get_max_credentials();
		?>
		<p class="description atshift-freeform-login-passkey-count">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: registered passkey count, 2: maximum passkey count. */
					__( 'Registered: %1$d/%2$d', 'atshift-freeform-login' ),
					absint( $credential_count ),
					$maximum
				)
			);
			?>
		</p>
		<?php
		if ( ! Atshift_Freeform_Login_Passkey_Environment::is_available() ) :
			?>
			<p class="description"><?php echo esc_html( Atshift_Freeform_Login_Passkey_Environment::unavailable_message() ); ?></p>
			<?php
		elseif ( $can_manage ) :
			?>
			<p class="atshift-freeform-login-passkey-actions">
				<button type="button" class="button button-secondary atshift-freeform-login-passkey-add" <?php disabled( $maximum <= $credential_count ); ?>>
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

	/**
	 * Normalize a shortcode boolean attribute.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function to_bool( $value ) {
		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Sanitize a space-separated class list.
	 *
	 * @param mixed $classes Raw classes.
	 * @return string
	 */
	private static function class_names( $classes ) {
		$classes = preg_split( '/\s+/', (string) $classes );
		$classes = array_filter( array_map( 'sanitize_html_class', is_array( $classes ) ? $classes : array() ) );

		return implode( ' ', $classes );
	}

	/**
	 * Prevent personalized passkey content from being stored in page caches.
	 *
	 * @return void
	 */
	private static function prevent_page_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress cache-control contract.
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}
}
