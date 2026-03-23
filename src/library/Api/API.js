/**
 * FOSSBilling API for JavaScript.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Tools for the API wrapper.
 */
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
   * Parses the data attribute value from a DOM element and validates known fields.
   *
   * @param {string} dataAttrValue The value of the data attribute to parse.
   * @returns {object} The parsed and validated data.
   * @throws {Error} If the data attribute value is invalid or does not match the schema.
   **/
  parseDataAttr: function (dataAttrValue) {
    if (!dataAttrValue) {
      return {};
    }

    let data;
    try {
      data = JSON.parse(dataAttrValue);
    } catch (error) {
      throw new Error('Invalid JSON in data-fb-api attribute.');
    }

    if (typeof data !== 'object' || data === null || Array.isArray(data)) {
      throw new Error('data-fb-api must be a JSON object.');
    }

    const assertString = (value, key) => {
      if (typeof value !== 'string') {
        throw new Error(`data-fb-api.${key} must be a string.`);
      }
    };

    const assertBoolean = (value, key) => {
      if (typeof value !== 'boolean') {
        throw new Error(`data-fb-api.${key} must be a boolean.`);
      }
    };

    if (Object.prototype.hasOwnProperty.call(data, 'href')) {
      assertString(data.href, 'href');
    }
    if (Object.prototype.hasOwnProperty.call(data, 'type')) {
      assertString(data.type, 'type');
    }
    if (Object.prototype.hasOwnProperty.call(data, 'endpoint')) {
      assertString(data.endpoint, 'endpoint');
    }
    if (Object.prototype.hasOwnProperty.call(data, 'callback')) {
      assertString(data.callback, 'callback');
    }
    if (Object.prototype.hasOwnProperty.call(data, 'message')) {
      assertString(data.message, 'message');
    }
    if (Object.prototype.hasOwnProperty.call(data, 'redirect')) {
      assertString(data.redirect, 'redirect');
    }
    if (Object.prototype.hasOwnProperty.call(data, 'reload')) {
      assertBoolean(data.reload, 'reload');
    }
    if (Object.prototype.hasOwnProperty.call(data, 'params')) {
      if (typeof data.params !== 'object' || data.params === null || Array.isArray(data.params)) {
        throw new Error('data-fb-api.params must be an object.');
      }
    }

    if (Object.prototype.hasOwnProperty.call(data, 'modal')) {
      const modal = data.modal;
      if (typeof modal !== 'object' || modal === null || Array.isArray(modal)) {
        throw new Error('data-fb-api.modal must be an object.');
      }
      if (typeof modal.type !== 'string') {
        throw new Error('data-fb-api.modal.type must be a string.');
      }

      const allowedTypes = ['confirm', 'danger', 'prompt'];
      if (!allowedTypes.includes(modal.type)) {
        throw new Error(`data-fb-api.modal.type must be one of: ${allowedTypes.join(', ')}.`);
      }

      if (modal.type === 'prompt' && typeof modal.key !== 'string') {
        throw new Error('data-fb-api.modal.key is required for prompt modals.');
      }

      const modalStringFields = ['title', 'content', 'button', 'buttonColor', 'label', 'value', 'key'];
      modalStringFields.forEach((field) => {
        if (Object.prototype.hasOwnProperty.call(modal, field)) {
          assertString(modal[field], `modal.${field}`);
        }
      });
    }

    return data;
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
        if (!Array.isArray(obj[key])) {
          obj[key] = [obj[key]];
        }
        obj[key].push(value);
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
 * @documentation https://fossbilling.org/docs/api/javascript
 */
const API = {
  /**
   * Wrapper for the admin API.
   * @documentation https://fossbilling.org/docs/api/javascript
   */
  admin: _createApiRole('admin'),

  /**
   * Wrapper for the client API.
   * @documentation https://fossbilling.org/docs/api/javascript
   */
  client: _createApiRole('client'),

  /**
   * Wrapper for the guest API.
   * @documentation https://fossbilling.org/docs/api/javascript
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
   * @documentation https://fossbilling.org/docs/api/javascript
   */
  makeRequest: function (method, url, params, successHandler, errorHandler, enableLoader = true, timeoutMs = 30000) {
    let loader = enableLoader ? this._createLoader() : null;

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

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
      } else {
        if (!Tools.isJSON(params)) {
          params = JSON.stringify(params);
        }
        body = params;
      }
    }

    const headers = {
      'Accept': 'application/json',
      'X-CSRF-Token': Tools.getCSRFToken() || '',
    };
    if (body && !isFormData) {
      headers['Content-Type'] = 'application/json';
    }

    return fetch(url.toString(), {
      method: method,
      headers: headers,
      body: body,
      signal: controller.signal
    })
      .then((response) => {
        clearTimeout(timeoutId);

        if (response.redirected) {
          window.location.replace(response.url);
          return;
        }

        if (!response.ok) {
          throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
        }

        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
          return response.json().catch(() => {
            throw new Error('Invalid JSON response from server');
          });
        } else {
          return response.text().then(text => {
            try {
              return JSON.parse(text);
            } catch (e) {
              throw new Error('Invalid or non-JSON response from server');
            }
          });
        }
      })
      .then((response) => {
        if (enableLoader && loader) {
          if (loader._fadeInTimeout) {
            clearTimeout(loader._fadeInTimeout);
          }
          document.body.removeChild(loader);
          loader = null;
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

        if (enableLoader && loader) {
          if (loader._fadeInTimeout) {
            clearTimeout(loader._fadeInTimeout);
          }
          document.body.removeChild(loader);
          loader = null;
        }

        let errorObj;
        if (error.name === 'AbortError') {
          errorObj = {
            message: 'Request timed out after ' + (timeoutMs / 1000) + ' seconds',
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
      window.location = apiData.redirect;
      return;
    }

    if (apiData.hasOwnProperty('reload')) {
      window.location.reload();
      return;
    }

    if (apiData.hasOwnProperty('message')) {
      FOSSBilling.message(apiData.message, "success");
      return;
    }

    if (result) {
      FOSSBilling.message("Form Updated", "success");
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
        formElement.addEventListener('submit', function (event) {
          event.preventDefault();

          const formData = new FormData(formElement);

          if (typeof editors === 'object' && editors !== null && !Array.isArray(editors)) {
            let editorContentOnRequiredAttr = true;
            for (const name in editors) {
              if (Object.prototype.hasOwnProperty.call(editors, name)) {
                const editorConfig = editors[name];
                if (editorConfig.required && editorConfig.editor.getData() === "") {
                  editorContentOnRequiredAttr = false;
                  break;
                }
                formData.set(name, editorConfig.editor.getData());
              }
            }
            if (!editorContentOnRequiredAttr) {
              return FOSSBilling.message('At least one of the required fields are empty.', 'error');
            }
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
              FOSSBilling.message(`${error.message} (${error.code})`, 'error');
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
        linkElement.addEventListener('click', function (event) {
          event.preventDefault();

          let apiData;
          try {
            apiData = Tools.parseDataAttr(linkElement.dataset.fbApi || '{}');
          } catch (error) {
            console.error('Failed to parse data-fb-api attribute:', error);
            FOSSBilling.message('Invalid API configuration', 'error');
            return;
          }

          const rawHref = linkElement.getAttribute('href') || '';
          if (!apiData.href && (!rawHref || rawHref === '#')) {
            return;
          }

          const handleApiRequest = (method, href, params = {}) => {
            const url = apiData.href || href;
            const mergedParams = apiData.params && typeof apiData.params === 'object'
              ? Object.assign({}, apiData.params, params)
              : params;
            API.makeRequest(method, Tools.getBaseURL(url), mergedParams,
              (result) => API._afterComplete(linkElement, result),
              (error) => FOSSBilling.message(`${error.message} (${error.code})`, 'error')
            );
          };

          if (apiData.hasOwnProperty('modal') && apiData.modal.type === 'prompt') {
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
          } else if (apiData.hasOwnProperty('modal')) {
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
          } else {
            handleApiRequest('GET', linkElement.getAttribute('href'));
          }
        });
      });
    }
  }
};
