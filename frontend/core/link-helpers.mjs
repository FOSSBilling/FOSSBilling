/**
 * Extracted helpers for data-fb-api link interactions.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Dispatch a link click through the appropriate modal flow (or directly) and
 * invoke `onRequest` when the user confirms.
 *
 * @param {object}      apiData     Parsed data-fb-api configuration.
 * @param {string}      rawHref     The link element's href attribute value.
 * @param {object|null} modalsLib   The global Modals library (or null when unavailable).
 * @param {function}    onRequest   Callback: (method, href, params?) => void.
 */
export function dispatchLinkAction(apiData, rawHref, modalsLib, onRequest) {
  if (!apiData.hasOwnProperty('modal')) {
    onRequest('GET', rawHref);
    return;
  }

  const modal = apiData.modal;

  if (!modalsLib || typeof modalsLib.create !== 'function') {
    if (modal.type === 'prompt') {
      const value = window.prompt(modal.label ?? modal.title ?? '', modal.value ?? '');
      if (value) {
        onRequest('GET', rawHref, { [modal.key]: value });
      }
    } else if (window.confirm(modal.content || modal.title || 'Are you sure?')) {
      onRequest('GET', rawHref);
    }
  } else if (modal.type === 'prompt') {
    modalsLib.create({
      type: modal.type,
      title: modal.title,
      label: modal.label ?? 'Label',
      value: modal.value ?? '',
      promptConfirmCallback: (value) => {
        if (value) {
          onRequest('GET', rawHref, { [modal.key]: value });
        }
      },
    });
  } else {
    modalsLib.create({
      type: modal.type === 'confirm' ? 'small-confirm' : modal.type,
      title: modal.title,
      content: modal.content ?? '',
      confirmButton: modal.button ?? 'Confirm',
      confirmButtonColor: modal.buttonColor ?? 'primary',
      confirmCallback: () => {
        onRequest('GET', rawHref);
      },
    });
  }
}

/**
 * Create a loading-state manager for a single link element.
 *
 * Tracks original DOM state, applies loading visual feedback (spinner button,
 * loading message, aria attributes), and restores everything on `reset()`.
 *
 * @param {HTMLElement} linkElement  The `<a>` element to manage.
 * @returns {{set: Function, reset: Function, isInProgress: Function}}
 */
export function createLinkLoadingState(linkElement) {
  let requestInProgress = false;
  let loadingAlert = null;
  let beforeUnloadHandler = null;
  let originalHtml = null;
  let originalAriaBusy = null;
  let originalAriaDisabled = null;
  let originallyDisabled = false;

  const getLoadingTarget = (selector) => {
    if (selector) {
      try {
        const target = document.querySelector(selector);
        if (target) {
          return target;
        }
      } catch (error) {
        console.warn('Invalid loading target selector:', selector);
      }
    }

    return linkElement.closest('.card-footer') || linkElement.parentElement;
  };

  const set = (apiData) => {
    requestInProgress = true;
    originalHtml = linkElement.innerHTML;
    originalAriaBusy = linkElement.getAttribute('aria-busy');
    originalAriaDisabled = linkElement.getAttribute('aria-disabled');
    originallyDisabled = linkElement.classList.contains('disabled');

    linkElement.setAttribute('aria-busy', 'true');
    linkElement.setAttribute('aria-disabled', 'true');
    linkElement.classList.add('disabled');

    if (apiData.loading?.button) {
      const spinner = document.createElement('span');
      spinner.className = 'spinner-border spinner-border-sm me-2';
      spinner.setAttribute('aria-hidden', 'true');

      linkElement.replaceChildren(spinner, document.createTextNode(apiData.loading.button));
    }

    if (apiData.loading?.message) {
      const target = getLoadingTarget(apiData.loading.target);
      if (target) {
        loadingAlert = document.createElement('div');
        loadingAlert.className = apiData.loading.alertClass || 'alert alert-info mt-3 mb-0';
        loadingAlert.setAttribute('role', 'status');
        loadingAlert.textContent = apiData.loading.message;
        target.appendChild(loadingAlert);
      }
    }

    if (apiData.preventNavigation) {
      beforeUnloadHandler = (event) => {
        event.preventDefault();
        event.returnValue = '';
      };
      window.addEventListener('beforeunload', beforeUnloadHandler);
    }
  };

  const reset = () => {
    if (!requestInProgress) {
      return;
    }

    requestInProgress = false;

    if (originalHtml !== null) {
      linkElement.innerHTML = originalHtml;
    }
    if (originalAriaBusy === null) {
      linkElement.removeAttribute('aria-busy');
    } else {
      linkElement.setAttribute('aria-busy', originalAriaBusy);
    }
    if (originalAriaDisabled === null) {
      linkElement.removeAttribute('aria-disabled');
    } else {
      linkElement.setAttribute('aria-disabled', originalAriaDisabled);
    }
    if (!originallyDisabled) {
      linkElement.classList.remove('disabled');
    }

    if (loadingAlert) {
      loadingAlert.remove();
      loadingAlert = null;
    }

    if (beforeUnloadHandler) {
      window.removeEventListener('beforeunload', beforeUnloadHandler);
      beforeUnloadHandler = null;
    }
  };

  return {
    set,
    reset,
    isInProgress: () => requestInProgress,
  };
}
