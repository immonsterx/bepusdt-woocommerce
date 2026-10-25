(function () {
	'use strict';

	var registry = window.wc && window.wc.wcBlocksRegistry;
	var wcSettings = window.wc && window.wc.wcSettings;
	var element = window.wp && window.wp.element;
	var htmlEntities = window.wp && window.wp.htmlEntities;
	var i18n = window.wp && window.wp.i18n;

	if (!registry || !wcSettings || !element || !htmlEntities || !i18n) {
		return;
	}

	var settings = wcSettings.getSetting('bepusdt_data', {});
	var icon = settings.icon || {};
	var title = htmlEntities.decodeEntities(settings.title || '');
	var ariaTitle = title || i18n.__('Cryptocurrency', 'bepusdt-woocommerce');
	var description = htmlEntities.decodeEntities(settings.description || '');
	var iconStyle = {
		objectFit: icon.fit || 'contain'
	};

	if (icon.width) {
		iconStyle.width = String(icon.width) + 'px';
	}
	if (icon.height) {
		iconStyle.height = String(icon.height) + 'px';
	}

	function Icon() {
		if (!settings.iconUrl) {
			return null;
		}

		return element.createElement('img', {
			className: 'bepusdt-wc-block-icon',
			src: settings.iconUrl,
			alt: '',
			style: iconStyle
		});
	}

	function Label() {
		return element.createElement(
			'span',
			{
				className: 'bepusdt-wc-block-label',
				'data-bepusdt-block-icon': '',
				'data-icon-auto': icon.auto ? 'yes' : 'no',
				'data-icon-width': icon.width || '',
				'data-icon-height': icon.height || ''
			},
			title ? element.createElement('span', null, title) : null,
			element.createElement(Icon)
		);
	}

	function Content() {
		var children = [];

		if (description) {
			children.push(element.createElement('p', { key: 'description' }, description));
		}
		if (settings.guideHtml) {
			children.push(element.createElement('div', {
				className: 'bepusdt-wc-notice',
				dangerouslySetInnerHTML: { __html: settings.guideHtml },
				key: 'guide'
			}));
		}

		return element.createElement('div', { className: 'bepusdt-wc-block-content' }, children);
	}

	registry.registerPaymentMethod({
		name: 'bepusdt',
		label: element.createElement(Label),
		content: element.createElement(Content),
		edit: element.createElement(Content),
		canMakePayment: function () { return true; },
		ariaLabel: ariaTitle,
		supports: { features: settings.supports || ['products'] }
	});
}());
