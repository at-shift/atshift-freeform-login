=== atshift Freeform Login ===
Contributors: atshift
Tags: login, custom login, login form, shortcode, branding
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.9.3-beta.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Design a beautiful WordPress login screen and place a matching login form anywhere with a shortcode.

== Description ==

atshift Freeform Login customizes the standard WordPress login screen without replacing WordPress authentication. It also provides the `[atshift_login]` shortcode for site pages.

The free plugin includes background colors and images, a site-title brand display, form placement and width, core color controls, responsive fallback, a live preview, the login shortcode, and Jetpack SSO compatibility.

When Jetpack SSO is active, its WordPress.com login UI is styled without replacing Jetpack authentication. The shortcode uses Jetpack automatically and respects Jetpack settings that hide or bypass the local login form. Use `[atshift_login jetpack="hide"]` only when local username and password login remains available.

An optional add-on can extend the free plugin with custom logo images, precise position offsets, transparency, borders, corner radius, and detailed shadow controls. The free plugin remains usable without an add-on.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open Settings > atshift Freeform Login in the WordPress administration menu.
4. Configure and save the design.
5. Enable login-screen application after reviewing the preview.

== Changelog ==

= 0.9.3-beta.3 =
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
