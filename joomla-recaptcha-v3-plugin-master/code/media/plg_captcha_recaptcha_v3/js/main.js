/**
 * @copyright  (C) 2023 SharkyKZ
 * @license    GPL-3.0-or-later
 */
const captchaKey = Joomla.getOptions('plg_captcha_recaptcha_v3.siteKey', '');
const triggerMethod = Joomla.getOptions('plg_captcha_recaptcha_v3.triggerMethod', 'focusin');
const actionSelector = 'input.plg-captcha-recaptcha-v3-action';
const answerSelector = 'input.plg-captcha-recaptcha-v3-hidden';
const getAction = form => findAction(form).replace(/[^a-z0-9]+/gi, '_');
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
	grecaptcha.enterprise.ready(function () {
		const actionElement = submitEvent.target.querySelector(actionSelector);
		actionElement.value = getAction(submitEvent.target);
		grecaptcha.enterprise.execute(captchaKey, {action: actionElement.value}).then(function (token) {
			submitEvent.target.querySelector(answerSelector).value = token;
			submitEvent.target.submit();
		});
	});
}

const handleFocus = function(focusInEvent) {
	grecaptcha.enterprise.ready(function () {
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
	grecaptcha.enterprise.ready(function () {
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
	grecaptcha.enterprise.ready(function () {
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
 * forms (popups, AJAX) get a token immediately.
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
window.plgRecaptchaV3Init = initField;

// Initial scan for fields already in the DOM at page load.
for (const element of document.querySelectorAll(answerSelector)) {
	initField(element.parentNode);
};
