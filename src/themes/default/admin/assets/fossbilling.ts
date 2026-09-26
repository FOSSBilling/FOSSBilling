// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
import './js/ui/modals.ts';
import initClipboard from './js/clipboard.ts';
import initThemePreference from './js/ui/theme.ts';
import initDebugBarLayout from './js/ui/debugBar.ts';
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
  initDebugBarLayout();
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

  initThemePreference();

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
