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
 * Get CSRF token from cookie
 * @returns {string|null} CSRF token or null if not found
 */
export function getCSRFToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)fossbilling_csrf=([^;]*)/)
    || document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Get base URL from relative path
 * @param {string} path - Relative path
 * @returns {string} Absolute URL
 */
export function getBaseURL(path: string): string {
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
export function safeQuerySelector(selector: string, scope: ParentNode = document): Element | null {
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
export function safeQuerySelectorAll(selector: string, scope: ParentNode = document): NodeListOf<Element> | [] {
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
export function debounce<T extends (...args: unknown[]) => void>(func: T, wait: number) {
  let timeout: ReturnType<typeof setTimeout> | undefined;
  return function executedFunction(this: unknown, ...args: Parameters<T>) {
    const context = this;
    const later = () => {
      clearTimeout(timeout);
      func.apply(context, args);
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
export function throttle<T extends (...args: unknown[]) => void>(func: T, limit: number) {
  let lastFunc: ReturnType<typeof setTimeout> | undefined;
  let lastRan: number | undefined;
  return function(this: unknown, ...args: Parameters<T>) {
    const context = this;
    if (!lastRan) {
      func.apply(context, args);
      lastRan = Date.now();
    } else {
      clearTimeout(lastFunc);
      lastFunc = setTimeout(() => {
        if ((Date.now() - lastRan) >= limit) {
          func.apply(context, args);
          lastRan = Date.now();
        }
      }, limit - (Date.now() - lastRan));
    }
  };
}

/**
 * Check if element is in viewport
 * @param {HTMLElement} element - Element to check
 * @returns {boolean} True if element is in viewport
 */
export function isElementInViewport(element: Element | null): boolean {
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
export function scrollToElement(target: Element | string, options: ScrollIntoViewOptions = {}) {
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
export function formatBytes(bytes: number, decimals = 2): string {
  if (!Number.isFinite(bytes) || bytes < 0) return '0 Bytes';
  if (bytes === 0) return '0 Bytes';

  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

  const i = Math.floor(Math.log(bytes) / Math.log(k));
  const index = Math.min(Math.max(i, 0), sizes.length - 1);

  return parseFloat((bytes / Math.pow(k, index)).toFixed(dm)) + ' ' + sizes[index];
}

/**
 * Deep merge objects
 * @param {...Object} objects - Objects to merge
 * @returns {Object} Merged object
 */
export function deepMerge(...objects: Array<Record<string, unknown>>): Record<string, unknown> {
  const result: Record<string, unknown> = {};

  for (const obj of objects) {
    for (const key in obj) {
      if (Object.prototype.hasOwnProperty.call(obj, key)) {
        if (typeof obj[key] === 'object' && obj[key] !== null && !Array.isArray(obj[key])) {
          const current = typeof result[key] === 'object' && result[key] !== null && !Array.isArray(result[key])
            ? result[key] as Record<string, unknown>
            : {};
          result[key] = deepMerge(current, obj[key] as Record<string, unknown>);
        } else {
          result[key] = obj[key];
        }
      }
    }
  }

  return result;
}
