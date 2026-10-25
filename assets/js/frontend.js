(function () {
	'use strict';

	var settings = window.bepusdtWc || {};
	var iconSyncQueued = false;

	function noticeElement(container) {
		var notice = container.querySelector('[data-bepusdt-notice]');
		if (!notice) {
			return null;
		}

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
		syncPaymentIconSizes(context || document);
	}

	function median(values) {
		values.sort(function (a, b) { return a - b; });
		return values.length ? values[Math.floor(values.length / 2)] : 0;
	}

	function syncPaymentIconSizes(context) {
		(context || document).querySelectorAll('[data-bepusdt-checkout], [data-bepusdt-block-icon]').forEach(function (container) {
			var image;
			var scope;
			var heights = [];
			var targetHeight;

			if (container.getAttribute('data-icon-auto') !== 'yes' || container.getAttribute('data-icon-height') || container.getAttribute('data-icon-width')) {
				return;
			}

			image = container.querySelector('.bepusdt-wc-method--image img, .bepusdt-wc-block-icon');
			if (!image) {
				return;
			}

			scope = container.closest('.wc_payment_methods, .payment_methods, .wc-block-components-radio-control') || document;
			scope.querySelectorAll('label img, .wc-block-components-radio-control__label-group img').forEach(function (candidate) {
				var rect;
				if (candidate === image || container.contains(candidate)) {
					return;
				}
				rect = candidate.getBoundingClientRect();
				if (rect.height >= 12 && rect.height <= 80 && rect.width >= 12) {
					heights.push(rect.height);
				}
			});

			targetHeight = median(heights);
			if (targetHeight) {
				targetHeight = Math.max(18, Math.min(48, Math.round(targetHeight * 100) / 100));
				if (container.hasAttribute('data-bepusdt-checkout')) {
					container.style.setProperty('--bepusdt-icon-height', targetHeight + 'px');
				} else {
					image.style.height = targetHeight + 'px';
					if (!container.getAttribute('data-icon-width')) {
						image.style.width = 'auto';
					}
				}
			}
		});
	}

	function queuePaymentIconSizeSync() {
		if (iconSyncQueued) {
			return;
		}
		iconSyncQueued = true;
		window.requestAnimationFrame(function () {
			iconSyncQueued = false;
			syncPaymentIconSizes(document);
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

	function initializePaymentCardLayout(section) {
		var container = section.closest('.woocommerce-order') || section.closest('.woocommerce');
		var anchor;
		var reference;
		var selector = '.woocommerce-order-overview, .woocommerce-order-details, .woocommerce-customer-details, .wc-block-order-confirmation-summary, .wp-block-woocommerce-order-confirmation-summary, .wp-block-woocommerce-order-confirmation-totals-wrapper';

		function sync() {
			var bestWidth = 0;
			var parentRect;
			var parentStyle;
			var contentLeft;
			var referenceRect;

			reference = null;
			container.querySelectorAll(selector).forEach(function (candidate) {
				var width = section.contains(candidate) ? 0 : candidate.getBoundingClientRect().width;
				if (width > bestWidth) {
					reference = candidate;
					bestWidth = width;
				}
			});

			if (!reference) {
				Array.prototype.forEach.call(container.children, function (candidate) {
					var rect = candidate === section ? { width: 0 } : candidate.getBoundingClientRect();
					if (rect.width > bestWidth) {
						reference = candidate;
						bestWidth = rect.width;
					}
				});
			}

			if (!reference) {
				section.style.removeProperty('--bepusdt-payment-card-width');
				section.style.removeProperty('--bepusdt-payment-card-offset');
				return;
			}

			parentRect = section.parentElement.getBoundingClientRect();
			parentStyle = window.getComputedStyle(section.parentElement);
			contentLeft = parentRect.left + parseFloat(parentStyle.borderLeftWidth || 0) + parseFloat(parentStyle.paddingLeft || 0);
			referenceRect = reference.getBoundingClientRect();
			section.style.setProperty('--bepusdt-payment-card-width', Math.round(referenceRect.width * 100) / 100 + 'px');
			section.style.setProperty('--bepusdt-payment-card-offset', Math.max(0, Math.round((referenceRect.left - contentLeft) * 100) / 100) + 'px');
		}

		if (!container) {
			return;
		}

		if (section.parentElement !== container) {
			anchor = container.querySelector('.woocommerce-order-details, .woocommerce-customer-details');
			while (anchor && anchor.parentElement !== container) {
				anchor = anchor.parentElement;
			}
			container.insertBefore(section, anchor);
		}

		sync();
		window.requestAnimationFrame(sync);
		window.addEventListener('resize', sync, { passive: true });
		if (window.ResizeObserver) {
			section._bepusdtResizeObserver = new window.ResizeObserver(sync);
			section._bepusdtResizeObserver.observe(container);
			container.querySelectorAll(selector).forEach(function (candidate) {
				section._bepusdtResizeObserver.observe(candidate);
			});
		}
	}

	document.addEventListener('pointerdown', handleMethodEvent, true);
	document.addEventListener('click', handleMethodEvent, true);

	function pollPayment(section) {
		var orderId = section.getAttribute('data-order-id');
		var orderKey = section.getAttribute('data-order-key');
		var nonce = section.getAttribute('data-nonce');
		var status = section.querySelector('[data-bepusdt-status]');

		if (!orderId || !orderKey || !nonce || !settings.ajaxUrl) {
			return Promise.resolve(null);
		}

		var body = new URLSearchParams();
		body.set('action', 'bepusdt_wc_check_order');
		body.set('order_id', orderId);
		body.set('order_key', orderKey);
		body.set('nonce', nonce);

		return window.fetch(settings.ajaxUrl, {
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
				return null;
			}

			if (json.data.is_paid) {
				if (status && settings.paidMessage) {
					status.textContent = settings.paidMessage;
				}
				window.setTimeout(function () {
					window.location.href = json.data.redirect_url || window.location.href;
				}, 1200);
			} else if (json.data.status === 'failed' && status && settings.expiredMessage) {
				status.textContent = settings.expiredMessage;
			}

			return json.data;
		}).catch(function () { return null; });
	}

	function startPaymentPolling(section) {
		var delay = 15000;
		var stopped = false;
		var timer = null;

		function schedule() {
			if (stopped) {
				return;
			}
			window.clearTimeout(timer);
			timer = window.setTimeout(run, document.hidden ? 60000 : delay);
		}

		function run() {
			if (document.hidden) {
				schedule();
				return;
			}

			pollPayment(section).then(function (data) {
				if (data && (data.is_paid || data.should_poll === false)) {
					stopped = true;
					return;
				}
				delay = Math.min(30000, Math.round(delay * 1.5));
				schedule();
			});
		}

		document.addEventListener('visibilitychange', function () {
			if (!document.hidden && !stopped) {
				window.clearTimeout(timer);
				run();
			}
		});

		run();
	}

	document.addEventListener('DOMContentLoaded', function () {
		resetCheckoutMethods(document);

		if (window.jQuery && window.jQuery(document.body).on) {
			window.jQuery(document.body).on('updated_checkout updated_wc_div', function () {
				resetCheckoutMethods(document);
			});
		}

		if (window.MutationObserver) {
			new window.MutationObserver(function () {
				queuePaymentIconSizeSync();
			}).observe(document.body, { childList: true, subtree: true });
		}

		var payment = document.querySelector('[data-bepusdt-payment]');
		if (!payment) {
			return;
		}

		initializePaymentCardLayout(payment);
		startPaymentPolling(payment);
	});
}());
