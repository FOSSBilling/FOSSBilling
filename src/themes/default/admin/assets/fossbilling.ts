// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
import { initEmailPreviewSupport } from '../../../../../frontend/ui/email-preview.ts';
import initNoticeDismissal from '../../../../../frontend/ui/notice-dismissal.ts';
import initFloatingHelp from '../../../../../frontend/ui/floating-help.ts';
import initDropdownFocus from '../../../../../frontend/ui/dropdown-focus.ts';
import initNavigation from './js/ui/navigation.ts';
import './js/ui/modals.ts';
import initClipboard from './js/clipboard.ts';
import initFormErrors from './js/ui/form-errors.ts';
import initFormLoading from './js/ui/form-loading.ts';
import flashMessage from './js/ui/flash.ts';
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
  initEmailPreviewSupport();
  initNoticeDismissal();
  initFloatingHelp(tabler);
  initDropdownFocus();
  initFormErrors();
  initFormLoading();
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

  initNavigation();
  initThemePreference();
  globalThis.flashMessage ||= flashMessage;
  globalThis.flashMessage({});

  initClipboard();
});
