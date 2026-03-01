/**
 * @copyright  (C) 2023 SharkyKZ
 * @license    GPL-3.0-or-later
 */
const captchaKey = Joomla.getOptions('plg_captcha_recaptcha_enterprise.siteKey', '');
const triggerMethod = Joomla.getOptions('plg_captcha_recaptcha_enterprise.triggerMethod', 'focusin');
const apiUrl = Joomla.getOptions('plg_captcha_recaptcha_enterprise.apiUrl', '');
const actionSelector = 'input.plg-captcha-recaptcha-enterprise-action';
const answerSelector = 'input.plg-captcha-recaptcha-enterprise-hidden';
const getAction = form => findAction(form).replace(/[^a-z0-9]+/gi, '_');

/**
 * Lazy-loads the Google reCAPTCHA Enterprise script on first need.
 * Returns a promise that resolves when grecaptcha.enterprise is ready.
 */
let apiLoadPromise = null;
const ensureApi = function () {
	if (apiLoadPromise) {
		return apiLoadPromise;
	}
	apiLoadPromise = new Promise(function (resolve) {
		if (typeof grecaptcha !== 'undefined' && grecaptcha.enterprise) {
			grecaptcha.enterprise.ready(resolve);
			return;
		}
		const script = document.createElement('script');
		script.src = apiUrl;
		script.defer = true;
		script.referrerPolicy = 'no-referrer';
		script.onload = function () {
			grecaptcha.enterprise.ready(resolve);
		};
		document.head.appendChild(script);
	});
	return apiLoadPromise;
};

const findAction = function (form) {
	if (form.hasAttribute('class') && form.getAttribute('class') !== '') {
		let matchClass;
		form.getAttribute('class').split(/\s+/).forEach((className) => {
			if (className.match(/^(com|mod|plg)\-/)) {
				matchClass = className;
			}
		});
		if (matchClass) {
			return matchClass;
		}
	}
	if (form.hasAttribute('id') && form.getAttribute('id') !== '') {
		return form.getAttribute('id');
	}
	if (form.hasAttribute('name') && form.getAttribute('name') !== '') {
		return form.getAttribute('name');
	}
	return 'submit';
}

const handleSubmit = function (submitEvent) {
	submitEvent.preventDefault();
	ensureApi().then(function () {
		const actionElement = submitEvent.target.querySelector(actionSelector);
		actionElement.value = getAction(submitEvent.target);
		grecaptcha.enterprise.execute(captchaKey, {action: actionElement.value}).then(function (token) {
			submitEvent.target.querySelector(answerSelector).value = token;
			submitEvent.target.submit();
		});
	});
}

const handleFocus = function(focusInEvent) {
	ensureApi().then(function () {
		const form = focusInEvent.target.form ?? focusInEvent.target.closest('input, textarea, select, button, fieldset').form;
		const actionElement = form.querySelector(actionSelector);
		actionElement.value = getAction(form);
		const answerElement = form.querySelector(answerSelector);
		grecaptcha.enterprise.execute(captchaKey, {action: actionElement.value}).then(function (token) {
			answerElement.value = token;
			setInterval(handleLoad, 110_000, answerElement);
		});
	});
}

const handleIframeFocus = function(focusInEvent, addedNode) {
	const form = addedNode.closest('input, textarea, select, button, fieldset').form;
	ensureApi().then(function () {
		const actionElement = form.querySelector(actionSelector);
		actionElement.value = getAction(form);
		const answerElement = form.querySelector(answerSelector);
		grecaptcha.enterprise.execute(captchaKey, {action: actionElement.value}).then(function (token) {
			answerElement.value = token;
			setInterval(handleLoad, 110_000, answerElement);
		});
	});
}

const handleLoad = function (element) {
	ensureApi().then(function () {
		const actionElement = element.form.querySelector(actionSelector);
		actionElement.value = getAction(element.form);
		grecaptcha.enterprise.execute(captchaKey, { action: actionElement.value }).then(function (token) {
			element.value = token;
		});
	});
}

const observerConfig = {childList: true, subtree: true};

const observerCallback = (mutations, observer) => {
	for (const mutation of mutations) {
		for (const addedNode of mutation.addedNodes) {
			if (addedNode.nodeType !== Node.ELEMENT_NODE || addedNode.tagName !== 'IFRAME') {
				continue;
			}
			addedNode.contentDocument.addEventListener('focusin', (event) => handleIframeFocus(event, addedNode), {once: true});
		}
	}
};

/**
 * Initialise a captcha field inside the given container element.
 * Called by onDisplay's inline script so that dynamically loaded
 * forms (popups, AJAX) get a token at that point, not on the 
 * initial page load. This is important when the form is configured
 * on inital page load, but might never be called as you don't want
 * the Captcha logo to appear all the time. 
 *
 * @param {HTMLElement} container  The element that wraps the hidden inputs.
 */
const initField = function (container) {
	const answerElement = container.querySelector(answerSelector);
	const actionElement = container.querySelector(actionSelector);

	if (!answerElement || !actionElement) {
		return;
	}

	// Avoid double-initialising if main.js already bound this field at page load.
	if (answerElement.dataset.recaptchaInit === '1') {
		return;
	}
	answerElement.dataset.recaptchaInit = '1';

	const form = answerElement.form;

	if (!form) {
		return;
	}

	if (triggerMethod === 'submit') {
		form.addEventListener('submit', handleSubmit);
		return;
	}

	if (triggerMethod === 'focusin') {
		form.addEventListener('focusin', handleFocus, {once: true});
		const observer = new MutationObserver(observerCallback);
		observer.observe(form, observerConfig);
		return;
	}

	// Default: generate token now and refresh on interval.
	handleLoad(answerElement);
	setInterval(handleLoad, 110_000, answerElement);
};

// Expose globally so onDisplay's inline script can call it.
window.plgRecaptchaEnterpriseInit = initField;

// Initial scan for fields already in the DOM at page load.
for (const element of document.querySelectorAll(answerSelector)) {
	initField(element.parentNode);
};
