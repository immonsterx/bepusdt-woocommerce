'use strict';

var registered = null;

global.window = {
	wc: {
		wcBlocksRegistry: {
			registerPaymentMethod: function (definition) { registered = definition; }
		},
		wcSettings: {
			getSetting: function () {
				return {
					title: 'Crypto Payment',
					description: 'Description',
					guideHtml: '<a href="#">Guide</a>',
					iconUrl: 'https://shop.example/crypto-pay.svg',
					icon: { auto: true, width: '', height: '', fit: 'contain' },
					supports: ['products']
				};
			}
		}
	},
	wp: {
		element: {
			createElement: function (type, props) {
				return { type: type, props: props, children: Array.prototype.slice.call(arguments, 2) };
			}
		},
		htmlEntities: { decodeEntities: function (value) { return value; } },
		i18n: { __: function (value) { return value; } }
	}
};

require('../assets/js/blocks.js');

if (!registered || registered.name !== 'bepusdt') {
	throw new Error('BEpusdt was not registered with the Checkout blocks registry.');
}
if (!registered.supports || registered.supports.features[0] !== 'products') {
	throw new Error('Checkout blocks support data is malformed.');
}
if (registered.canMakePayment() !== true) {
	throw new Error('The registered payment method is unexpectedly unavailable.');
}

process.stdout.write('Checkout blocks contract checks passed.\n');
