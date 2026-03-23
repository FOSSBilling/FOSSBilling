import { Tooltip, Toast, Modal, Collapse } from 'bootstrap/dist/js/bootstrap.esm.js';
import './js/utils';
import { initAvatars } from './js/avatar.js';

globalThis.bootstrap = { Tooltip, Toast, Modal, Collapse };

document.addEventListener('DOMContentLoaded', () => {
  initAvatars();

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
   * Enable Bootstrap Tooltip
   */
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

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
      API.guest.post('cart/set_currency', {currency: select.value}, function(response) {
        location.reload()
      }, function(error) {
        FOSSBilling.message(error)
      });
    });
  });

  /**
   * Lazy load Tom Select only if language selector exists
   * Includes error handling and ensures CSS is loaded before JS initializes
   */
  const languageSelector = document.querySelector('.js-language-selector');
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

  // Attach event listeners to all forms and links with data-fb-api attribute.
  if (document.querySelector("form[data-fb-api]")) {
    API._apiForm();
  };
  if (document.querySelector("a[data-fb-api]")) {
    API._apiLink();
  }
});
