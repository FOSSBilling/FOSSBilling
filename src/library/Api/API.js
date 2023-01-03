/**
 * FOSSBilling API wrapper for JavaScript
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

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
    },

    /**
     * Reformats malformed JSON data to conform to proper JSON format. 
     * @param {object|string} jsonString The object to reformat to proper JSON data, correcting malformed data
     * @param {boolean} [returnObj=false] Determines if the function returns a reformatted object or the stringified json
     * @returns {object|string} The reformatted object or stringified version of the object.
     */
    reformatJson: function (jsonString, returnObj = false) {
        let obj = jsonString;
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
        var result = (returnObj) ? reformattedObj : JSON.stringify(reformattedObj);
        return result;
    },
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
         * 
         * @documentation https://fossbilling.org/docs/api/javascript
         */
        get: function (endpoint, params, successHandler, errorHandler) {
            API.makeRequest('GET', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler)
        },
        /**
         * Make a POST request to the admin API
         * @param {string} endpoint The endpoint to call. Should be an endpoint path relative to the admin API.
         * @param {object} [params] The parameters to send
         * @param {function} [successHandler] The function to call if the request is successful
         * @param {function} [errorHandler] The function to call if the request is unsuccessful
         * 
         * @documentation https://fossbilling.org/docs/api/javascript
         */
        post: function (endpoint, params, successHandler, errorHandler) {
            API.makeRequest('POST', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler)
        }
    },

    /**
     * Wrapper for the client API
     * @documentation https://fossbilling.org/docs/api/javascript
     */
    client: {
        baseURL: Tools.getBaseURL('client'),
        get: function (endpoint, params, successHandler, errorHandler) {
            API.makeRequest('GET', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler)
        },
        post: function (endpoint, params, successHandler, errorHandler) {
            API.makeRequest('POST', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler)
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
         * 
         * @example
         * API.guest.get("system/version", {}, function(response) {
         *    console.log(response);
         * });
         * 
         * @documentation https://fossbilling.org/docs/api/javascript
         */
        get: function (endpoint, params, successHandler, errorHandler) {
            API.makeRequest('GET', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler)
        },
        /**
         * Make a POST request to the guest API
         * @param {string} endpoint The endpoint to call. Should be an endpoint path relative to the guest API.
         * @param {object} [params] The parameters to send
         * @param {function} [successHandler] The function to call if the request is successful
         * @param {function} [errorHandler] The function to call if the request is unsuccessful
         * 
         * @documentation https://fossbilling.org/docs/api/javascript
         */
        post: function (endpoint, params, successHandler, errorHandler) {
            API.makeRequest('POST', `${this.baseURL}/${endpoint}`, params, successHandler, errorHandler)
        }
    },

    /**
     * Make a request to the API
     * @param {string} method The HTTP method to use
     * @param {string} url The URL to call
     * @param {object} [params] The parameters to send
     * @param {function} [successHandler] The function to call if the request is successful
     * @param {function} [errorHandler] The function to call if the request is unsuccessful
     * 
     * @documentation https://fossbilling.org/docs/api/javascript
     */
    makeRequest: function (method, url, params, successHandler, errorHandler) {
        // If the parameters are not set, set them to an empty object
        params = (params) ? params : {};

        // If the request didn't specify a CSRF token, use the one set in the page headers
        params.CSRFToken = (params.CSRFToken) ? params.CSRFToken : Tools.getCSRFToken();

        url = new URL(url);

        var body = params;

        // Loop through the parameters and add them to the URL as a query string
        // GET requests should have their parameters in the query string and POST requests should have them in the body
        if (method.toLowerCase() === "get") {
            var getParams = params;
            // Convert JSON strings to an Object
            if (Tools.isJSON(params)) {
                getParams = JSON.parse(params);
            }
            if (typeof getParams === "object") {
                Object.keys(getParams).forEach(key => url.searchParams.append(key, getParams[key]));
            }
            body = null
        } else if (method.toLowerCase() === "post") {
            var postParams = params;
            // Convert the JSON to an object
            if (Tools.isJSON(postParams)) {
                postParams = JSON.parse(postParams);
            }
            // Now we run the object through the reformatJson function, setting the body to the returned JSON string
            if (typeof postParams === "object") {
                postParams = Tools.reformatJson(postParams);
                body = postParams;
            } else {
                body = params;
            }
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
                // If the response is an error, call the error handler
                if (response.error) {
                    if (typeof errorHandler === 'function') {
                        errorHandler(response.error);
                    } else {
                        console.error(`${response.error.message} (Code: ${response.error.code})`);
                        console.warn("No error handler was specified. The error was logged to the console. Documentation: https://fossbilling.org/docs/api/javascript   ");
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