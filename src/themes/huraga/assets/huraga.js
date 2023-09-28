import './scss/huraga.scss';

import $ from 'jquery';
import * as bootstrap from 'bootstrap';
import '../../admin_default/assets/js/tomselect';
import '../../admin_default/assets/js/fossbilling';

globalThis.$ = globalThis.jQuery = $;
globalThis.bootstrap = bootstrap;

/**
 * Enable Bootstrap Tooltip
 */
document.addEventListener('DOMContentLoaded', () => {
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
});
