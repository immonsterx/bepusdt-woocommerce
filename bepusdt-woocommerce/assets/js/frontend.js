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

	function showNotice(container, message) {
		var notice = noticeElement(container);
		if (!notice) {
			return;
		}

		notice.textContent = message;
	}

	function showGuideNotice(container) {
		var notice = noticeElement(container);
		if (!notice) {
			return;
		}

		var link = document.createElement('a');
		link.href = settings.guideUrl || '/usdt-payment-guide-buy-and-withdraw/';
		link.target = '_blank';
		link.rel = 'noopener';

		var prefix = document.createElement('span');
		prefix.textContent = '👉 查看 ';

		var label = document.createElement('span');
		label.className = 'bepusdt-wc-notice-link-text';
		label.textContent = (settings.guideLabel || '查看 USDT 支付教學').replace(/^查看\s*/, '');

		link.appendChild(prefix);
		link.appendChild(label);

		notice.textContent = '';
		notice.appendChild(link);
	}

	function disabledMethodMessage(method) {
		var brand = method.getAttribute('data-bepusdt-method-name') || method.getAttribute('aria-label') || '';
		var template = settings.unsupportedTemplate || '當前地址無法使用 %s 支付，請選擇 USDT 支付。';

		if (!brand) {
			return settings.unsupportedMessage || '當前地址無法使用此支付方式，請選擇 USDT 支付。';
		}

		return template.replace('%s', brand);
	}

	function restoreGuideNotice(container) {
		var notice = container.querySelector('[data-bepusdt-notice]');
		if (!notice) {
			return;
		}

		window.clearTimeout(notice._bepusdtTimer);
		notice._bepusdtTimer = window.setTimeout(function () {
			selectMethod(container.querySelector('[data-bepusdt-primary-method]'));
			showGuideNotice(container);
		}, 3600);
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
			showGuideNotice(checkout);
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
				showNotice(checkout, disabledMethodMessage(disabledMethod));
				restoreGuideNotice(checkout);
				return;
			}
			showNotice(document, disabledMethodMessage(disabledMethod));
			return;
		}

		keepPrimarySelected(method);
		showGuideNotice(method.closest('[data-bepusdt-checkout]') || document);
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
