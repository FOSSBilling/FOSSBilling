/**
 * FOSSBilling API for JavaScript.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

import { parseDataAttr } from './parse-data-attr.mjs';

/**
 * Tools for the API wrapper.
 */
const FOSSBilling = window.FOSSBilling = window.FOSSBilling || {};

const Tools = {
  /**
   * Constructs the full URL for an API endpoint.
   * If the provided URL is relative, it's resolved against the application's base API URL.
   *
   * @param {string} url The API endpoint (e.g., "guest/system/company") or a full URL.
   * @returns {string} The complete URL for the API call.
   */
  getBaseURL: function (url) {
    if (typeof url !== 'string' || !url.trim()) {
      return `${window.location.origin}/api/`;
    }

    if (url.startsWith('http://') || url.startsWith('https://')) {
      return url;
    }

    if (url.includes('index.php?_url=/api/') || url.includes('?_url=/api/')) {
      return new URL(url, `${window.location.origin}/`).toString();
    }

    const base = `${window.location.origin}/api/`;
    let normalized = url;
    if (normalized.startsWith('/')) {
      normalized = normalized.slice(1);
    }
    if (normalized.startsWith('api/')) {
      normalized = normalized.slice(4);
    }

    return new URL(normalized, base).toString();
  },

  /**
   * @returns {string|null} The CSRF token from cookie, or null if not found.
   */
  getCSRFToken: function () {
    const match = document.cookie.match(/csrf_token=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : null;
  },

  /**
   * Check if a string is valid JSON or not.
   *
   * @param {string} jsonString The string to check.
   * @returns {boolean} True if the string is valid JSON, or false if it is not.
   */
  isJSON: function (jsonString) {
    if (typeof jsonString !== 'string') {
      return false;
    }
    try {
      JSON.parse(jsonString);
      return true;
    } catch (error) {
      return false;
    }
  },

  /**
   * Converts a FormData object into a urlencoded string.
   *
   * @param {FormData} formData The FormData object to serialize.
   * @returns {string} Serialized string of the FormData.
   */
  serializeFormData: function (formData) {
    const params = new URLSearchParams(formData);
    if (!formData.has('CSRFToken')) {
      const token = Tools.getCSRFToken();
      if (token) {
        params.append('CSRFToken', token);
      }
    }

    return params.toString();
  },

  /**
   * Converts a FormData object into a valid object.
   *
   * @param {FormData} formData The FormData object to serialize.
   * @returns {object} The reformatted object.
   */
  serializeFormDataToObject: function (formData) {
    const obj = {};

    for (const [key, value] of formData.entries()) {
      if (key.endsWith('[]')) {
        const plainKey = key.slice(0, -2);
        if (!obj[plainKey]) {
          obj[plainKey] = [];
        }
        obj[plainKey].push(value);
      } else if (Object.prototype.hasOwnProperty.call(obj, key)) {
        obj[key] = value;
      } else {
        obj[key] = value;
      }
    }

    if (!Object.prototype.hasOwnProperty.call(obj, 'CSRFToken')) {
      const token = Tools.getCSRFToken();
      if (token) {
        obj.CSRFToken = token;
      }
    }

    const reformattedObj = {};
    Object.keys(obj).forEach((originalKey) => {
      const parts = originalKey.match(/[^[\]]+/g) || [originalKey];
      let currentContext = reformattedObj;

      for (let i = 0; i < parts.length; i++) {
        const part = parts[i];

        if (i === parts.length - 1) {
          currentContext[part] = obj[originalKey];
        } else {
          if (!Object.prototype.hasOwnProperty.call(currentContext, part) || typeof currentContext[part] !== 'object' || currentContext[part] === null) {
            currentContext[part] = {};
          }

          currentContext = currentContext[part];
        }
      }
    });

    return reformattedObj;
  },

  /**
   * Converts a FormData object into a valid JSON string.
   *
   * @param {FormData} formData The FormData object to serialize.
   * @returns {string} JSON string of the FormData object.
   */
  serializeFormDataToJSON: function (formData) {
    return JSON.stringify(this.serializeFormDataToObject(formData));
  }
};

Tools.parseDataAttr = parseDataAttr;

/**
 * Creates an API for a specific role (admin, client, guest).
 *
 * @param {string} role The role for the API (admin, client, guest).
 * @returns {object} The API object for the specified role.
 **/
function _createApiRole (role) {
  const baseNamespaceUrlString = Tools.getBaseURL(role);

  const createMethod = (method) => {
    return function(endpoint, params, successHandler, errorHandler, enableLoader = true) {
      if (typeof endpoint !== 'string' || !endpoint.trim()) {
        throw new Error('Invalid endpoint: must be a non-empty string');
      }
      const requestUrl = new URL(endpoint, `${baseNamespaceUrlString}/`).toString();
      API.makeRequest(method, requestUrl, params, successHandler, errorHandler, enableLoader);
    };
  };

  return {
    baseURL: baseNamespaceUrlString,
    get: createMethod('GET'),
    post: createMethod('POST'),
    put: createMethod('PUT'),
    delete: createMethod('DELETE'),
    patch: createMethod('PATCH')
  };
}

/**
 * FOSSBilling API wrapper for JavaScript.
 * @documentation https://docs.fossbilling.org/extensions-and-development/javascript/
 */
const API = {
  /**
   * Wrapper for the admin API.
   * @documentation https://docs.fossbilling.org/extensions-and-development/javascript/
   */
  admin: _createApiRole('admin'),

  /**
   * Wrapper for the client API.
   * @documentation https://docs.fossbilling.org/extensions-and-development/javascript/
   */
  client: _createApiRole('client'),

  /**
   * Wrapper for the guest API.
   * @documentation https://docs.fossbilling.org/extensions-and-development/javascript/
   */
  guest: _createApiRole('guest'),

  /**
   * Make a request to the API.
   *
   * @param {string} method The HTTP method to use.
   * @param {string} url The URL to call.
   * @param {object|string} [params] The parameters to send.
   * @param {function} [successHandler] The function to call if the request is successful.
   * @param {function} [errorHandler] The function to call if the request is unsuccessful.
   * @param {boolean} [enableLoader=true] Enable or disable the usage of a loader. Custom themes simply need to provide one with the spinner-border class.
   * @param {number} [timeoutMs=30000] Timeout duration in milliseconds.
   * @param {string|null} [timeoutMessage=null] Message to show when the request times out.
   * @documentation https://docs.fossbilling.org/extensions-and-development/javascript/
   */
  makeRequest: function (method, url, params, successHandler, errorHandler, enableLoader = true, timeoutMs = 30000, timeoutMessage = null) {
    let loader = enableLoader ? this._createLoader() : null;

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
    const parseResponseBody = async (response) => {
      if (response.status === 204) {
        return { payload: null, rawText: '' };
      }

      const text = await response.text();
      if (!text) {
        return { payload: null, rawText: '' };
      }

      try {
        return { payload: JSON.parse(text), rawText: text };
      } catch (error) {
        return { payload: null, rawText: text };
      }
    };

    url = new URL(url);
    const isFormData = params instanceof FormData;

    if (isFormData) {
      if (!params.has('CSRFToken')) {
        params.append('CSRFToken', Tools.getCSRFToken());
      }
    } else if (params && typeof params === 'object') {
      if (!params.CSRFToken) {
        params.CSRFToken = Tools.getCSRFToken();
      }
    }

    let body = null;
    const methodLower = method.toLowerCase();
    if (methodLower === 'get') {
      if (isFormData) {
        for (const [key, value] of params.entries()) {
          url.searchParams.append(key, value);
        }
      } else if (params && typeof params === 'object') {
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
      } else if (params) {
        url.search = params;
      }
    } else if (['post', 'put', 'patch', 'delete'].includes(methodLower)) {
      if (isFormData) {
        body = params;
      } else if (typeof params === 'string') {
        body = params;
      } else {
        body = JSON.stringify(params);
      }
    }

    const headers = {
      'Accept': 'application/json',
      'X-CSRF-Token': Tools.getCSRFToken() || '',
    };
    if (url.origin === window.location.origin) {
      headers['X-Requested-With'] = 'XMLHttpRequest';
    }
    if (body && !isFormData) {
      headers['Content-Type'] = 'application/json';
    }

    const fetchOptions = {
      method: method,
      headers: headers,
      signal: controller.signal
    };
    if (methodLower !== 'get') {
      fetchOptions.body = body;
    }

    return fetch(url.toString(), fetchOptions)
      .then(async (response) => {
        clearTimeout(timeoutId);

        if (response.redirected) {
          window.location.replace(response.url);
          return;
        }

        const { payload, rawText } = await parseResponseBody(response);

        if (!response.ok) {
          const error = new Error(payload?.error?.message || `HTTP error ${response.status}: ${response.statusText}`);
          error.code = payload?.error?.code || `http_${response.status}`;
          error.status = response.status;
          error.rawBody = rawText;
          throw error;
        }

        if (rawText && payload === null) {
          throw new Error('Invalid or non-JSON response from server');
        }

        return payload;
      })
      .then((response) => {
        if (!response) {
          if (typeof successHandler === 'function') {
            successHandler(null);
          }

          return null;
        }

        if (response.error) {
          const error = new Error(response.error.message || 'Unknown API error');
          error.code = response.error.code;
          throw error;
        }

        if (typeof successHandler === 'function') {
          successHandler(response.result);
        }

        return response.result;
      })
      .catch((error) => {
        clearTimeout(timeoutId);

        let errorObj;
        if (error.name === 'AbortError') {
          errorObj = {
            message: timeoutMessage || `Request timed out after ${timeoutMs / 1000} seconds`,
            code: 'timeout_error'
          };
        } else if (error.name === 'TypeError' && error.message.includes('NetworkError')) {
          errorObj = {
            message: 'Network connection error',
            code: 'network_error'
          };
        } else {
          errorObj = {
            message: error.message || 'Unknown error occurred',
            code: error.code || 'unknown_error'
          };
        }

        console.error(`API Error: ${errorObj.message}`);

        if (typeof errorHandler === 'function') {
          errorHandler(errorObj);
        } else {
          console.warn('No error handler was specified for API error.');
          const normalizedError = new Error(errorObj.message);
          normalizedError.code = errorObj.code;
          throw normalizedError;
        }
      })
      .finally(() => {
        if (enableLoader && loader) {
          if (loader._fadeInTimeout) {
            clearTimeout(loader._fadeInTimeout);
          }
          if (document.body.contains(loader)) {
            document.body.removeChild(loader);
          }
          loader = null;
        }
      });
  },

  /**
   * After the API request is complete, this function will be called.
   *
   * @param {object} object The HTML element that triggered the API call.
   * @param {*} result The result of the API call.
   * @returns
   */
  _afterComplete: function (object, result) {
    let apiData;
    try {
      apiData = Tools.parseDataAttr(object.dataset.fbApi || '{}');
    } catch (error) {
      console.warn('Invalid JSON in data-fb-api attribute:', error);
      return;
    }

    if (apiData.hasOwnProperty('callback') && typeof window[apiData.callback] === 'function') {
      return window[apiData.callback](result);
    } else if (apiData.hasOwnProperty('callback')) {
      console.warn('Invalid callback function:', apiData.callback);
    }

    if (apiData.hasOwnProperty('redirect')) {
      const redirectUrl = new URL(apiData.redirect, window.location.href);

      if (redirectUrl.href === window.location.href) {
        window.location.reload();
      } else {
        window.location = redirectUrl.href;
      }

      return;
    }

    if (apiData.hasOwnProperty('reload')) {
      window.location.reload();
      return;
    }

    if (apiData.hasOwnProperty('message')) {
      FOSSBilling.ui.notify(apiData.message, "success");
      return;
    }

    if (result) {
      FOSSBilling.ui.notify("Form Updated", "success");
      return;
    }

    console.warn('Unhandled API response in _afterComplete:', apiData);
  },

  /**
   * Creates a loader element and appends it to the document body.
   *
   * @returns {HTMLElement} The created loader element.
   */
  _createLoader: function() {
    const loader = document.createElement('div');
    loader.classList.add('spinner-border');
    loader.setAttribute('role', 'status');
    Object.assign(loader.style, {
      width: '4rem',
      height: '4rem',
      left: '50%',
      top: '50%',
      position: 'fixed',
      opacity: '0',
      transition: 'opacity 250ms'
    });
    document.body.appendChild(loader);
    loader._fadeInTimeout = setTimeout(() => { loader.style.opacity = '1'; }, 250);
    return loader;
  },

  /**
   * Attach event listeners to forms with data attribute 'data-fb-api'.
   **/
  _apiForm: function () {
    const formElements = document.querySelectorAll('form[data-fb-api]');

    if (formElements.length > 0) {
      formElements.forEach(formElement => {
        if (formElement.dataset.fbApiBound === 'true') {
          return;
        }

        formElement.dataset.fbApiBound = 'true';
        formElement.addEventListener('submit', function (event) {
          event.preventDefault();

          const formData = new FormData(formElement);

          if (FOSSBilling.editor) {
            if (!FOSSBilling.editor.validateForm(formElement)) {
              return FOSSBilling.ui.notify('At least one of the required fields are empty.', 'error');
            }

            FOSSBilling.editor.syncForm(formElement, formData);
          }

          const formMethod = (formElement.getAttribute('method') || 'post').toLowerCase();
          const data = formMethod !== 'get'
            ? Tools.serializeFormDataToJSON(formData)
            : Tools.serializeFormData(formData);

          const buttons = formElement.querySelectorAll('button:not([disabled])');
          const toggleButtons = (disable) => {
            buttons.forEach(button => button.disabled = disable);
          };
          toggleButtons(true);

          const action = formElement.getAttribute('action');
          if (!action) {
            toggleButtons(false);
            console.warn('Missing form action attribute. Skipping API call.');
            return;
          }

          API.makeRequest(
            formMethod,
            Tools.getBaseURL(action),
            data,
            (result) => {
              toggleButtons(false);
              API._afterComplete(formElement, result);
              return result;
            },
            (error) => {
              toggleButtons(false);
              FOSSBilling.ui.notify(`${error.message} (${error.code})`, 'error');
            }
          );
        });
      });
    }
  },

  /**
   * Attach event listeners to links with data attribute 'data-fb-api'.
   **/
  _apiLink: function () {
    const linkElements = document.querySelectorAll('a[data-fb-api]');

    if (linkElements.length > 0) {
      linkElements.forEach(linkElement => {
        if (linkElement.dataset.fbApiBound === 'true') {
          return;
        }

        linkElement.dataset.fbApiBound = 'true';
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

        const setLoadingState = (apiData) => {
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

        const resetLoadingState = () => {
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

        linkElement.addEventListener('click', function (event) {
          event.preventDefault();

          if (requestInProgress) {
            return;
          }

          if (linkElement.classList.contains('disabled') || linkElement.getAttribute('aria-disabled') === 'true') {
            return;
          }

          let apiData;
          try {
            apiData = Tools.parseDataAttr(linkElement.dataset.fbApi || '{}');
          } catch (error) {
            console.error('Failed to parse data-fb-api attribute:', error);
            FOSSBilling.ui.notify('Invalid API configuration', 'error');
            return;
          }

          const rawHref = linkElement.getAttribute('href') || '';
          if (!apiData.href && (!rawHref || rawHref === '#')) {
            return;
          }

          const handleApiRequest = (method, href, params = {}) => {
            if (apiData.loading || apiData.preventNavigation) {
              setLoadingState(apiData);
            }

            const url = apiData.href || href;
            const mergedParams = apiData.params && typeof apiData.params === 'object'
              ? Object.assign({}, apiData.params, params)
              : params;
            API.makeRequest(method, Tools.getBaseURL(url), mergedParams,
              (result) => {
                resetLoadingState();
                API._afterComplete(linkElement, result);
              },
              (error) => {
                resetLoadingState();
                FOSSBilling.ui.notify(`${error.message} (${error.code})`, 'error');
              },
              true,
              apiData.timeoutMs ?? 30000,
              apiData.timeoutMessage ?? null
            );
          };

          if (apiData.hasOwnProperty('modal')) {
            if (typeof Modals === 'undefined' || typeof Modals.create !== 'function') {
              if (apiData.modal.type === 'prompt') {
                const value = window.prompt(apiData.modal.label ?? apiData.modal.title ?? '', apiData.modal.value ?? '');
                if (value) {
                  const p = {};
                  p[apiData.modal.key] = value;
                  handleApiRequest('GET', linkElement.getAttribute('href'), p);
                }
              } else if (window.confirm(apiData.modal.content || apiData.modal.title || 'Are you sure?')) {
                handleApiRequest('GET', linkElement.getAttribute('href'));
              }
            } else if (apiData.modal.type === 'prompt') {
              Modals.create({
                type: apiData.modal.type,
                title: apiData.modal.title,
                label: apiData.modal.label ?? 'Label',
                value: apiData.modal.value ?? '',
                promptConfirmCallback: (value) => {
                  if (value) {
                    const p = {};
                    const name = apiData.modal.key;
                    p[name] = value;
                    handleApiRequest('GET', linkElement.getAttribute('href'), p);
                  }
                },
              });
            } else {
              Modals.create({
                type: (apiData.modal.type === 'confirm') ? 'small-confirm' : apiData.modal.type,
                title: apiData.modal.title,
                content: apiData.modal.content ?? '',
                confirmButton: apiData.modal.button ?? 'Confirm',
                confirmButtonColor: apiData.modal.buttonColor ?? 'primary',
                confirmCallback: () => {
                  handleApiRequest('GET', linkElement.getAttribute('href'));
                },
              });
            }
          } else {
            handleApiRequest('GET', linkElement.getAttribute('href'));
          }
        });
      });
    }
  }
};

window.FOSSBilling = window.FOSSBilling || {};
window.FOSSBilling.tools = Tools;
window.FOSSBilling.api = API;

const bindApiInteractions = () => {
  if (document.querySelector('form[data-fb-api]')) {
    API._apiForm();
  }

  if (document.querySelector('a[data-fb-api]')) {
    API._apiLink();
  }
};

if (typeof FOSSBilling.ready === 'function') {
  FOSSBilling.ready(bindApiInteractions);
} else if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bindApiInteractions);
} else {
  bindApiInteractions();
}
