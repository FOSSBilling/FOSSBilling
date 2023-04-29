/**
 * FOSSBilling API wrapper for JavaScript
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
  * Converts a form element into a url encoded string
  * @param {object} FormData object
  * @returns {string} Serialized string of the FormData
 */
FormData.prototype.serialize = function () {
    if (!this.get('CSRFToken')) {
        this.append('CSRFToken', Tools.getCSRFToken());
    }
    return new URLSearchParams(this).toString();
}
/**
 * Converts a form element in valid valid object that can be used for JSON
 * @param {object} FormData object
 * @returns {object} The reformatted object or stringified version of the object.
*/
FormData.prototype.serializeObject = function () {
    const obj = {};
    if (!this.get('CSRFToken')) {
        this.append('CSRFToken', Tools.getCSRFToken());
    }
    // reformat input[] fields to arrays
    for (const pair of this.entries()) {
        key = pair[0];
        if (key.endsWith('[]')) {
            key = key.slice(0, -2);
            if (!obj[key]) {
                obj[key] = [];
            }
            obj[key].push(pair[1]);
        } else {
            obj[key] = pair[1];
        }
    }
    let reformattedObj = {};
    Object.keys(obj).forEach(function (key) {
        let parts = key.split('[');
        let current = reformattedObj;
        for (let i = 0; i < parts.length; i++) {
            let part = parts[i];
            if (part.endsWith(']')) {
                part = part.slice(0, -1);
            }
            if (i === parts.length - 1) {
                current[part] = obj[key];
            } else {
                if (!(part in current)) {
                    current[part] = {};
                }
                current = current[part];
            }
        }
    });
    return reformattedObj;
}
/**
 * Converts a form element into a valid JSON string depends on serializeObject
 * @param {object} FormData object
 * @returns {string} Returns JSON string of the FromData Object
*/
FormData.prototype.serializeJSON = function () {
    return JSON.stringify(this.serializeObject());
}


const Tools = {
    /**
     * Get the full URL from a relative URL to the API
     * @param {string} url The endpoint to call. Might be a relative URL or a full URL. If it's a relative URL, it will be appended to the base URL.
     * @returns {string} The full URL to call
     */
    getBaseURL: function (url) {
        if (url.indexOf('http://') > -1 || url.indexOf('https://') > -1) {
            return url;
        }
        // Return the base URL from the page headers. The theme must have the base URL in the page headers for this to work.
        return document.querySelector('meta[property="bb:url"]').getAttribute('content') + 'index.php?_url=/api/' + url;
    },

    /**
     * Grab the CSRF token from the page headers. The theme must have the CSRF token in the page headers for this to work.
     * @returns {string} The CSRF token
     */
    getCSRFToken: function () {
        return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    },

    /**
     * Check if a string is valid JSON or not.
     * @param {string} jsonString The string to check if it's valid JSON or not
     * @returns {boolean} Returns true if the string is valid JSON, or false if it is not
     */
    isJSON: function (jsonString) {
        try {
            JSON.parse(jsonString);
            return true;
        } catch (error) {
            return false;
        }
    }
}

/**
  * FOSSBilling API wrapper for JavaScript
  * @documentation https://fossbilling.org/docs/api/javascript
  */
