// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
import initNoticeDismissal from '../../../../../frontend/ui/notice-dismissal.ts';
import initFloatingHelp from '../../../../../frontend/ui/floating-help.ts';
import initDropdownFocus from '../../../../../frontend/ui/dropdown-focus.ts';
import * as tabler from '@tabler/core';
import './js/utils.ts';
import initTheme from './js/ui/theme.ts';
import initEmailPreviews from './js/ui/email-preview.ts';
import initPhoneInput from './js/phone-input.ts';
import initNotifications from './js/notifications.ts';
import initAuthForms from './js/auth-forms.ts';
import initModals from './js/ui/modals.ts';
import initTabs from './js/ui/tabs.ts';
import initFormErrors from './js/ui/form-errors.ts';
import initNavigation from './js/ui/navigation.ts';
import initFocus from './js/ui/focus.ts';
import initFormLoading from './js/ui/form-loading.ts';
import flashMessage from './js/ui/flash.ts';
import initUnsavedChanges from './js/ui/unsaved-changes.ts';

globalThis.tabler = tabler;
// Preserve the Bootstrap component API used by existing module and extension templates.
globalThis.bootstrap = tabler.bootstrap;

document.addEventListener('DOMContentLoaded', () => {
  initNoticeDismissal();
  initFloatingHelp(tabler);
  initDropdownFocus();
  /**
   * Global error handler for unhandled Promise rejections
   */
  window.addEventListener('unhandledrejection', function(event) {
    const error = event.reason;
    let message = 'An unexpected error occurred';
    if (error && typeof error === 'object') {
      message = error.message || error.code || message;
    } else if (typeof error === 'string') {
      message = error;
    }
    FOSSBilling.message(message, 'error');
  });

  /**
   * Global error handler for synchronous errors
   */
  window.onerror = function(message, source, lineno, colno, error) {
    let displayMessage = message;
    if (error && error.message) {
      displayMessage = error.message;
    }
    FOSSBilling.message(displayMessage, 'error');
  };

  /**
   * Wire up the light/dark theme controller. Runs on every page that
   * includes the Huraga layout so user toggles are persisted in localStorage
   * and the data-bs-theme attribute is kept in sync.
   */
  initFormErrors();
  initFormLoading();
  initNavigation();
  initFocus();
  initModals();
  initTheme();
  initEmailPreviews();
  initPhoneInput();
  initNotifications();
  initAuthForms();
  initUnsavedChanges();

  initTabs();

  /**
   * Manage flash message to show after page reload
   */
  globalThis.flashMessage ||= flashMessage;
  globalThis.flashMessage({});

  /**
   * Add asterisk to required field labels
   */
  const requiredInputs = document.querySelectorAll('input[required]:not([type="hidden"]), select[required], textarea[required]');
  requiredInputs.forEach(input => {
    if (input.closest('form.auth')) {
      return;
    }
    [...(input.labels || [])].forEach(label => {
      if (label.classList.contains('required') || /\*\s*$/.test(label.textContent || '')) {
        return;
      }
      const asterisk = document.createElement('span');
      asterisk.textContent = ' *';
      asterisk.classList.add('text-danger');
      asterisk.setAttribute('aria-hidden', 'true');
      label.appendChild(asterisk);
    });
  });

  const currencySelector = document.querySelectorAll('select.currency_selector');
  currencySelector.forEach(function (select) {
    select.addEventListener('change', function () {
      FOSSBilling.api.guest.post('cart/set_currency', {currency: select.value}, function(response) {
        location.reload();
      }, function(error) {
        let message = 'An unexpected error occurred';
        if (error && typeof error === 'object') {
          message = error.message || error.code || message;
        } else if (typeof error === 'string') {
          message = error;
        }
        FOSSBilling.message(message, 'error');
      });
    });
  });

  /**
   * Lazy load Tom Select only if language selector exists
   * Includes error handling and ensures CSS is loaded before JS initializes
   */
  const languageSelector = document.querySelector('.js-locale-selector');
  if (languageSelector) {
    // Dynamically import TomSelect module with error handling
    import('./js/tomselect.ts')
      .then(module => {
        if (typeof module.default === 'function') {
          module.default();
        } else {
          console.error('TomSelect module does not export a default function');
        }
      })
      .catch(err => {
        console.error('Failed to load language selector:', err);
      });
  }

});
