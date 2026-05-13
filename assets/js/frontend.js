(function () {
	'use strict';

	var settings = window.bepusdtWc || {};

	function noticeElement(container) {
		var notice = container.querySelector('[data-bepusdt-notice]');
		if (!notice) {
			return null;
		}

		window.clearTimeout(notice._bepusdtTimer);
		notice.hidden = false;
		return notice;
	}

	function hasGuideNotice() {
		return typeof settings.guideHtml === 'string' && settings.guideHtml.trim() !== '';
	}

	function hideNotice(container) {
		var notice = container.querySelector('[data-bepusdt-notice]');
		if (!notice) {
			return;
		}

		window.clearTimeout(notice._bepusdtTimer);
		notice.textContent = '';
		notice.hidden = true;
	}

	function showGuideNotice(container) {
		if (!hasGuideNotice()) {
			hideNotice(container);
			return;
		}

		var notice = noticeElement(container);
		if (!notice) {
			return;
		}

		notice.innerHTML = settings.guideHtml;
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
			var primary = checkout.querySelector('[data-bepusdt-primary-method]');
			selectMethod(primary);
			if (primary) {
				showGuideNotice(checkout);
			} else {
				hideNotice(checkout);
			}
		});
	}

	function keepPrimarySelected(checkout) {
		var primary = checkout ? checkout.querySelector('[data-bepusdt-primary-method]') : null;
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

		var checkout = method.closest('[data-bepusdt-checkout]');
		keepPrimarySelected(checkout);
		showGuideNotice(checkout || document);
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
