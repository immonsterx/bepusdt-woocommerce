(function () {
	'use strict';

	var settings = window.bepusdtWc || {};

	function showNotice(container, message) {
		var notice = container.querySelector('[data-bepusdt-notice]');
		if (!notice) {
			return;
		}

		notice.textContent = message;
		notice.hidden = false;
		window.clearTimeout(notice._bepusdtTimer);
		notice._bepusdtTimer = window.setTimeout(function () {
			notice.hidden = true;
		}, 3600);
	}

	function hideNotice(container) {
		var notice = container.querySelector('[data-bepusdt-notice]');
		if (!notice) {
			return;
		}

		window.clearTimeout(notice._bepusdtTimer);
		notice.hidden = true;
	}

	function selectMethod(method) {
		if (!method || !method.hasAttribute('data-bepusdt-primary-method')) {
			return;
		}

		var group = method.closest('.bepusdt-wc-method-grid');
		if (!group) {
			return;
		}

		group.querySelectorAll('.bepusdt-wc-method').forEach(function (item) {
			var isSelected = item === method;
			item.classList.toggle('bepusdt-wc-method--active', isSelected);
			item.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
		});
	}

	function resetCheckoutMethods(context) {
		(context || document).querySelectorAll('[data-bepusdt-checkout]').forEach(function (checkout) {
			selectMethod(checkout.querySelector('[data-bepusdt-primary-method]'));
		});
	}

	function keepPrimarySelected(method) {
		var checkout = method.closest('[data-bepusdt-checkout]');
		var primary = checkout ? checkout.querySelector('[data-bepusdt-primary-method]') : method;
		selectMethod(primary);
		window.setTimeout(function () {
			selectMethod(primary);
		}, 0);
	}

	function handleMethodEvent(event) {
		var method = event.target.closest('.bepusdt-wc-method');
		if (!method) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
		if (event.stopImmediatePropagation) {
			event.stopImmediatePropagation();
		}

		var disabledMethod = event.target.closest('[data-bepusdt-disabled-method]');
		if (disabledMethod) {
			var checkout = disabledMethod.closest('[data-bepusdt-checkout]');
			if (checkout) {
				selectMethod(checkout.querySelector('[data-bepusdt-primary-method]'));
			}
			showNotice(checkout || document, settings.unsupportedMessage || 'This payment method is unavailable for the current address. Please choose USDT payment.');
			return;
		}

		keepPrimarySelected(method);
		hideNotice(method.closest('[data-bepusdt-checkout]') || document);
	}

	document.addEventListener('pointerdown', handleMethodEvent, true);
	document.addEventListener('click', handleMethodEvent, true);

	function pollPayment(section) {
		var orderId = section.getAttribute('data-order-id');
		var nonce = section.getAttribute('data-nonce');
		var status = section.querySelector('[data-bepusdt-status]');

		if (!orderId || !nonce || !settings.ajaxUrl) {
			return;
		}

		var body = new URLSearchParams();
		body.set('action', 'bepusdt_wc_check_order');
		body.set('order_id', orderId);
		body.set('nonce', nonce);

		window.fetch(settings.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (json) {
			if (!json || !json.success || !json.data) {
				return;
			}

			if (json.data.is_paid) {
				if (status) {
					status.textContent = settings.paidMessage || 'Payment confirmed. Refreshing order status...';
				}
				window.setTimeout(function () {
					window.location.href = json.data.redirect_url || window.location.href;
				}, 1200);
			}
		}).catch(function () {});
	}

	document.addEventListener('DOMContentLoaded', function () {
		resetCheckoutMethods(document);

		if (window.jQuery && window.jQuery(document.body).on) {
			window.jQuery(document.body).on('updated_checkout updated_wc_div', function () {
				resetCheckoutMethods(document);
			});
		}

		var payment = document.querySelector('[data-bepusdt-payment]');
		if (!payment) {
			return;
		}

		pollPayment(payment);
		window.setInterval(function () {
			pollPayment(payment);
		}, 15000);
	});
}());
