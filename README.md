<div align="center">
  <img src="assets/plugin-icons/atshift-freeform-login-icon-256.png" width="128" height="128" alt="atshift Freeform Login">
  <h1>atshift Freeform Login</h1>
  <p><strong>Design a polished WordPress login screen and use the same login experience anywhere on your site.</strong></p>
  <p>
    <a href="https://upf.at-shift.net/en/freeform-login/">Official Website</a> ·
    <a href="https://wordpress.org/plugins/atshift-freeform-login/">WordPress.org</a> ·
    <a href="https://upf.at-shift.net/freeform-login/">日本語</a>
  </p>
</div>

## Overview

atshift Freeform Login customizes the standard WordPress login screen without replacing WordPress authentication. Administrators can control the background, branding, form placement, colors, width, and responsive behavior while checking the result in a live preview.

The `[atshift_login]` shortcode places a matching login form on site pages. Jetpack SSO remains supported, and sites that meet the passkey requirements can also offer server-verified passkey registration and login alongside the existing password flow.

## Features

- Responsive customization for the standard WordPress login screen
- Background colors and images with visual positioning controls
- Site-title branding and introductory text
- Form placement, width, colors, and responsive fallback settings
- Live preview before login-screen changes are enabled
- Matching frontend login form with the `[atshift_login]` shortcode
- Jetpack SSO and WordPress.com authentication-flow compatibility
- Multiple named passkeys with registration and last-used dates
- Passkey login on the WordPress login screen and shortcode form
- Optional Passkeys field placement with atshift User Profile Fields

## Requirements

- WordPress 6.5 or later
- PHP 7.4 or later

Passkeys additionally require PHP 8.3 or later, the PHP JSON and OpenSSL extensions, and an HTTPS site. Localhost is supported for development. The design and shortcode features continue to work when the passkey requirements are not met.

## Installation

1. Install the plugin from [WordPress.org](https://wordpress.org/plugins/atshift-freeform-login/) or upload the plugin ZIP from **Plugins > Add Plugin > Upload Plugin**.
2. Activate the plugin.
3. Open **Settings > atshift Freeform Login** in the WordPress admin menu.
4. Configure the design and review it in the live preview.
5. Save the settings and enable the login-screen application when ready.

## Getting Started

Begin with the visual settings screen and choose a background, brand display, and form position. Desktop, tablet, and mobile previews help confirm that the form remains readable and usable at each size.

After saving the design, enable it for the standard WordPress login screen. Add `[atshift_login]` to a page when the same login experience is needed within the site.

## Passkeys

Passkeys let users sign in with a device's biometric authentication, PIN, or security key instead of typing a username and password. Because each credential is created for this site, passkeys reduce the risks of phishing and password reuse.

On a supported server, the registration flow is:

1. Sign in normally and open **Users > Profile**.
2. Select **Add passkey** and approve the prompt shown by the browser or operating system.
3. Give the passkey a recognizable name and confirm it appears in the registered passkeys list.

The device decides whether to use biometrics, a device PIN, a security key, or a QR-code handoff to another device. Users can register more than one passkey. A passkey synchronized through the same storage account may already be available on other devices; otherwise, sign in normally on the additional device and register another passkey there.

After the first passkey is registered on the site, a passkey login button appears on the WordPress login screen and in the `[atshift_login]` shortcode. Password login remains available as a fallback, so each account should still use a long, unique password stored in a password manager.

Passkey ceremonies are verified on the WordPress server and do not require an external authentication service. The bundled WebAuthn and supporting libraries are MIT licensed, with package names and exact versions recorded in `composer.lock`.

When [atshift User Profile Fields](https://wordpress.org/plugins/atshift-user-profile-fields/) is active, its optional Passkeys field can place the same management controls inside the configured profile layout. Credentials and authentication remain managed by atshift Freeform Login.

## Shortcode

Add the login form to a page with:

```text
[atshift_login]
```

Optional attributes include `redirect`, `show_lost_password`, `remember`, `jetpack`, and `class`.

## Pro Add-on

The optional Pro add-on extends the design controls provided by the free plugin. It is installed alongside this free base plugin and is not included in this repository.

Pro adds custom logo images, precise position offsets, transparency, borders, corner radius, and detailed shadow controls.

- Pro add-on: [Upgrade to Pro](https://upf.at-shift.net/en/freeform-login/#pricing)
- Japanese product page: [Freeform Login](https://upf.at-shift.net/freeform-login/)

## Documentation

| Topic | English | 日本語 |
| --- | --- | --- |
| Product guide | [Freeform Login](https://upf.at-shift.net/en/freeform-login/) | [Freeform Login](https://upf.at-shift.net/freeform-login/) |
| WordPress.org | [Plugin directory](https://wordpress.org/plugins/atshift-freeform-login/) | [Plugin directory](https://ja.wordpress.org/plugins/atshift-freeform-login/) |
| Releases | [GitHub Releases](https://github.com/at-shift/atshift-freeform-login/releases) | [GitHub Releases](https://github.com/at-shift/atshift-freeform-login/releases) |

## Related Projects

- [atshift User Profile Fields](https://wordpress.org/plugins/atshift-user-profile-fields/) creates practical, configurable WordPress user profile screens and can place the Freeform Login Passkeys controls within its layouts.
- [at-shift Fields](https://wordpress.org/plugins/atshift-fields-maintenance-for-custom-field-suite/) brings a similar field-building experience to posts and custom post types.

## Reporting Issues

Please use [GitHub Issues](https://github.com/at-shift/atshift-freeform-login/issues) and include reproduction steps together with your WordPress, PHP, and plugin versions.

## License

[GPL-2.0-or-later](LICENSE)
