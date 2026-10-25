'use strict';

var fs = require('fs');
var path = require('path');
var playwright = require('playwright');

(async function () {
	var chromePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
	var launchOptions = { headless: true };
	if (fs.existsSync(chromePath)) {
		launchOptions.executablePath = chromePath;
	}
	var browser = await playwright.chromium.launch(launchOptions);
	var page = await browser.newPage({ viewport: { width: 1100, height: 800 } });

	await page.setContent([
		'<div class="wc_payment_methods" style="width:760px">',
		'  <div><label><img id="theme-icon" alt="" src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2224%22/%3E"></label></div>',
		'  <div class="bepusdt-wc-checkout" data-bepusdt-checkout data-icon-auto="yes" data-icon-width="" data-icon-height="">',
		'    <div class="bepusdt-wc-method-grid"><button class="bepusdt-wc-method bepusdt-wc-method--image" data-bepusdt-primary-method><span class="bepusdt-wc-method-card"><img id="auto-icon" src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 50 11.25%22/%3E"></span></button></div>',
		'  </div>',
		'  <div class="bepusdt-wc-checkout" data-bepusdt-checkout data-icon-auto="yes" data-icon-width="120" data-icon-height="" style="--bepusdt-icon-width:120px;--bepusdt-icon-fit:contain">',
		'    <div class="bepusdt-wc-method-grid"><button class="bepusdt-wc-method bepusdt-wc-method--image" data-bepusdt-primary-method><span class="bepusdt-wc-method-card"><img id="manual-icon" src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 50 11.25%22/%3E"></span></button></div>',
		'  </div>',
		'</div>',
		'<div class="woocommerce-order" style="width:760px;position:relative">',
		'  <div id="nested"><section class="bepusdt-wc-payment" data-bepusdt-payment></section></div>',
		'  <div class="woocommerce-order-details" style="width:620px;margin-left:70px;height:40px"></div>',
		'</div>'
	].join(''));

	await page.addStyleTag({ path: path.resolve(__dirname, '../assets/css/frontend.css') });
	await page.addScriptTag({ path: path.resolve(__dirname, '../assets/js/frontend.js') });
	await page.evaluate(function () { document.dispatchEvent(new Event('DOMContentLoaded')); });
	await page.waitForTimeout(100);

	var result = await page.evaluate(function () {
		var autoIcon = document.getElementById('auto-icon');
		var manualIcon = document.getElementById('manual-icon');
		var card = document.querySelector('[data-bepusdt-payment]').getBoundingClientRect();
		var parent = document.querySelector('.woocommerce-order').getBoundingClientRect();
		return {
			autoHeight: Math.round(parseFloat(getComputedStyle(autoIcon).height)),
			manualWidth: Math.round(parseFloat(getComputedStyle(manualIcon).width)),
			manualHeight: Math.round(parseFloat(getComputedStyle(manualIcon).height)),
			cardWidth: Math.round(card.width),
			cardOffset: Math.round(card.left - parent.left)
		};
	});

	await browser.close();

	if (result.autoHeight !== 24) {
		throw new Error('Automatic icon sizing did not follow the theme reference height: ' + result.autoHeight);
	}
	if (result.manualWidth !== 120 || result.manualHeight !== 27) {
		throw new Error('A one-sided manual size did not preserve the SVG aspect ratio: ' + JSON.stringify(result));
	}
	if (result.cardWidth !== 620 || result.cardOffset !== 70) {
		throw new Error('Payment card did not follow the theme content container: ' + JSON.stringify(result));
	}

	process.stdout.write('Frontend layout checks passed.\n');
}()).catch(function (error) {
	process.stderr.write(error.stack + '\n');
	process.exit(1);
});
