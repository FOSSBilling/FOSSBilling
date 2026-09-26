// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
import './js/ui/modals.ts';
import initClipboard from './js/clipboard.ts';
import * as tabler from '@tabler/core';
import './js/fossbilling.ts';

globalThis.tabler = tabler;
// Deprecated alias for third-party extensions; first-party code uses `tabler.*`.
globalThis.bootstrap = tabler.bootstrap;

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
    tabler.Tooltip.getOrCreateInstance(tooltipTriggerEl, {
      'trigger': 'hover focus'
    });
  });

  initClipboard();
});
