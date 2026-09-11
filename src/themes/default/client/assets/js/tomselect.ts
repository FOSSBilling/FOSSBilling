// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
/**
 * Huraga theme TomSelect setup - lazy loaded only when needed
 */

import TomSelect from 'tom-select';
import { initLocaleSelector } from "../../../../../../frontend/core/locale-select.mts";

globalThis.TomSelect = TomSelect;

/**
 * Parse custom properties string to extract flag class
 * @param {string} customProperties - HTML string with flag span
 * @returns {string} - Flag class name or empty string
 */
function extractFlagClass(customProperties) {
  if (!customProperties) return '';
  const match = customProperties.match(/class="([^"]*fi[^"]*)"/);
  return match ? match[1] : '';
}

/**
 * Get the flag icon HTML for display
 * @param {object} data - Option data object
 * @param {boolean} escape - Whether to escape the text
 * @returns {string} - HTML string for the option
 */
function localeSelectorTemplate(data, escape) {
  const flagClass = extractFlagClass(data.customProperties);
  const flagHtml = flagClass ? `<span class="${flagClass} locale-flag"></span>` : '';
  return `<div class="d-flex align-items-center">${flagHtml}${escape(data.text)}</div>`;
}

export default function initLanguageSelector() {
  initLocaleSelector(TomSelect, {
    item: (data, escape) => localeSelectorTemplate(data, escape),
    option: (data, escape) => localeSelectorTemplate(data, escape),
  });
}
