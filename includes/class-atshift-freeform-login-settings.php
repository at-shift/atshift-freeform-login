<?php
/**
 * Admin settings.
 *
 * @package AtshiftFreeformLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and renders login design settings.
 */
class Atshift_Freeform_Login_Settings {
	const OPTION_KEY = 'atshift_freeform_login_settings';
	const PAGE_SLUG  = 'atshift-freeform-login';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_atshift_freeform_login_save_settings', array( $this, 'handle_save' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return apply_filters( 'atshift_freeform_login_default_settings', self::base_defaults() );
	}

	/**
	 * Defaults owned by the free plugin.
	 *
	 * Add-ons may extend defaults(), but these keys remain the storage boundary
	 * for the WordPress.org plugin.
	 *
	 * @return array<string, mixed>
	 */
	public static function base_defaults() {
		return array(
			'enabled'                 => 0,
			'background_media_type'   => 'color',
			'background_color'        => '#f0f2f5',
			'background_image_id'     => 0,
			'background_image_url'    => '',
			'background_position'     => 'center center',
			'background_size'         => 'cover',
			'logo_mode'               => 'site_title',
			'banner_logo_image_id'    => 0,
			'banner_logo_image_url'   => '',
			'brand_text_color'        => '#1d2327',
			'intro_text'              => '',
			'intro_width'             => 100,
			'form_position'           => 'center-center',
			'form_width'              => 340,
			'form_background_color'   => '#ffffff',
			'show_field_labels'       => 0,
			'form_shadow'             => 1,
			'button_background_color' => '#2271b1',
			'button_text_color'       => '#ffffff',
			'label_color'             => '#1d2327',
			'link_color'              => '#2271b1',
		);
	}

	/**
	 * Return normalized settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		$stored   = get_option( self::OPTION_KEY, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$settings = array_intersect_key( $stored, self::base_defaults() );

		if ( ! array_key_exists( 'background_media_type', $stored ) ) {
			$settings['background_media_type'] = empty( $stored['background_image_id'] ) ? 'color' : 'image';
		}

		$settings = apply_filters( 'atshift_freeform_login_stored_settings', $settings );

		return self::sanitize_settings( array_merge( self::defaults(), is_array( $settings ) ? $settings : array() ) );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		$output['enabled']           = empty( $input['enabled'] ) ? 0 : 1;
		$output['form_shadow']       = empty( $input['form_shadow'] ) ? 0 : 1;
		$output['show_field_labels'] = empty( $input['show_field_labels'] ) ? 0 : 1;
		$output['intro_text']        = isset( $input['intro_text'] ) ? sanitize_textarea_field( (string) $input['intro_text'] ) : '';
		$output['intro_width']       = self::bounded_int( isset( $input['intro_width'] ) ? $input['intro_width'] : $defaults['intro_width'], 30, 100 );
		$background_media_types      = apply_filters( 'atshift_freeform_login_background_media_types', array( 'color', 'image' ) );
		$output['background_media_type'] = self::allowed_value(
			isset( $input['background_media_type'] ) ? $input['background_media_type'] : '',
			is_array( $background_media_types ) ? $background_media_types : array( 'color', 'image' ),
			$defaults['background_media_type']
		);

		foreach ( array( 'background_color', 'brand_text_color', 'form_background_color', 'button_background_color', 'button_text_color', 'label_color', 'link_color' ) as $key ) {
			$color          = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
			$output[ $key ] = $color ? $color : $defaults[ $key ];
		}

		$output['background_image_id'] = isset( $input['background_image_id'] ) ? absint( $input['background_image_id'] ) : 0;

		$background_url = $output['background_image_id'] ? wp_get_attachment_image_url( $output['background_image_id'], 'full' ) : false;

		$output['background_image_url'] = $background_url ? esc_url_raw( $background_url ) : '';
		$output['banner_logo_image_id'] = isset( $input['banner_logo_image_id'] ) ? absint( $input['banner_logo_image_id'] ) : 0;

		$banner_logo_url = $output['banner_logo_image_id'] ? wp_get_attachment_image_url( $output['banner_logo_image_id'], 'full' ) : false;

		$output['banner_logo_image_url'] = $banner_logo_url ? esc_url_raw( $banner_logo_url ) : '';

		$output['logo_mode'] = self::allowed_value(
			isset( $input['logo_mode'] ) ? $input['logo_mode'] : '',
			array( 'site_title', 'banner', 'none' ),
			$defaults['logo_mode']
		);
		$output['form_position'] = self::allowed_value(
			isset( $input['form_position'] ) ? $input['form_position'] : '',
			array( 'left-top', 'center-top', 'right-top', 'left-center', 'center-center', 'right-center', 'left-bottom', 'center-bottom', 'right-bottom' ),
			$defaults['form_position']
		);
		$output['background_position'] = self::allowed_value(
			isset( $input['background_position'] ) ? $input['background_position'] : '',
			array( 'left top', 'center top', 'right top', 'left center', 'center center', 'right center', 'left bottom', 'center bottom', 'right bottom' ),
			$defaults['background_position']
		);
		$output['background_size'] = self::allowed_value(
			isset( $input['background_size'] ) ? $input['background_size'] : '',
			array( 'cover', 'contain', 'auto' ),
			$defaults['background_size']
		);

		$output['form_width'] = self::bounded_int( isset( $input['form_width'] ) ? $input['form_width'] : $defaults['form_width'], 240, 720 );

		return apply_filters( 'atshift_freeform_login_sanitize_settings', $output, $input, $defaults );
	}

	/**
	 * Add the settings submenu.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'atshift Freeform Login', 'atshift-freeform-login' ),
			__( 'atshift Freeform Login', 'atshift-freeform-login' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Load assets on this plugin screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'atshift-freeform-login-admin',
			ATSHIFT_FREEFORM_LOGIN_URL . 'assets/admin.css',
			array(),
			ATSHIFT_FREEFORM_LOGIN_VERSION
		);
		wp_enqueue_script(
			'atshift-freeform-login-admin',
			ATSHIFT_FREEFORM_LOGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			ATSHIFT_FREEFORM_LOGIN_VERSION,
			true
		);
		wp_localize_script(
			'atshift-freeform-login-admin',
			'atshiftFreeformLoginAdmin',
			array(
				'imageTitle'  => __( 'Select an image', 'atshift-freeform-login' ),
				'imageButton' => __( 'Use this image', 'atshift-freeform-login' ),
				'videoTitle'  => __( 'Select a loop video', 'atshift-freeform-login' ),
				'videoButton' => __( 'Use this video', 'atshift-freeform-login' ),
				'backgroundColorLabel' => __( 'Background color', 'atshift-freeform-login' ),
				'fallbackColorLabel'   => __( 'Fallback background color', 'atshift-freeform-login' ),
			)
		);
	}

	/**
	 * Save submitted settings.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'atshift-freeform-login' ) );
		}

		check_admin_referer( 'atshift_freeform_login_save_settings' );

		$raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			? wp_unslash( $_POST['settings'] )
			: array();

		$stored    = get_option( self::OPTION_KEY, array() );
		$stored    = is_array( $stored ) ? array_intersect_key( $stored, self::base_defaults() ) : array();
		$raw       = array_merge( $stored, $raw );
		$sanitized = self::sanitize_settings( $raw );
		$free_only = array_intersect_key( $sanitized, self::base_defaults() );

		update_option( self::OPTION_KEY, $free_only, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'updated' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Render the first design screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag set by handle_save() after nonce verification.
		$updated = isset( $_GET['updated'] ) ? sanitize_text_field( wp_unslash( $_GET['updated'] ) ) : '';
		?>
		<div class="wrap atshift-freeform-login-admin">
			<header class="atshift-freeform-login-page-header">
				<div>
					<h1><?php esc_html_e( 'atshift Freeform Login', 'atshift-freeform-login' ); ?><?php do_action( 'atshift_freeform_login_page_title' ); ?></h1>
					<p><?php esc_html_e( 'Design your login, your way.', 'atshift-freeform-login' ); ?></p>
				</div>
			</header>

			<?php if ( '1' === $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Login design settings saved.', 'atshift-freeform-login' ); ?></p></div>
			<?php endif; ?>

			<?php Atshift_Freeform_Login_Jetpack::render_admin_status(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="atshift_freeform_login_save_settings">
				<?php wp_nonce_field( 'atshift_freeform_login_save_settings' ); ?>

				<div class="atshift-freeform-login-toolbar">
					<label class="atshift-freeform-login-toggle">
						<input type="hidden" name="settings[enabled]" value="0">
						<input type="checkbox" name="settings[enabled]" value="1" <?php checked( $settings['enabled'], 1 ); ?>>
						<span><?php esc_html_e( 'Apply this design to the WordPress login screen', 'atshift-freeform-login' ); ?></span>
					</label>
					<?php submit_button( __( 'Save changes', 'atshift-freeform-login' ), 'primary', 'submit', false ); ?>
				</div>

				<div class="atshift-freeform-login-workspace">
					<div class="atshift-freeform-login-settings">
						<div class="atshift-freeform-login-settings-accordion" data-settings-accordion>
							<details class="atshift-freeform-login-settings-group" open>
								<summary><?php do_action( 'atshift_freeform_login_settings_group_summary', 'background' ); ?><?php esc_html_e( 'Background', 'atshift-freeform-login' ); ?></summary>
								<p class="atshift-freeform-login-settings-description"><?php echo esc_html( $this->group_description( 'background', __( 'Set the background color and image for the entire login screen.', 'atshift-freeform-login' ) ) ); ?></p>
							<div class="atshift-freeform-login-control-grid">
								<?php
								$background_type_options = apply_filters(
									'atshift_freeform_login_background_media_type_options',
									array(
										'color' => __( 'Background color', 'atshift-freeform-login' ),
										'image' => __( 'Image', 'atshift-freeform-login' ),
									)
								);
								$this->render_select_field( 'background_media_type', __( 'Background type', 'atshift-freeform-login' ), $settings, $background_type_options );
								?>
								<div class="atshift-freeform-login-expanded-options" data-background-options>
									<?php $this->render_color_field( 'background_color', __( 'Background color', 'atshift-freeform-login' ), $settings, '', true ); ?>
									<?php do_action( 'atshift_freeform_login_settings_background_controls', $this, $settings ); ?>
									<?php $this->render_media_field( 'background_image', __( 'Image', 'atshift-freeform-login' ), $settings, 'image', 'image' ); ?>
									<div class="atshift-freeform-login-background-media-details" data-background-media-details<?php echo 'color' === $settings['background_media_type'] ? ' hidden' : ''; ?>>
										<?php $this->render_select_field( 'background_position', __( 'Display position', 'atshift-freeform-login' ), $settings, $this->background_positions() ); ?>
										<?php $this->render_select_field( 'background_size', __( 'Display size', 'atshift-freeform-login' ), $settings, array( 'cover' => __( 'Cover', 'atshift-freeform-login' ), 'contain' => __( 'Contain', 'atshift-freeform-login' ), 'auto' => __( 'Original size', 'atshift-freeform-login' ) ) ); ?>
									</div>
								</div>
							</div>
							<?php $this->render_upgrade_note( 'background' ); ?>
							</details>

							<details class="atshift-freeform-login-settings-group">
							<summary><?php do_action( 'atshift_freeform_login_settings_group_summary', 'brand' ); ?><?php esc_html_e( 'Brand', 'atshift-freeform-login' ); ?></summary>
							<p class="atshift-freeform-login-settings-description"><?php echo esc_html( $this->group_description( 'brand', __( 'Choose whether the site title appears above the login form and set its color.', 'atshift-freeform-login' ) ) ); ?></p>
						<div class="atshift-freeform-login-control-grid">
							<?php
							$logo_modes = apply_filters(
								'atshift_freeform_login_logo_modes',
								array(
									'site_title' => __( 'Site title', 'atshift-freeform-login' ),
									'banner'     => __( 'Wide logo image', 'atshift-freeform-login' ),
									'none'       => __( 'Do not show', 'atshift-freeform-login' ),
								)
							);
							$this->render_select_field( 'logo_mode', __( 'Logo', 'atshift-freeform-login' ), $settings, $logo_modes );
							?>
							<div class="atshift-freeform-login-expanded-options" data-logo-options<?php echo 'none' === $settings['logo_mode'] ? ' hidden' : ''; ?>>
								<?php $this->render_color_field( 'brand_text_color', __( 'Site title color', 'atshift-freeform-login' ), $settings, 'site_title' ); ?>
								<div class="atshift-freeform-login-logo-mode-control" data-logo-mode-visible="banner">
									<?php $this->render_media_field( 'banner_logo_image', __( 'Wide logo image', 'atshift-freeform-login' ), $settings ); ?>
									<small><?php esc_html_e( 'Displayed in a 4:1 frame. Tall images are cropped from the center.', 'atshift-freeform-login' ); ?></small>
								</div>
								<?php do_action( 'atshift_freeform_login_settings_brand_controls', $this, $settings ); ?>
							</div>
							<?php $this->render_textarea_field( 'intro_text', __( 'Introductory text', 'atshift-freeform-login' ), $settings, __( 'Displayed between the brand and login form. Leave blank to hide it.', 'atshift-freeform-login' ) ); ?>
							<?php $this->render_number_field( 'intro_width', __( 'Text width', 'atshift-freeform-login' ), $settings, 30, 100, 1, '%' ); ?>
						</div>
							<?php $this->render_upgrade_note( 'brand' ); ?>
							</details>

								<details class="atshift-freeform-login-settings-group">
								<summary><?php do_action( 'atshift_freeform_login_settings_group_summary', 'placement' ); ?><?php esc_html_e( 'Form placement and size', 'atshift-freeform-login' ); ?></summary>
									<p class="atshift-freeform-login-settings-description"><?php echo esc_html( $this->group_description( 'placement', __( 'Set the login form position and width.', 'atshift-freeform-login' ) ) ); ?></p>
									<div class="atshift-freeform-login-control-grid">
										<?php $this->render_select_field( 'form_position', __( 'Position', 'atshift-freeform-login' ), $settings, $this->form_positions() ); ?>
									<?php $this->render_number_field( 'form_width', __( 'Form width', 'atshift-freeform-login' ), $settings, 240, 720, 1, 'px' ); ?>
									<?php do_action( 'atshift_freeform_login_settings_placement_controls', $this, $settings ); ?>
									</div>
									<?php $this->render_upgrade_note( 'placement' ); ?>
								</details>

								<?php do_action( 'atshift_freeform_login_settings_groups', $this, $settings ); ?>

								<details class="atshift-freeform-login-settings-group">
								<summary><?php do_action( 'atshift_freeform_login_settings_group_summary', 'form_style' ); ?><?php esc_html_e( 'Form background and border', 'atshift-freeform-login' ); ?></summary>
									<p class="atshift-freeform-login-settings-description"><?php echo esc_html( $this->group_description( 'form_style', __( 'Set the form background color.', 'atshift-freeform-login' ) ) ); ?></p>
									<div class="atshift-freeform-login-control-grid">
										<?php $this->render_color_field( 'form_background_color', __( 'Form background', 'atshift-freeform-login' ), $settings ); ?>
									<?php do_action( 'atshift_freeform_login_settings_form_style_controls', $this, $settings ); ?>
									</div>
									<?php $this->render_upgrade_note( 'form_style' ); ?>
								</details>

								<details class="atshift-freeform-login-settings-group">
								<summary><?php do_action( 'atshift_freeform_login_settings_group_summary', 'text_buttons' ); ?><?php esc_html_e( 'Text and buttons', 'atshift-freeform-login' ); ?></summary>
									<p class="atshift-freeform-login-settings-description"><?php esc_html_e( 'Choose labels or placeholders and set the colors used for text, links, and the login button.', 'atshift-freeform-login' ); ?></p>
									<div class="atshift-freeform-login-control-grid">
									<label class="atshift-freeform-login-control atshift-freeform-login-checkbox-control">
										<span><?php esc_html_e( 'Field labels', 'atshift-freeform-login' ); ?></span>
										<span class="atshift-freeform-login-checkbox-line">
											<input type="hidden" name="settings[show_field_labels]" value="1">
											<input type="checkbox" name="settings[show_field_labels]" value="0" <?php checked( empty( $settings['show_field_labels'] ) ); ?> data-setting="use_placeholders">
											<span><?php esc_html_e( 'Use placeholders', 'atshift-freeform-login' ); ?></span>
										</span>
										</label>
										<?php $this->render_color_field( 'label_color', __( 'Text color', 'atshift-freeform-login' ), $settings ); ?>
										<?php $this->render_color_field( 'link_color', __( 'Link color', 'atshift-freeform-login' ), $settings ); ?>
										<?php $this->render_color_field( 'button_background_color', __( 'Button color', 'atshift-freeform-login' ), $settings ); ?>
										<?php $this->render_color_field( 'button_text_color', __( 'Button text color', 'atshift-freeform-login' ), $settings ); ?>
										<?php do_action( 'atshift_freeform_login_settings_button_border_controls', $this, $settings ); ?>
										<?php do_action( 'atshift_freeform_login_settings_text_button_controls', $this, $settings ); ?>
									</div>
									<?php $this->render_upgrade_note( 'text_buttons' ); ?>
								</details>

								<details class="atshift-freeform-login-settings-group">
								<summary><?php do_action( 'atshift_freeform_login_settings_group_summary', 'shadow' ); ?><?php esc_html_e( 'Shadow', 'atshift-freeform-login' ); ?></summary>
									<p class="atshift-freeform-login-settings-description"><?php echo esc_html( $this->group_description( 'shadow', __( 'Choose whether the form has a shadow.', 'atshift-freeform-login' ) ) ); ?></p>
									<div class="atshift-freeform-login-control-grid">
									<label class="atshift-freeform-login-control atshift-freeform-login-checkbox-control">
										<span><?php esc_html_e( 'Drop shadow', 'atshift-freeform-login' ); ?></span>
										<span class="atshift-freeform-login-checkbox-line">
											<input type="hidden" name="settings[form_shadow]" value="0">
											<input type="checkbox" name="settings[form_shadow]" value="1" <?php checked( $settings['form_shadow'], 1 ); ?> data-setting="form_shadow">
											<span><?php esc_html_e( 'Enable drop shadow', 'atshift-freeform-login' ); ?></span>
										</span>
									</label>
									<?php do_action( 'atshift_freeform_login_settings_shadow_controls', $this, $settings ); ?>
									</div>
									<?php $this->render_upgrade_note( 'shadow' ); ?>
								</details>
						</div>
					</div>

					<div class="atshift-freeform-login-preview-column">
						<div class="atshift-freeform-login-preview-heading">
							<div>
								<h2><?php esc_html_e( 'Preview', 'atshift-freeform-login' ); ?></h2>
								<p><?php esc_html_e( 'Preview using representative screen sizes.', 'atshift-freeform-login' ); ?></p>
							</div>
							<div class="atshift-freeform-login-preview-toolbar">
								<div class="atshift-freeform-login-device-switch" role="group" aria-label="<?php esc_attr_e( 'Preview device', 'atshift-freeform-login' ); ?>">
									<button type="button" class="is-active" data-preview-device="desktop" aria-pressed="true" title="<?php esc_attr_e( 'Desktop', 'atshift-freeform-login' ); ?>"><span class="dashicons dashicons-desktop" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Desktop', 'atshift-freeform-login' ); ?></span></button>
									<button type="button" data-preview-device="tablet" aria-pressed="false" title="<?php esc_attr_e( 'Tablet', 'atshift-freeform-login' ); ?>"><span class="dashicons dashicons-tablet" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Tablet', 'atshift-freeform-login' ); ?></span></button>
									<button type="button" data-preview-device="mobile" aria-pressed="false" title="<?php esc_attr_e( 'Mobile', 'atshift-freeform-login' ); ?>"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Mobile', 'atshift-freeform-login' ); ?></span></button>
								</div>
								<label>
									<span class="screen-reader-text"><?php esc_html_e( 'Screen size', 'atshift-freeform-login' ); ?></span>
									<select data-preview-size aria-label="<?php esc_attr_e( 'Screen size', 'atshift-freeform-login' ); ?>">
										<optgroup label="<?php esc_attr_e( 'Desktop', 'atshift-freeform-login' ); ?>">
											<option value="desktop-16-9" data-device="desktop" data-width="1920" data-height="1080">1920 × 1080 (16:9)</option>
											<option value="desktop-16-10" data-device="desktop" data-width="1920" data-height="1200">1920 × 1200 (16:10)</option>
											<option value="desktop-21-9" data-device="desktop" data-width="2560" data-height="1080">2560 × 1080 (21:9)</option>
											<option value="desktop-4-3" data-device="desktop" data-width="1440" data-height="1080">1440 × 1080 (4:3)</option>
										</optgroup>
										<optgroup label="<?php esc_attr_e( 'Tablet', 'atshift-freeform-login' ); ?>">
											<option value="tablet-portrait" data-device="tablet" data-width="768" data-height="1024">768 × 1024 (3:4)</option>
											<option value="tablet-landscape" data-device="tablet" data-width="1024" data-height="768">1024 × 768 (4:3)</option>
										</optgroup>
										<optgroup label="<?php esc_attr_e( 'Mobile', 'atshift-freeform-login' ); ?>">
											<option value="mobile-portrait" data-device="mobile" data-width="390" data-height="844"><?php esc_html_e( '390 × 844 (Standard)', 'atshift-freeform-login' ); ?></option>
											<option value="mobile-landscape" data-device="mobile" data-width="844" data-height="390"><?php esc_html_e( '844 × 390 (Landscape)', 'atshift-freeform-login' ); ?></option>
										</optgroup>
									</select>
								</label>
							</div>
						</div>
						<div class="atshift-freeform-login-preview-stage" data-preview-stage>
							<div class="atshift-freeform-login-preview" data-preview data-device="desktop">
								<?php do_action( 'atshift_freeform_login_preview_background', $settings ); ?>
								<div class="atshift-freeform-login-preview-group" data-preview-group>
								<div class="atshift-freeform-login-preview-logo" data-preview-logo><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
								<div class="atshift-freeform-login-preview-intro" data-preview-intro <?php echo '' === trim( $settings['intro_text'] ) ? 'hidden' : ''; ?>><?php echo nl2br( esc_html( $settings['intro_text'] ) ); ?></div>
								<div class="atshift-freeform-login-preview-form" data-preview-form>
									<?php if ( Atshift_Freeform_Login_Jetpack::is_sso_active() ) : ?>
										<div class="atshift-freeform-login-preview-sso">
											<button type="button" data-preview-button disabled><span class="dashicons dashicons-wordpress" aria-hidden="true"></span><?php esc_html_e( 'Log in with WordPress.com', 'atshift-freeform-login' ); ?></button>
										</div>
										<?php if ( ! Atshift_Freeform_Login_Jetpack::hides_local_login() && ! Atshift_Freeform_Login_Jetpack::forwards_to_wordpress_com() ) : ?>
											<div class="atshift-freeform-login-preview-separator"><span><?php esc_html_e( 'Or', 'atshift-freeform-login' ); ?></span></div>
										<?php endif; ?>
									<?php endif; ?>
									<?php if ( ! Atshift_Freeform_Login_Jetpack::hides_local_login() && ! Atshift_Freeform_Login_Jetpack::forwards_to_wordpress_com() ) : ?>
									<label><span class="atshift-freeform-login-field-label<?php echo empty( $settings['show_field_labels'] ) ? ' atshift-freeform-login-visually-hidden' : ''; ?>" data-preview-field-label><?php esc_html_e( 'Username / Email', 'atshift-freeform-login' ); ?></span><input type="text" placeholder="<?php echo empty( $settings['show_field_labels'] ) ? esc_attr__( 'Username / Email', 'atshift-freeform-login' ) : ''; ?>" data-placeholder="<?php esc_attr_e( 'Username / Email', 'atshift-freeform-login' ); ?>" disabled></label>
									<label><span class="atshift-freeform-login-field-label<?php echo empty( $settings['show_field_labels'] ) ? ' atshift-freeform-login-visually-hidden' : ''; ?>" data-preview-field-label><?php esc_html_e( 'Password', 'atshift-freeform-login' ); ?></span><input type="password" placeholder="<?php echo empty( $settings['show_field_labels'] ) ? esc_attr__( 'Password', 'atshift-freeform-login' ) : ''; ?>" data-placeholder="<?php esc_attr_e( 'Password', 'atshift-freeform-login' ); ?>" disabled></label>
									<div class="atshift-freeform-login-preview-actions">
										<label><input type="checkbox" disabled> <?php esc_html_e( 'Remember Me', 'atshift-freeform-login' ); ?></label>
										<button type="button" data-preview-button disabled><?php esc_html_e( 'Log In', 'atshift-freeform-login' ); ?></button>
									</div>
									<?php endif; ?>
								</div>
								<div class="atshift-freeform-login-preview-secondary">
									<div class="atshift-freeform-login-preview-links">
										<?php if ( ! Atshift_Freeform_Login_Jetpack::hides_local_login() && ! Atshift_Freeform_Login_Jetpack::forwards_to_wordpress_com() ) : ?>
											<a href="#" tabindex="-1"><?php esc_html_e( 'Lost your password?', 'atshift-freeform-login' ); ?></a>
										<?php endif; ?>
										<a href="#" tabindex="-1"><?php esc_html_e( 'Back to site', 'atshift-freeform-login' ); ?></a>
										<a href="#" tabindex="-1"><?php esc_html_e( 'Privacy Policy', 'atshift-freeform-login' ); ?></a>
									</div>
									<div class="atshift-freeform-login-preview-language">
										<select disabled><option><?php esc_html_e( 'Language', 'atshift-freeform-login' ); ?></option></select>
										<button type="button" data-preview-button disabled><?php esc_html_e( 'Change', 'atshift-freeform-login' ); ?></button>
									</div>
								</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Return a section description that add-ons may extend.
	 *
	 * @param string $group       Group identifier.
	 * @param string $description Free description.
	 * @return string
	 */
	private function group_description( $group, $description ) {
		return (string) apply_filters( 'atshift_freeform_login_settings_group_description', $description, $group );
	}

	/**
	 * Explain the free boundary and the corresponding Pro extension.
	 *
	 * The note is intentionally absent while Pro is active. A purchase action
	 * can be added later through the action without changing this layout.
	 *
	 * @param string $group Group identifier.
	 * @return void
	 */
	private function render_upgrade_note( $group ) {
		if ( defined( 'ATSHIFT_FREEFORM_LOGIN_PRO_VERSION' ) || class_exists( 'Atshift_Freeform_Login_Pro' ) ) {
			return;
		}

		$notes = array(
			'background'   => array(
				'tier' => 'pro',
				'text' => __( 'Background color and images are available in the free version. Upgrade to Pro to use a lightweight loop video.', 'atshift-freeform-login' ),
			),
			'brand'        => array(
				'tier' => 'pro',
				'text' => __( 'Free accepts a wide logo image in a fixed 4:1 frame; images with a different height are cropped from the center. Pro preserves wide and tall image ratios and lets you adjust the logo width.', 'atshift-freeform-login' ),
			),
			'placement'    => array(
				'tier' => 'pro',
				'text' => __( 'Upgrade to Pro to fine-tune the horizontal and vertical position in pixels.', 'atshift-freeform-login' ),
			),
			'form_style'   => array(
				'tier' => 'pro',
				'text' => __( 'Upgrade to Pro to adjust opacity, corner radius, and border styling.', 'atshift-freeform-login' ),
			),
			'text_buttons' => array(
				'tier' => 'pro',
				'text' => __( 'Free automatically adjusts link and button colors for hover and pressed states. Pro lets you choose each interaction color and hide the language selector on the login screen.', 'atshift-freeform-login' ),
			),
			'shadow'       => array(
				'tier' => 'pro',
				'text' => __( 'Upgrade to Pro to adjust shadow position, blur, spread, color, and opacity.', 'atshift-freeform-login' ),
			),
		);

		if ( ! isset( $notes[ $group ] ) ) {
			return;
		}

		$note = $notes[ $group ];
		?>
		<div class="atshift-freeform-login-upgrade-note is-<?php echo esc_attr( $note['tier'] ); ?>">
			<span class="atshift-freeform-login-upgrade-badge"><?php echo esc_html( strtoupper( $note['tier'] ) ); ?></span>
			<span><?php echo esc_html( $note['text'] ); ?></span>
			<?php do_action( 'atshift_freeform_login_upgrade_note_action', $group, $note['tier'] ); ?>
		</div>
		<?php
	}

	/** @return array<string, string> */
	private function form_positions() {
		return array(
			'left-top'     => __( 'Top left', 'atshift-freeform-login' ),
			'center-top'   => __( 'Top center', 'atshift-freeform-login' ),
			'right-top'    => __( 'Top right', 'atshift-freeform-login' ),
			'left-center'  => __( 'Center left', 'atshift-freeform-login' ),
			'center-center'=> __( 'Center', 'atshift-freeform-login' ),
			'right-center' => __( 'Center right', 'atshift-freeform-login' ),
			'left-bottom'  => __( 'Bottom left', 'atshift-freeform-login' ),
			'center-bottom'=> __( 'Bottom center', 'atshift-freeform-login' ),
			'right-bottom' => __( 'Bottom right', 'atshift-freeform-login' ),
		);
	}

	/** @return array<string, string> */
	private function background_positions() {
		return array(
			'left top'     => __( 'Top left', 'atshift-freeform-login' ),
			'center top'   => __( 'Top center', 'atshift-freeform-login' ),
			'right top'    => __( 'Top right', 'atshift-freeform-login' ),
			'left center'  => __( 'Center left', 'atshift-freeform-login' ),
			'center center'=> __( 'Center', 'atshift-freeform-login' ),
			'right center' => __( 'Center right', 'atshift-freeform-login' ),
			'left bottom'  => __( 'Bottom left', 'atshift-freeform-login' ),
			'center bottom'=> __( 'Bottom center', 'atshift-freeform-login' ),
			'right bottom' => __( 'Bottom right', 'atshift-freeform-login' ),
		);
	}

	/**
	 * Render a color control.
	 *
	 * @param string               $key Settings key.
	 * @param string               $label Label.
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $logo_mode Optional logo mode that displays the control.
	 * @param bool                 $dynamic_background_label Whether JavaScript updates the background label.
	 * @return void
	 */
	public function render_color_field( $key, $label, $settings, $logo_mode = '', $dynamic_background_label = false ) {
		?>
		<label class="atshift-freeform-login-control"<?php echo '' !== $logo_mode ? ' data-logo-mode-visible="' . esc_attr( $logo_mode ) . '"' : ''; ?>>
			<span<?php echo $dynamic_background_label ? ' data-background-color-label' : ''; ?>><?php echo esc_html( $label ); ?></span>
			<input type="color" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ); ?>" data-setting="<?php echo esc_attr( $key ); ?>">
		</label>
		<?php
	}

	/**
	 * Render a number control.
	 *
	 * @param string               $key Settings key.
	 * @param string               $label Label.
	 * @param array<string, mixed> $settings Settings.
	 * @param int                  $min Minimum.
	 * @param int                  $max Maximum.
	 * @param int                  $step Step.
	 * @param string               $unit Unit.
	 * @return void
	 */
	public function render_number_field( $key, $label, $settings, $min, $max, $step, $unit ) {
		?>
		<label class="atshift-freeform-login-control">
			<span><?php echo esc_html( $label ); ?></span>
			<span class="atshift-freeform-login-number"><input type="number" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" data-setting="<?php echo esc_attr( $key ); ?>"><small><?php echo esc_html( $unit ); ?></small></span>
		</label>
		<?php
	}

	/**
	 * Render a multiline plain-text control.
	 *
	 * @param string               $key Settings key.
	 * @param string               $label Label.
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $description Supporting description.
	 * @return void
	 */
	public function render_textarea_field( $key, $label, $settings, $description = '' ) {
		?>
		<label class="atshift-freeform-login-control atshift-freeform-login-textarea-control">
			<span><?php echo esc_html( $label ); ?></span>
			<textarea name="settings[<?php echo esc_attr( $key ); ?>]" rows="4" data-setting="<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( $settings[ $key ] ); ?></textarea>
			<?php if ( '' !== $description ) : ?>
				<small><?php echo esc_html( $description ); ?></small>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Render a select control.
	 *
	 * @param string               $key Settings key.
	 * @param string               $label Label.
	 * @param array<string, mixed> $settings Settings.
	 * @param array<string, string> $options Options.
	 * @return void
	 */
	public function render_select_field( $key, $label, $settings, $options ) {
		?>
		<label class="atshift-freeform-login-control">
			<span><?php echo esc_html( $label ); ?></span>
			<select name="settings[<?php echo esc_attr( $key ); ?>]" data-setting="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $options as $value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings[ $key ], $value ); ?>><?php echo esc_html( $option_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php
	}

	/**
	 * Render a media control.
	 *
	 * @param string               $prefix Settings prefix.
	 * @param string               $label Label.
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $media_type Media-library type.
	 * @param string               $visible_mode Optional background-media mode.
	 * @param string               $select_label Optional select-button label.
	 * @return void
	 */
	public function render_media_field( $prefix, $label, $settings, $media_type = 'image', $visible_mode = '', $select_label = '' ) {
		$id_key  = $prefix . '_id';
		$url_key = $prefix . '_url';
		$select_label = '' !== $select_label ? $select_label : ( 'video' === $media_type ? __( 'Select video', 'atshift-freeform-login' ) : __( 'Select image', 'atshift-freeform-login' ) );
		$is_hidden = '' !== $visible_mode && isset( $settings['background_media_type'] ) && $visible_mode !== $settings['background_media_type'];
		?>
		<div class="atshift-freeform-login-control atshift-freeform-login-media-control" data-media-control="<?php echo esc_attr( $prefix ); ?>" data-media-type="<?php echo esc_attr( $media_type ); ?>"<?php echo '' !== $visible_mode ? ' data-background-media-visible="' . esc_attr( $visible_mode ) . '"' : ''; ?><?php echo $is_hidden ? ' hidden' : ''; ?>>
			<span><?php echo esc_html( $label ); ?></span>
			<input type="hidden" name="settings[<?php echo esc_attr( $id_key ); ?>]" value="<?php echo esc_attr( $settings[ $id_key ] ); ?>" data-media-id>
			<input type="hidden" value="<?php echo esc_url( $settings[ $url_key ] ); ?>" data-setting="<?php echo esc_attr( $url_key ); ?>" data-media-url>
			<div class="atshift-freeform-login-media-actions">
				<button type="button" class="button" data-select-media><?php echo esc_html( $select_label ); ?></button>
				<button type="button" class="button-link-delete" data-remove-media <?php echo empty( $settings[ $id_key ] ) ? 'hidden' : ''; ?>><?php esc_html_e( 'Remove', 'atshift-freeform-login' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Return an allowlisted value.
	 *
	 * @param mixed         $value Raw value.
	 * @param array<string> $allowed Allowed values.
	 * @param string        $fallback Fallback.
	 * @return string
	 */
	private static function allowed_value( $value, $allowed, $fallback ) {
		$value = sanitize_text_field( (string) $value );

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Return a bounded integer.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min Minimum.
	 * @param int   $max Maximum.
	 * @return int
	 */
	private static function bounded_int( $value, $min, $max ) {
		$value = (int) $value;

		return max( $min, min( $max, $value ) );
	}
}
