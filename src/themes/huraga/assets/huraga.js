import { Tooltip, Toast } from 'bootstrap';
import './js/tomselect';
import './js/utils';

globalThis.bootstrap = { Tooltip, Toast };

document.addEventListener('DOMContentLoaded', () => {
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
  }
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
   */
  const languageSelector = document.querySelector('.js-language-selector');
  if (languageSelector) {
    import('./js/tomselect.js').then(module => {
      module.default();
    });
  }
});
