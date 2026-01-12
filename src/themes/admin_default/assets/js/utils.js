/**
 * Utility functions for FOSSBilling admin theme
 * 
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Get CSRF token from meta tag
 * @returns {string|null} CSRF token or null if not found
 */
export function getCSRFToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || null;
}

/**
 * Get base URL from relative path
 * @param {string} path - Relative path
 * @returns {string} Absolute URL
 */
export function getBaseURL(path) {
  if (!path) return '';
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }
  return new URL(path, window.location.origin).toString();
}

/**
 * Safe DOM query selector with error handling
 * @param {string} selector - CSS selector
 * @param {HTMLElement|Document} [scope=document] - Scope to search within
 * @returns {HTMLElement|null} Found element or null
 */
export function safeQuerySelector(selector, scope = document) {
  try {
    return scope.querySelector(selector);
  } catch (error) {
    console.warn(`Invalid selector: ${selector}`, error);
    return null;
  }
}

/**
 * Safe DOM query selector for multiple elements
 * @param {string} selector - CSS selector
 * @param {HTMLElement|Document} [scope=document] - Scope to search within
 * @returns {NodeList|[]} Found elements or empty array
 */
export function safeQuerySelectorAll(selector, scope = document) {
  try {
    return scope.querySelectorAll(selector);
  } catch (error) {
    console.warn(`Invalid selector: ${selector}`, error);
    return [];
  }
}

/**
 * Debounce function to limit how often a function can be called
 * @param {Function} func - Function to debounce
 * @param {number} wait - Time to wait in milliseconds
 * @returns {Function} Debounced function
 */
export function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

/**
 * Throttle function to limit how often a function can be called
 * @param {Function} func - Function to throttle
 * @param {number} limit - Time limit in milliseconds
 * @returns {Function} Throttled function
 */
export function throttle(func, limit) {
  let lastFunc;
  let lastRan;
  return function(...args) {
    if (!lastRan) {
      func(...args);
      lastRan = Date.now();
    } else {
      clearTimeout(lastFunc);
      lastFunc = setTimeout(() => {
        if ((Date.now() - lastRan) >= limit) {
          func(...args);
          lastRan = Date.now();
        }
      }, limit - (Date.now() - lastRan));
    }
  }
}

/**
 * Check if element is in viewport
 * @param {HTMLElement} element - Element to check
 * @returns {boolean} True if element is in viewport
 */
export function isElementInViewport(element) {
  if (!element) return false;
  
  const rect = element.getBoundingClientRect();
  return (
    rect.top >= 0 &&
    rect.left >= 0 &&
    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
  );
}

/**
 * Scroll to element smoothly
 * @param {HTMLElement|string} target - Element or selector
 * @param {Object} [options] - Scroll options
 */
export function scrollToElement(target, options = {}) {
  const element = typeof target === 'string' ? safeQuerySelector(target) : target;
  if (!element) return;
  
  const { behavior = 'smooth', block = 'start', inline = 'nearest' } = options;
  
  element.scrollIntoView({
    behavior,
    block,
    inline
  });
}

/**
 * Format bytes to human readable format
 * @param {number} bytes - Bytes to format
 * @param {number} [decimals=2] - Number of decimals
 * @returns {string} Formatted string
 */
export function formatBytes(bytes, decimals = 2) {
  if (bytes === 0) return '0 Bytes';
  
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
  
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Deep merge objects
 * @param {...Object} objects - Objects to merge
 * @returns {Object} Merged object
 */
export function deepMerge(...objects) {
  const result = {};
  
  for (const obj of objects) {
    for (const key in obj) {
      if (obj.hasOwnProperty(key)) {
        if (typeof obj[key] === 'object' && obj[key] !== null && !Array.isArray(obj[key])) {
          result[key] = deepMerge(result[key] || {}, obj[key]);
        } else {
          result[key] = obj[key];
        }
      }
    }
  }
  
  return result;
}

/**
 * Register a CKEditor instance for canned response functionality
 * @param {HTMLElement} element - The editor container element
 * @param {Object} editor - The CKEditor instance
 */
export function registerEditor(element, editor) {
  if (!element || !editor) {
    console.warn('Invalid editor registration:', { element, editor });
    return;
  }
  
  // Store editor on the element itself
  element.editor = editor;
  
  // Add data attribute to mark as editor
  element.setAttribute('data-editor', 'true');
  
  // Initialize FOSSBilling editors registry if needed
  window.FOSSBilling = window.FOSSBilling || {};
  window.FOSSBilling.editors = window.FOSSBilling.editors || {};
  
  // Store in registry with element ID as key
  if (element.id) {
    window.FOSSBilling.editors[element.id] = editor;
  }
  
  console.debug('Editor registered:', element.id || 'unnamed', editor);
}

/**
 * Unregister a CKEditor instance
 * @param {HTMLElement} element - The editor container element
 */
export function unregisterEditor(element) {
  if (!element) return;
  
  // Remove from element
  delete element.editor;
  element.removeAttribute('data-editor');
  
  // Remove from registry
  if (element.id && window.FOSSBilling?.editors) {
    delete window.FOSSBilling.editors[element.id];
  }
  
  console.debug('Editor unregistered:', element.id || 'unnamed');
}