// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
import './js/ui/modals.ts';
import * as tabler from '@tabler/core';
import './js/fossbilling.ts';
import { initThemeToggle } from '../../../../../frontend/core/theme-toggle.mts';
import { initTooltips } from '../../../../../frontend/core/tooltips.mts';

globalThis.tabler = tabler;
// Deprecated alias for third-party extensions; first-party code uses `tabler.*`.
globalThis.bootstrap = tabler.bootstrap;

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
    let tooltip = tabler.Tooltip.getInstance(button);
    let createdForThisAction = false;
    if (!tooltip) {
      tooltip = new tabler.Tooltip(button, { trigger: 'manual' });
      createdForThisAction = true;
    }

    const hadOriginalTitle = Object.prototype.hasOwnProperty.call(button.dataset, 'bsOriginalTitle');
    const originalTitle = button.dataset.bsOriginalTitle;
    button.dataset.bsOriginalTitle = 'Copied';
    tooltip.show();
    setTimeout(() => {
      if (hadOriginalTitle) {
        button.dataset.bsOriginalTitle = originalTitle;
      } else {
        delete button.dataset.bsOriginalTitle;
      }
      tooltip.hide();
      if (createdForThisAction) {
        tooltip.dispose();
      }
    }, 2000);
  } else {
    if (typeof FOSSBilling !== 'undefined' && FOSSBilling.message) {
      FOSSBilling.message('Failed to copy to clipboard. Please select the text and copy it manually.', 'error');
    }
  }
}

function loadAdminFeature(loader, name) {
  loader().catch(error => {
    console.error(`Failed to load ${name}:`, error);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  if (document.querySelector('.datepicker')) {
    loadAdminFeature(async () => {
      const { default: initDatepickers } = await import('./js/datepicker.ts');
      initDatepickers();
    }, 'datepickers');
  }

  if (document.querySelector('.js-locale-selector, .autocomplete-selector, .canned_response_select')) {
    loadAdminFeature(async () => {
      const { default: initTomSelectControls } = await import('./js/tomselect.ts');
      initTomSelectControls();
    }, 'Tom Select controls');
  }

  if (document.querySelector('.sortable')) {
    loadAdminFeature(async () => {
      await import('sortable-tablesort/dist/sortable.min.js');
    }, 'sortable tables');
  }

  if (document.querySelector('#theme-settings')) {
    loadAdminFeature(async () => {
      const { default: initThemeSettings } = await import('./js/ui/theme_settings.ts');
      initThemeSettings();
    }, 'theme settings');
  }

  initThemeToggle();

  /**
   * Enable Bootstrap Tooltip
   */
  initTooltips(tabler.Tooltip, { 'trigger': 'hover' });


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
