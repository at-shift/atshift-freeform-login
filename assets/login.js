(function () {
	'use strict';

	const login = document.getElementById('login');
	if (!login) {
		return;
	}

	const placeholders = window.atshiftFreeformLoginScreen || {};
	const username = document.getElementById('user_login');
	const password = document.getElementById('user_pass');

	function applyPlaceholder(input, label, placeholder, showLabel) {
		if (!input) {
			return;
		}

		input.placeholder = showLabel ? '' : placeholder;
		if (label) {
			label.classList.add('atshift-freeform-login-field-label');
			label.classList.toggle('atshift-freeform-login-visually-hidden', !showLabel);
		}
	}

	applyPlaceholder(
		username,
		document.querySelector('label[for="user_login"]'),
		placeholders.usernamePlaceholder || 'Username / Email',
		Boolean(placeholders.showFieldLabels)
	);
	applyPlaceholder(
		password,
		document.querySelector('label[for="user_pass"]'),
		placeholders.passwordPlaceholder || 'Password',
		Boolean(placeholders.showFieldLabels)
	);

	if (login.querySelector('.atshift-freeform-login-secondary')) {
		return;
	}

	const nav = document.getElementById('nav');
	const backToSite = document.getElementById('backtoblog');
	const privacy = document.querySelector('.privacy-policy-page-link');
	const language = document.querySelector('.language-switcher');

	if (!nav && !backToSite && !privacy && !language) {
		return;
	}

	const secondary = document.createElement('div');
	secondary.className = 'atshift-freeform-login-secondary';

	const links = document.createElement('div');
	links.className = 'atshift-freeform-login-secondary-links';

	[nav, backToSite, privacy].forEach(function (element) {
		if (element) {
			links.appendChild(element);
		}
	});

	if (links.children.length) {
		secondary.appendChild(links);
	}

	if (language) {
		secondary.appendChild(language);
	}

	login.appendChild(secondary);
})();
