// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
import * as tabler from '@tabler/core';
import './js/utils.ts';
import initTheme from './js/ui/theme.ts';
import initPhoneInput from './js/phone-input.ts';
import { errorMessage } from '../../../../../frontend/core/error-message.mts';
import { initTabDeepLinking } from '../../../../../frontend/core/tabs.mts';
import { initTooltips } from '../../../../../frontend/core/tooltips.mts';

globalThis.tabler = tabler;
// Deprecated alias for third-party extensions; first-party code uses `tabler.*`.
globalThis.bootstrap = tabler.bootstrap;

document.addEventListener('DOMContentLoaded', () => {
  /**
   * Global error handler for unhandled Promise rejections
   */
  window.addEventListener('unhandledrejection', function(event) {
    FOSSBilling.message(errorMessage(event.reason), 'error');
  });

  /**
   * Global error handler for synchronous errors
   */
  window.onerror = function(message, source, lineno, colno, error) {
    FOSSBilling.message((error && error.message) || message, 'error');
  };

  /**
   * Wire up the light/dark theme controller. Runs on every page that
   * includes the Huraga layout so user toggles are persisted in localStorage
   * and the data-bs-theme attribute is kept in sync.
   */
  initTheme();
  initPhoneInput();

  /**
   * Enable Bootstrap Tooltip
   */
  initTooltips(bootstrap.Tooltip);

  initTabDeepLinking(bootstrap.Tab, {
    selectors: '[data-bs-toggle="tab"], [data-bs-toggle="pill"], [data-bs-toggle="list"]',
  });

  /**
   * Manage flash message to show after page reload
   */
  globalThis.flashMessage = ({message = '', reload = false, type = 'info'}) => {
    let key = 'flash-message';
    let sessionMessage = sessionStorage.getItem(key);
    if (message === '' && sessionMessage) {
      FOSSBilling.message(sessionMessage, type);
      sessionStorage.removeItem(key);
      return;
    }
    if (message) {
      sessionStorage.setItem(key, message);
      if (typeof reload === 'boolean' && reload) {
        window.location.reload();
      } else if (typeof reload === 'string') {
        window.location.assign(reload);
      }
    }
  };
  flashMessage({});

  /**
   * Add asterisk to required field labels
   */
  const requiredInputs = document.querySelectorAll('input[required], textarea[required]');
  requiredInputs.forEach(input => {
    const label = input.previousElementSibling;
    const isAuth = input.parentElement.parentElement.classList.contains('auth');
    if (!isAuth && label && label.tagName.toLowerCase() === 'label') {
      const asterisk = document.createElement('span');
      asterisk.textContent = ' *';
      asterisk.classList.add('text-danger');
      label.appendChild(asterisk);
    }
  });

  const currencySelector = document.querySelectorAll('select.currency_selector');
  currencySelector.forEach(function (select) {
    select.addEventListener('change', function () {
      FOSSBilling.api.guest.post('cart/set_currency', {currency: select.value}, function(response) {
        location.reload();
      }, function(error) {
        FOSSBilling.message(errorMessage(error), 'error');
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
