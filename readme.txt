=== atshift Freeform Login ===
Contributors: atshift
Tags: login, custom login, login form, passkey, webauthn
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Design a beautiful WordPress login screen and place a matching login form anywhere with a shortcode.

== Description ==

atshift Freeform Login customizes the standard WordPress login screen without replacing WordPress authentication. It also provides the `[atshift_login]` shortcode for site pages.

The free plugin includes background colors and images, a site-title brand display, form placement and width, core color controls, responsive fallback, a live preview, the login shortcode, and Jetpack SSO compatibility.

Passkey support is available on PHP 8.3 or newer when the PHP JSON and OpenSSL extensions are enabled and the site uses HTTPS. Localhost is supported for development. The rest of the plugin continues to run on its stated minimum PHP version when the passkey module is unavailable.

Users can register, name, and remove multiple passkeys from the standard WordPress profile screen. Synced passkeys may also be available on other devices using the same storage account. After the first passkey is registered on the site, a passkey login button appears on the WordPress login screen and in the `[atshift_login]` shortcode. Username and password login remains available as a fallback, so users should keep a long, unique password and store it in a password manager.

When atshift User Profile Fields is active, its optional Passkeys field can place the same management controls within the configured profile layout. Credentials and authentication remain managed by atshift Freeform Login.

When Jetpack SSO is active, its WordPress.com login UI is styled without replacing Jetpack authentication. The shortcode uses Jetpack automatically and respects Jetpack settings that hide or bypass the local login form. Use `[atshift_login jetpack="hide"]` only when local username and password login remains available.

Passkey ceremonies are verified on the WordPress server and do not require an external authentication service. The bundled WebAuthn and supporting libraries are MIT licensed; package names and exact versions are recorded in `composer.lock`.

== Links ==

* Official website: [upf.at-shift.net/en/freeform-login](https://upf.at-shift.net/en/freeform-login/)

== Shortcodes ==

Use `[atshift_login]` for the complete username, password, and passkey login experience.

If WP-Members or another plugin already provides the username and password form, place `[atshift_passkey_login]` beside it to output only the passkey button. It accepts `redirect`, `remember`, and `class`. `remember` defaults to `false`; use `remember="true"` to request WordPress's persistent login cookie.

The standalone button is omitted when the visitor is already logged in, passkeys are unavailable, no passkey has been registered on the site, or Jetpack SSO disables local login.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open Settings > atshift Freeform Login in the WordPress administration menu.
4. Configure and save the design.
5. Enable login-screen application after reviewing the preview.
6. On PHP 8.3 or newer, open Users > Profile to register a passkey.
7. To add the complete login form to a page, insert `[atshift_login]`.
8. If another plugin already provides the login form, insert `[atshift_passkey_login]` where only the passkey button should appear.

Shortcode examples:

* Complete form with a redirect: `[atshift_login redirect="/my-account/"]`
* Passkey button with a redirect: `[atshift_passkey_login redirect="/my-account/"]`
* Passkey button with a persistent login cookie: `[atshift_passkey_login remember="true"]`

For every attribute and examples of passkey-only integration with an existing login form, see the [Shortcode Guide](https://upf.at-shift.net/en/freeform-login/shortcodes/).

== Pro Add-on ==

The optional Pro add-on extends the free plugin with custom logo images, precise position offsets, transparency, borders, corner radius, and detailed shadow controls. The free plugin remains usable without an add-on.

* Pro add-on: [Upgrade to Pro](https://upf.at-shift.net/en/freeform-login/#pricing)

== Related Projects ==

* [atshift User Profile Fields](https://wordpress.org/plugins/atshift-user-profile-fields/) - create practical WordPress user profile fields and optionally place Freeform Login passkey controls within its profile layouts.
* [at-shift Fields](https://wordpress.org/plugins/atshift-fields-maintenance-for-custom-field-suite/) - arrange custom fields for posts and custom post types with a similar field-building experience.

== Changelog ==

= 2.1.0 =
* Added the `[atshift_passkey_login]` shortcode for placing a standalone passkey button beside an existing login form.
* Added same-site redirect, persistent-login, and custom-class options for the standalone passkey button.
* Improved the position of the WordPress 7.1 Remember Me help bubble across login layouts.
* Added official shortcode documentation and integration examples.

= 2.0.1 =
* Standardized the plugin action and metadata links shown on the Plugins screen.

= 2.0 =
* Added server-verified passkey registration and passwordless login on supported PHP 8.3 or newer HTTPS sites.
* Added profile management for multiple named passkeys with registration and last-used dates.
* Added passkey login to the WordPress login screen and `[atshift_login]` shortcode alongside the existing password fallback.

= 0.9.3-beta.3 =
* Confirmed compatibility with WordPress 7.1 RC3.
* Replaced long placement menus with a visual position picker.
* Added responsive placement controls that switch cleanly between one and two columns.
* Improved live-preview language visibility and placement labels.

= 0.9.3-beta.2 =
* Reordered form and background placement options from left to right, with each column arranged from top to bottom.
* Clarified placement labels in English and Japanese.

= 0.9.3-beta.1 =
* Added introductory text alignment and site-tagline defaults.
* Added add-on-aware settings navigation and Pro upgrade links.
* Improved form placement, background positioning labels, notices, and live-preview cache handling.

= 0.9.2-beta.3 =
* Added background type controls, introductory text, and a cropped wide-logo option.
* Improved automatic interaction colors and login-screen compatibility styling.
* Refined the live preview and grouped conditional settings for clearer editing.

= 0.9.1-beta.1 =
* Moved the design screen under Settings > atshift Freeform Login.
* Added product illustration and plugin icon source assets for GitHub and future directory use.

= 0.9.0-beta.1 =
* Public beta with responsive login customization, shortcode output, and Jetpack SSO compatibility.
