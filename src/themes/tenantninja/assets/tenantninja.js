import { Tooltip, Toast, Modal, Collapse, Tab } from 'bootstrap/dist/js/bootstrap.esm.js';
import './js/utils';
import initTheme from './js/ui/theme.js';
import initPhoneInput from './js/phone-input.js';
import initBackToTop from './js/ui/back-to-top.js';

globalThis.bootstrap = { Tooltip, Toast, Modal, Collapse, Tab };

document.addEventListener('DOMContentLoaded', () => {
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
   * includes the TenantNinja layout so user toggles are persisted in localStorage
   * and the data-bs-theme attribute is kept in sync.
   */
  initTheme();
  initPhoneInput();
  initBackToTop();

  /**
   * Enable Bootstrap Tooltip
   */
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

  const showLinkedTab = () => {
    const id = window.location.hash.slice(1);
    const trigger = [...document.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"], [data-bs-toggle="list"]')]
      .find((tab) => (tab.getAttribute('data-bs-target') || tab.getAttribute('href')) === `#${id}`);
    if (trigger) {
      bootstrap.Tab.getOrCreateInstance(trigger).show();
    }
  };

  showLinkedTab();
  window.addEventListener('hashchange', showLinkedTab);
  document.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"], [data-bs-toggle="list"]').forEach((trigger) => {
    trigger.addEventListener('shown.bs.tab', function() {
      const target = this.getAttribute('data-bs-target') || this.getAttribute('href');
      if (target?.startsWith('#')) {
        history.replaceState({}, '', `${window.location.pathname}${window.location.search}${target}`);
      }
    });
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
    import('./js/tomselect.js')
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
