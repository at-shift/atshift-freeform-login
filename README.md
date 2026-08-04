# atshift Freeform Login

Design a beautiful WordPress login screen and place a matching login form anywhere with a shortcode.

The `codex/wordpress-org-1.0` branch contains the WordPress.org submission candidate. Translations are distributed through WordPress.org language packs rather than bundled with this branch.

## Features

- Customize the standard WordPress login screen without replacing WordPress authentication
- Set background colors and images
- Control form placement, width, colors, and responsive behavior
- Preview changes before applying them
- Place a matching login form with `[atshift_login]`
- Preserve Jetpack SSO and its WordPress.com authentication flow

## Shortcode

```text
[atshift_login]
```

Optional attributes include `redirect`, `show_lost_password`, `remember`, `jetpack`, and `class`.

Shortcode usage is documented here rather than in the plugin settings screen, keeping the design workspace focused on visual customization.

## Requirements

- WordPress 6.5 or later
- PHP 7.4 or later

## License

GPL-2.0-or-later
