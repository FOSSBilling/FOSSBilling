import './scss/huraga.scss';

import $ from 'jquery';
import * as bootstrap from 'bootstrap';
import '../../admin_default/assets/js/tomselect';
import '../../admin_default/assets/js/fossbilling';

globalThis.$ = globalThis.jQuery = $;
globalThis.bootstrap = bootstrap;

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
        bb.redirect();
      } else if (typeof reload === 'string') {
        bb.redirect(reload);
      }
    }
  }
  flashMessage({});
});

