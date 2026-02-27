import './js/ui/modals';
import { initAvatars } from './js/avatar.js';
import { coloris, init } from '@melloware/coloris';
import * as tabler from '@tabler/core/js/tabler.js';
import './js/tomselect';
import './js/datepicker';
import ApexCharts from 'apexcharts';
import './js/ui/theme_settings';
import './js/fossbilling';
import 'sortable-tablesort/dist/sortable.min.js';

globalThis.ApexCharts = ApexCharts;
globalThis.bootstrap = tabler.bootstrap;

init();
coloris({
  el: '#coloris-picker',
  alpha: false
});


/**
 * Extracts text from the clipboard target element referenced by the button.
 * Returns null if the selector is missing, invalid, or the element is not found.
 */
function getClipboardTargetText(button) {
  const targetSelector = button.dataset.clipboardTarget;
  if (!targetSelector) return null;

  // Only allow simple ID selectors such as "#element-id"
  if (!/^#[A-Za-z0-9_-]+$/.test(targetSelector)) {
    return null;
  }

  const targetElement = document.querySelector(targetSelector);
  if (!targetElement) return null;

  if ('value' in targetElement) {
    return targetElement.value;
  }

  return targetElement.textContent;
}

/**
 * Attempts to copy the given text to the clipboard.
 * Falls back to a legacy execCommand approach when the Clipboard API is unavailable.
 */
async function copyTextToClipboard(text) {
  if (navigator.clipboard?.writeText) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch (err) {
      // Fall through to legacy fallback
    }
  }

  let textarea;
  try {
    textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    // Intentional: use deprecated execCommand as a legacy clipboard fallback for older browsers.
    return document.execCommand('copy');
  } catch (err) {
    return false;
  } finally {
    if (textarea && textarea.parentNode) {
      textarea.parentNode.removeChild(textarea);
    }
  }
}

/**
 * Shows a "Copied" tooltip on success or an error message on failure.
 */
function handleClipboardResult(button, success) {
  if (success) {
    let tooltip = bootstrap.Tooltip.getInstance(button);
    if (!tooltip) {
      tooltip = new bootstrap.Tooltip(button, { trigger: 'manual' });
    }

    const originalTitle = button.dataset.bsOriginalTitle;
    button.dataset.bsOriginalTitle = 'Copied';
    tooltip.show();
    setTimeout(() => {
      button.dataset.bsOriginalTitle = originalTitle;
      tooltip.hide();
    }, 2000);
  } else {
    if (typeof FOSSBilling !== 'undefined' && FOSSBilling.message) {
      FOSSBilling.message('Failed to copy to clipboard', 'error');
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  initAvatars();

  document.querySelectorAll('.js-theme-toggler').forEach(element => {
    element.addEventListener('click', event => {
      event.preventDefault();
      // Intentionally use getAttribute('href') to read the raw attribute value
      // instead of element.href, since we only parse a simple theme token here.
      const href = element.getAttribute('href') || '';
      let theme = null;

      // Try to extract theme value safely from href
      if (href.includes('=')) {
        const parts = href.split('=');
        if (parts.length > 1 && parts[1]) {
          theme = parts[1];
        }
      }

      if (!theme) {
        return;
      }

      localStorage.setItem('theme', theme);
      document.documentElement.setAttribute('data-bs-theme', theme);
    });
  });


  /**
   * Enable Bootstrap Tooltip
   */
  const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(function (tooltipTriggerEl) {
    new bootstrap.Tooltip(tooltipTriggerEl, {
      'trigger': 'hover'
    });
  });


  /**
   * Copy To Clipboard
   */
  document.addEventListener('click', async function(event) {
    const button = event.target.closest('.clipboard-copy');
    if (!button) return;

    const text = getClipboardTargetText(button);
    if (text === null) return;

    const success = await copyTextToClipboard(text);
    handleClipboardResult(button, success);
  });
});