const API = {
    /**
     * Wrapper for the admin API
     * @documentation https://fossbilling.org/docs/api/javascript
     */
    admin: {
        baseURL: Tools.getBaseURL('admin'),
        /**
         * Make a GET request to the admin API
         * @param {string} endpoint The endpoint to call. Should be an endpoint path relative to the admin API.
         * @param {object} [params] The parameters to send
         * @param {function} [successHandler] The function to call if the request is successful
         * @param {function} [errorHandler] The function to call if the request is unsuccessful
         * @param {bool} [enableLoader] Enable or disable the usage of a loader
         *
         * @documentation https://fossbilling.org/docs/api/javascript
         */
        get: function (endpoint, params, successHandler, errorHandler, enableLoader = true) {
            API.makeRequest('GET', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler, enableLoader)
        },
        /**
         * Make a POST request to the admin API
         * @param {string} endpoint The endpoint to call. Should be an endpoint path relative to the admin API.
         * @param {object} [params] The parameters to send
         * @param {function} [successHandler] The function to call if the request is successful
         * @param {function} [errorHandler] The function to call if the request is unsuccessful
         * @param {bool} [enableLoader] Enable or disable the usage of a loader
         *
         * @documentation https://fossbilling.org/docs/api/javascript
         */
        post: function (endpoint, params, successHandler, errorHandler, enableLoader = true) {
            API.makeRequest('POST', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler, enableLoader)
        }
    },

    /**
     * Wrapper for the client API
     * @documentation https://fossbilling.org/docs/api/javascript
     */
    client: {
        baseURL: Tools.getBaseURL('client'),
        get: function (endpoint, params, successHandler, errorHandler, enableLoader = true) {
            API.makeRequest('GET', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler, enableLoader)
        },
        post: function (endpoint, params, successHandler, errorHandler, enableLoader = true) {
            API.makeRequest('POST', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler, enableLoader)
        }
    },

    /**
     * Wrapper for the guest API
     * @documentation https://fossbilling.org/docs/api/javascript
     */
    guest: {
        baseURL: Tools.getBaseURL('guest'),
        /**
         * Make a GET request to the guest API
         * @param {string} endpoint The endpoint to call. Should be an endpoint path relative to the guest API.
         * @param {object} [params] The parameters to send
         * @param {function} [successHandler] The function to call if the request is successful
         * @param {function} [errorHandler] The function to call if the request is unsuccessful
         * @param {bool} [enableLoader] Enable or disable the usage of a loader
         *
         * @example
         * API.guest.get("system/version", {}, function(response) {
         *    console.log(response);
         * });
         *
         * @documentation https://fossbilling.org/docs/api/javascript
         */
        get: function (endpoint, params, successHandler, errorHandler, enableLoader = true) {
            API.makeRequest('GET', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler, enableLoader)
        },
        /**
         * Make a POST request to the guest API
         * @param {string} endpoint The endpoint to call. Should be an endpoint path relative to the guest API.
         * @param {object} [params] The parameters to send
         * @param {function} [successHandler] The function to call if the request is successful
         * @param {function} [errorHandler] The function to call if the request is unsuccessful
         * @param {bool} [enableLoader] Enable or disable the usage of a loader
         *
         * @documentation https://fossbilling.org/docs/api/javascript
         */
        post: function (endpoint, params, successHandler, errorHandler, enableLoader = true) {
            API.makeRequest('POST', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler, enableLoader)
        }
    },

    /**
     * Make a request to the API
     * @param {string} method The HTTP method to use
     * @param {string} url The URL to call
     * @param {object|string} [params] The parameters to send
     * @param {function} [successHandler] The function to call if the request is successful
     * @param {function} [errorHandler] The function to call if the request is unsuccessful
     * @param {bool} [enableLoader] Enable or disable the usage of a loader. Custom themes simply need to provide one with the spinner-border class
     * @documentation https://fossbilling.org/docs/api/javascript
     */
    makeRequest: function (method, url, params, successHandler, errorHandler, enableLoader = true) {
        // Add a loading icon to the page
        const loader = document.createElement('div');
        loader.classList.add('spinner-border');
        loader.setAttribute('role', 'status');
        loader.style.width = '4rem';
        loader.style.height = '4rem';
        loader.style.left = '50%';
        loader.style.top = '50%';
        loader.style.position = 'fixed';
        loader.style.opacity = '0';
        loader.style.transition = 'opacity 250ms';
        document.body.appendChild(loader);

        url = new URL(url);
        if (typeof params === 'object') {
            if (!params.CSRFToken) { params.CSRFToken = Tools.getCSRFToken() }
        }
        // Loop through the parameters and add them to the URL as a query string
        // GET requests should have their parameters in the query string and POST requests should have them in the body
        if (method.toLowerCase() === "get") {
            if (typeof params === 'object') {
                Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
            } else {
                if (params) {
                    url.search = params;
                }
            }
            body = null
        } else if (method.toLowerCase() === "post") {
            if (!Tools.isJSON(params)) {
                params = JSON.stringify(params)
            }
            body = params;
        }

        if (enableLoader) {
            setTimeout(() => {
                loader.style.opacity = '1';
            }, 250);
        }

        // Call the API and handle the response
        return fetch(url.toString(), {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: body,
        })
            .then((response) => {
                return response.json();
            })
            .then((response) => {
                document.body.removeChild(loader);
                // If the response is an error, call the error handler
                if (response.error) {
                    if (typeof errorHandler === 'function') {
                        errorHandler(response.error);
                    } else {
                        console.error(`${response.error.message} (Code: ${response.error.code})`);
                        console.warn("No error handler was specified. The error was logged to the console. Documentation: https://fossbilling.org/docs/api/javascript");
                    }
                    return;
                }

                // If the response is a success, call the success handler
                if (typeof successHandler === 'function') {
                    successHandler(response.result);
                }
            })
    },
};
