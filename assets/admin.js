(function ($) {
	'use strict';

	const root = document.querySelector('.atshift-freeform-login-admin');
	if (!root) {
		return;
	}

	const preview = root.querySelector('[data-preview]');
	const previewStage = root.querySelector('[data-preview-stage]');
	const previewSize = root.querySelector('[data-preview-size]');
	const previewDeviceButtons = root.querySelectorAll('[data-preview-device]');
	const previewGroup = root.querySelector('[data-preview-group]');
	const previewForm = root.querySelector('[data-preview-form]');
	const previewLogo = root.querySelector('[data-preview-logo]');
	const previewIntro = root.querySelector('[data-preview-intro]');
	const logoModeControls = root.querySelectorAll('[data-logo-mode-visible]');
	const settingsGroups = root.querySelectorAll('[data-settings-accordion] > details');

	settingsGroups.forEach(function (group) {
		const summary = group.querySelector('summary');

		summary.addEventListener('click', function () {
			if (group.open) {
				return;
			}

			settingsGroups.forEach(function (otherGroup) {
				if (otherGroup !== group) {
					otherGroup.open = false;
				}
			});
		});
	});

	function field(name) {
		return root.querySelector('[data-setting="' + name + '"]');
	}

	function value(name, fallback) {
		const control = field(name);
		if (!control) {
			return fallback;
		}

		if (control.type === 'checkbox') {
			return control.checked;
		}

		return control.value;
	}

	function shadowValue() {
		if (!value('form_shadow', true)) {
			return 'none';
		}

		return '0 18px 50px 0 rgba(0,0,0,0.18)';
	}

	function mixHexColor(source, target, weight) {
		const sourceValue = source.replace('#', '');
		const targetValue = target.replace('#', '');
		const channels = [0, 2, 4].map(function (offset) {
			const from = parseInt(sourceValue.slice(offset, offset + 2), 16);
			const to = parseInt(targetValue.slice(offset, offset + 2), 16);

			return Math.round(from + (to - from) * weight).toString(16).padStart(2, '0');
		});

		return '#' + channels.join('');
	}

	function interactiveColorStates(color) {
		const normalized = /^#[0-9a-f]{6}$/i.test(color) ? color : '#2271b1';
		const red = parseInt(normalized.slice(1, 3), 16);
		const green = parseInt(normalized.slice(3, 5), 16);
		const blue = parseInt(normalized.slice(5, 7), 16);
		const luminance = (0.2126 * red + 0.7152 * green + 0.0722 * blue) / 255;

		if (luminance >= 0.86) {
			return {
				hover: mixHexColor(normalized, '#000000', 0.06),
				active: mixHexColor(normalized, '#000000', 0.12),
				focus: mixHexColor(normalized, '#000000', 0.45)
			};
		}

		if (luminance <= 0.12) {
			return {
				hover: mixHexColor(normalized, '#ffffff', 0.12),
				active: mixHexColor(normalized, '#ffffff', 0.22),
				focus: mixHexColor(normalized, '#ffffff', 0.40)
			};
		}

		return {
			hover: mixHexColor(normalized, '#ffffff', 0.12),
			active: mixHexColor(normalized, '#000000', 0.14),
			focus: normalized
		};
	}

	function selectedViewport() {
		const option = previewSize && previewSize.selectedOptions.length ? previewSize.selectedOptions[0] : null;

		return {
			device: option ? option.dataset.device : 'desktop',
			width: option ? parseInt(option.dataset.width, 10) : 1920,
			height: option ? parseInt(option.dataset.height, 10) : 1080
		};
	}

	function syncDeviceButtons(device) {
		previewDeviceButtons.forEach(function (button) {
			const active = button.dataset.previewDevice === device;
			button.classList.toggle('is-active', active);
			button.setAttribute('aria-pressed', active ? 'true' : 'false');
		});
	}

	function updateViewportLayout() {
		if (!preview || !previewStage || !previewGroup) {
			return;
		}

		const viewport = selectedViewport();
		const stageStyle = window.getComputedStyle(previewStage);
		const availableWidth = previewStage.clientWidth - parseFloat(stageStyle.paddingLeft) - parseFloat(stageStyle.paddingRight);
		const availableHeight = previewStage.clientHeight - parseFloat(stageStyle.paddingTop) - parseFloat(stageStyle.paddingBottom);
		const scale = Math.min(availableWidth / viewport.width, availableHeight / viewport.height, 1);
		const mobileLayout = viewport.width <= 782;
		const shortLayout = viewport.height <= 600;
		const formWidth = parseInt(value('form_width', 340), 10);
		const configuredPosition = mobileLayout ? 'center-center' : value('form_position', 'center-center');
		const horizontalPosition = configuredPosition.split('-')[0] || 'center';

		preview.dataset.device = viewport.device;
		preview.dataset.position = shortLayout ? horizontalPosition + '-top' : configuredPosition;
		preview.style.width = viewport.width + 'px';
		preview.style.height = viewport.height + 'px';
		preview.style.transform = 'translate(-50%, -50%) scale(' + scale + ')';
		preview.style.overflowY = shortLayout ? 'auto' : 'hidden';
		previewGroup.style.width = (mobileLayout ? Math.max(0, viewport.width - 40) : Math.min(formWidth, viewport.width - 48)) + 'px';
		previewGroup.style.transform = 'none';
		syncDeviceButtons(viewport.device);
	}

	function updatePreview() {
		if (!preview || !previewStage || !previewGroup || !previewForm) {
			return;
		}

		const backgroundImage = value('background_image_url', '');
		const backgroundMediaType = value('background_media_type', 'color');
		root.querySelectorAll('[data-background-media-visible]').forEach(function (control) {
			control.hidden = control.dataset.backgroundMediaVisible !== backgroundMediaType;
		});
		root.querySelectorAll('[data-background-media-details]').forEach(function (control) {
			control.hidden = backgroundMediaType === 'color';
		});
		const backgroundColorLabel = root.querySelector('[data-background-color-label]');
		if (backgroundColorLabel) {
			backgroundColorLabel.textContent = backgroundMediaType === 'color' ? atshiftFreeformLoginAdmin.backgroundColorLabel : atshiftFreeformLoginAdmin.fallbackColorLabel;
		}
		preview.style.backgroundColor = value('background_color', '#f0f2f5');
		preview.style.backgroundImage = backgroundMediaType !== 'color' && backgroundImage ? 'url("' + backgroundImage + '")' : 'none';
		preview.style.backgroundPosition = value('background_position', 'center center');
		preview.style.backgroundSize = value('background_size', 'cover');

		updateViewportLayout();
		previewForm.style.backgroundColor = value('form_background_color', '#ffffff');
		previewForm.style.setProperty('--atshift-preview-form-bg', value('form_background_color', '#ffffff'));
		previewForm.style.border = '0 solid transparent';
		previewForm.style.borderRadius = '8px';
		previewForm.style.boxShadow = shadowValue();
		previewForm.style.color = value('label_color', '#1d2327');
		const showFieldLabels = value('show_field_labels', false);
		previewGroup.querySelectorAll('[data-preview-field-label]').forEach(function (label) {
			label.classList.toggle('atshift-freeform-login-visually-hidden', !showFieldLabels);
		});
		previewGroup.querySelectorAll('[data-placeholder]').forEach(function (input) {
			input.placeholder = showFieldLabels ? '' : input.dataset.placeholder;
		});
		const linkColor = value('link_color', '#2271b1');
		const linkStates = interactiveColorStates(linkColor);
		previewGroup.style.setProperty('--atshift-preview-link', linkColor);
		previewGroup.style.setProperty('--atshift-preview-link-hover', linkStates.hover);
		previewGroup.style.setProperty('--atshift-preview-link-active', linkStates.active);
		previewGroup.style.setProperty('--atshift-preview-link-focus', linkStates.focus);
		const buttons = previewForm.querySelectorAll('[data-preview-button]');
		const buttonColor = value('button_background_color', '#2271b1');
		const buttonStates = interactiveColorStates(buttonColor);
		previewGroup.style.setProperty('--atshift-preview-button', buttonColor);
		previewGroup.style.setProperty('--atshift-preview-button-hover', buttonStates.hover);
		previewGroup.style.setProperty('--atshift-preview-button-active', buttonStates.active);
		previewGroup.style.setProperty('--atshift-preview-button-focus', buttonStates.focus);
		buttons.forEach(function (button) {
			button.style.backgroundColor = buttonColor;
			button.style.borderColor = buttonColor;
			button.style.color = value('button_text_color', '#ffffff');
		});

		const logoMode = value('logo_mode', 'site_title');
		logoModeControls.forEach(function (control) {
			control.hidden = control.dataset.logoModeVisible !== logoMode;
		});
		previewLogo.hidden = logoMode === 'none';
		previewLogo.classList.remove('has-image');
		previewLogo.style.color = logoMode === 'site_title' ? value('brand_text_color', '#1d2327') : '';
		previewLogo.style.width = '100%';
		previewLogo.style.backgroundImage = 'none';
		previewLogo.style.aspectRatio = '';
		if (previewIntro) {
			const introText = value('intro_text', '').trim();
			previewIntro.hidden = introText === '';
			previewIntro.textContent = introText;
			previewIntro.style.width = value('intro_width', 100) + '%';
			previewIntro.style.color = value('label_color', '#1d2327');
		}

		document.dispatchEvent(new CustomEvent('atshift-freeform-login:preview-updated', {
			detail: {
				root: root,
				preview: preview,
				previewGroup: previewGroup,
				previewForm: previewForm,
				previewLogo: previewLogo,
				mobileLayout: selectedViewport().width <= 782,
				shortLayout: selectedViewport().height <= 600
			}
		}));
	}

	root.addEventListener('input', updatePreview);
	root.addEventListener('change', updatePreview);

	previewDeviceButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			const device = button.dataset.previewDevice;
			const option = Array.from(previewSize.options).find(function (candidate) {
				return candidate.dataset.device === device;
			});

			if (option) {
				previewSize.value = option.value;
				updatePreview();
			}
		});
	});

	if ('ResizeObserver' in window && previewStage) {
		new ResizeObserver(updateViewportLayout).observe(previewStage);
	} else {
		window.addEventListener('resize', updateViewportLayout);
	}

	root.querySelectorAll('[data-media-control]').forEach(function (control) {
		const idField = control.querySelector('[data-media-id]');
		const urlField = control.querySelector('[data-media-url]');
		const removeButton = control.querySelector('[data-remove-media]');
		const mediaType = control.dataset.mediaType || 'image';

		control.querySelector('[data-select-media]').addEventListener('click', function () {
			const frame = wp.media({
				title: mediaType === 'video' ? atshiftFreeformLoginAdmin.videoTitle : atshiftFreeformLoginAdmin.imageTitle,
				button: { text: mediaType === 'video' ? atshiftFreeformLoginAdmin.videoButton : atshiftFreeformLoginAdmin.imageButton },
				library: { type: mediaType },
				multiple: false
			});

			frame.on('select', function () {
				const attachment = frame.state().get('selection').first().toJSON();
				idField.value = attachment.id;
				urlField.value = attachment.url;
				removeButton.hidden = false;
				updatePreview();
			});

			frame.open();
		});

		removeButton.addEventListener('click', function () {
			idField.value = '';
			urlField.value = '';
			removeButton.hidden = true;
			updatePreview();
		});
	});

	updatePreview();
})(jQuery);
