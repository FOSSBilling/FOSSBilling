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
    const base = `${window.location.origin}/api/`;
    return new URL(url, base).toString();
  },

  /**
   * @returns {string|null} The CSRF token, or null if not found.
   */
  getCSRFToken: function () {
    const metaElement = document.querySelector('meta[name="csrf-token"]');
    return metaElement ? metaElement.getAttribute('content') : null;
  },

  /**
   * Check if a string is valid JSON or not.
   *
   * @param {string} jsonString The string to check.
   * @returns {boolean} True if the string is valid JSON, or false if it is not.
   */
  isJSON: function (jsonString) {
    try {
      const parsed = JSON.parse(jsonString);
      return typeof parsed === 'object' && parsed !== null;
    } catch (error) {
      return false;
    }
  },

  /**
   * Parses the data attribute value from a DOM element, validating against a predefined
   * schema using JSON Type Definition (JTD).
   *
   * @param {string} dataAttrValue The value of the data attribute to parse.
   * @returns {object} The parsed and validated data.
   * @throws {Error} If the data attribute value is invalid or does not match the schema.
   **/
  parseDataAttr: function (dataAttrValue) {
    const ajv = new Ajv();

    const schema = {
      "optionalProperties": {
        "callback": { "type": "string" },
        "message": { "type": "string" },
        "redirect": { "type": "string" },
        "reload": { "type": "boolean" },
        "modal": {
          "discriminator": "type",
          "mapping": {
            "confirm": {
              "properties": {
                "title": { "type": "string" }
              },
              "optionalProperties": {
                "content": { "type": "string" },
                "button": { "type": "string" },
                "buttonColor": { "type": "string" }
              }
            },
            "danger": {
              "properties": {
                "title": { "type": "string" }
              },
              "optionalProperties": {
                "content": { "type": "string" },
                "button": { "type": "string" },
                "buttonColor": { "type": "string" }
              }
            },
            "prompt": {
              "properties": {
                "title": { "type": "string" }
              },
              "optionalProperties": {
                "label": { "type": "string" },
                "value": { "type": "string" },
                "key": { "type": "string" }
              }
            }
          }
        }
      }
    }

    const parse = ajv.compileParser(schema);
    const data = parse(dataAttrValue);

    if (data === undefined) {
      throw new Error(parse.message);
    } else {
      return data;
    }
  },

  /**
   * Converts a form element into a URL encoded string.
   *
   * @param {FormData} formData The FormData object to serialize.
   * @returns {string} Serialized string of the FormData.
   */
  serializeFormData: function (formData) {
    if (!formData.get('CSRFToken')) {
      formData.append('CSRFToken', Tools.getCSRFToken());
    }
    return new URLSearchParams(formData).toString();
  },

  /**
   * Converts a FormData object into a valid JavaScript object.
   *
   * @param {FormData} formData The FormData object to serialize.
   * @returns {object} The reformatted object.
   */
  serializeFormDataToObject: function (formData) {
    const obj = Object.fromEntries(
      Array.from(formData.entries()).map(([key, value]) => {
        if (key.endsWith('[]')) {
          const plainKey = key.slice(0, -2);
          return [plainKey, formData.getAll(`${plainKey}[]`)];
        }
        return [key, value];
      })
    );

    if (!obj.CSRFToken) {
      obj.CSRFToken = Tools.getCSRFToken();
    }

    const reformattedObj = {};
      Object.keys(obj).forEach(function (originalKey) {
        const parts = originalKey.match(/[^[\]]+/g) || [originalKey];
        let currentContext = reformattedObj;

        for (let i = 0; i < parts.length; i++) {
          let part = parts[i];

          if (i === parts.length - 1) {
            currentContext[part] = obj[originalKey];
          } else {
            if (!currentContext.hasOwnProperty(part) || typeof currentContext[part] !== 'object' || currentContext[part] === null) {
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
        console.warn('Invalid endpoint provided. Skipping API call.');
        return;
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
    if (params && typeof params === 'object') {
      if (!params.CSRFToken) {
        const csrfToken = Tools.getCSRFToken();
        if (csrfToken) {
          params.CSRFToken = csrfToken;
        } else {
          console.warn('CSRF token is missing. Request might fail.');
        }
      }
    }

    let body = null;
    if (method.toLowerCase() === 'get') {
      if (typeof params === 'object') {
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
      } else if (params) {
        url.search = params;
      }
    } else if (['post', 'put', 'patch', 'delete'].includes(method.toLowerCase())) {
      if (!Tools.isJSON(params)) {
        params = JSON.stringify(params);
      }
      body = params;
    }

    const headers = {
      'Accept': 'application/json',
    };
    if (body) {
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
        }

        throw error;
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
    setTimeout(() => { loader.style.opacity = '1'; }, 250);
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

          const data = formElement.getAttribute('method').toLowerCase() !== 'get'
            ? Tools.serializeFormDataToJSON(formData)
            : Tools.serializeFormData(formData);

          const buttons = formElement.querySelectorAll('button:not([disabled])');
          const toggleButtons = (disable) => {
            buttons.forEach(button => button.disabled = disable);
          };
          toggleButtons(true);

          API.makeRequest(
            formElement.getAttribute('method'),
            Tools.getBaseURL(formElement.getAttribute('action')),
            data,
            (result) => {
              toggleButtons(false);
              return API._afterComplete(formElement, result);
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
            console.warn('Invalid JSON in data-fb-api attribute:', error);
            return;
          }

          const handleApiRequest = (method, href, params = {}) => {
            API.makeRequest(method, Tools.getBaseURL(href), params,
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
