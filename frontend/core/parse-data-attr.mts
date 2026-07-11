/**
 * FOSSBilling API for JavaScript — data-fb-api attribute parser.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 *
 * Pure, side-effect-free validators for the JSON object stored in a
 * `data-fb-api` attribute. Exported so it can be unit-tested in Node
 * without a DOM; bundled into src/public/assets/js/api.js by esbuild.
 */

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

const assertPositiveNumber = (value, key) => {
  if (typeof value !== 'number' || !Number.isFinite(value) || value <= 0) {
    throw new Error(`data-fb-api.${key} must be a positive number.`);
  }
};

const assertPlainObject = (value, key) => {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    throw new Error(`data-fb-api.${key} must be an object.`);
  }
};

/**
 * Map of top-level data-fb-api fields to their scalar validators.
 * Looping over this replaces the per-field hasOwnProperty boilerplate.
 */
const TOP_LEVEL_SCHEMA = {
  href: assertString,
  type: assertString,
  endpoint: assertString,
  callback: assertString,
  message: assertString,
  redirect: assertString,
  reload: assertBoolean,
  preventNavigation: assertBoolean,
  timeoutMs: assertPositiveNumber,
  timeoutMessage: assertString,
  params: assertPlainObject,
};

const LOADING_STRING_FIELDS = ['message', 'button', 'target', 'alertClass'];

const MODAL_ALLOWED_TYPES = ['confirm', 'danger', 'prompt'];
const MODAL_STRING_FIELDS = ['title', 'content', 'button', 'buttonColor', 'label', 'value', 'key'];

/**
 * Validates the optional `loading` sub-object.
 *
 * @param {*} loading The value of data-fb-api.loading.
 * @throws {Error} If `loading` is not an object or one of its string fields is invalid.
 */
function validateLoading(loading) {
  assertPlainObject(loading, 'loading');

  for (const field of LOADING_STRING_FIELDS) {
    if (Object.prototype.hasOwnProperty.call(loading, field)) {
      assertString(loading[field], `loading.${field}`);
    }
  }
}

/**
 * Validates the optional `modal` sub-object, including its `type` enum
 * and the conditional requirement that prompt modals declare a `key`.
 *
 * @param {*} modal The value of data-fb-api.modal.
 * @throws {Error} If `modal` is structurally invalid.
 */
function validateModal(modal) {
  assertPlainObject(modal, 'modal');

  if (typeof modal.type !== 'string') {
    throw new Error('data-fb-api.modal.type must be a string.');
  }
  if (!MODAL_ALLOWED_TYPES.includes(modal.type)) {
    throw new Error(`data-fb-api.modal.type must be one of: ${MODAL_ALLOWED_TYPES.join(', ')}.`);
  }
  if (modal.type === 'prompt' && typeof modal.key !== 'string') {
    throw new Error('data-fb-api.modal.key is required for prompt modals.');
  }

  for (const field of MODAL_STRING_FIELDS) {
    if (Object.prototype.hasOwnProperty.call(modal, field)) {
      assertString(modal[field], `modal.${field}`);
    }
  }
}

/**
 * Parses the data attribute value from a DOM element and validates known fields.
 *
 * @param {string} dataAttrValue The value of the data attribute to parse.
 * @returns {object} The parsed and validated data.
 * @throws {Error} If the data attribute value is invalid or does not match the schema.
 **/
export function parseDataAttr(dataAttrValue) {
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

  for (const [key, validator] of Object.entries(TOP_LEVEL_SCHEMA)) {
    if (Object.prototype.hasOwnProperty.call(data, key)) {
      validator(data[key], key);
    }
  }

  if (Object.prototype.hasOwnProperty.call(data, 'loading')) {
    validateLoading(data.loading);
  }

  if (Object.prototype.hasOwnProperty.call(data, 'modal')) {
    validateModal(data.modal);
  }

  return data;
}
