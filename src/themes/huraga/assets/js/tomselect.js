/**
 * Huraga theme TomSelect setup - lazy loaded only when needed
 */

import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.css';

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
  const flagHtml = flagClass ? `<span class="${flagClass} me-2" style="display: inline-block; vertical-align: middle;"></span>` : '';
  return `<div class="d-flex align-items-center">${flagHtml}${escape(data.text)}</div>`;
}

export default function initLanguageSelector() {
  const localeSelectorEl = document.querySelector('.js-language-selector');
  if (localeSelectorEl === null) {
    return;
  }

  // Get saved language preference
  const savedLang = getCookie('BBLANG') || '';

  let localeSelector = new TomSelect('.js-language-selector', {
    copyClassesToDropdown: false,
    controlClass: 'ts-control locale',
    dropdownClass: 'dropdown-menu ts-dropdown locale-selector-dropdown',
    optionClass: 'dropdown-item',
    controlInput: false,
    items: savedLang ? [savedLang] : [],
    render: {
      item: (data, escape) => localeSelectorTemplate(data, escape),
      option: (data, escape) => localeSelectorTemplate(data, escape),
    },
    onItemAdd: (value) => {
      setCookie('BBLANG', value, 365);
      window.location.reload();
    },
  });
}

function getCookie(name) {
  var nameEQ = name + '=';
  var ca = document.cookie.split(';');
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) == ' ') c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
  }
  return null;
}

function setCookie(name, value, days) {
  if (days) {
    var date = new Date();
    date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
    var expires = '; expires=' + date.toGMTString();
  } else var expires = '';
  document.cookie = name + '=' + value + expires + '; path=/ ';
}
