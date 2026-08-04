(function () {
	'use strict';

	const placeholders = window.atshiftFreeformLoginFrontend || {};

	function applyPlaceholder(container, input, label, placeholder, showLabel) {
		if (!input) {
			return;
		}

		input.placeholder = showLabel ? '' : placeholder;
		if (label) {
			label.classList.add('atshift-freeform-login-field-label');
			label.classList.toggle('atshift-freeform-login-visually-hidden', !showLabel);
		}
	}

	document.querySelectorAll('.atshift-freeform-login').forEach(function (container) {
		const username = container.querySelector('input[name="log"]');
		const password = container.querySelector('input[name="pwd"]');

		applyPlaceholder(
			container,
			username,
			container.querySelector('label[for="user_login"]') || (username ? username.closest('label') : null),
			placeholders.usernamePlaceholder || 'Username / Email',
			Boolean(placeholders.showFieldLabels)
		);
		applyPlaceholder(
			container,
			password,
			container.querySelector('label[for="user_pass"]') || (password ? password.closest('label') : null),
			placeholders.passwordPlaceholder || 'Password',
			Boolean(placeholders.showFieldLabels)
		);
	});
})();
