import "@melloware/coloris/dist/coloris.css";
import './scss/fossbilling.scss';

import './js/sprite';
import $ from 'jquery';
import './js/ui/modals';
import { coloris, init } from '@melloware/coloris';
import ClipboardJS from "clipboard";
import '@tabler/core/src/js/tabler';
import './js/tomselect'
import './js/datepicker'
import ApexCharts from 'apexcharts';
import './js/ui/theme_settings';
import './js/fossbilling';
import 'sortable-tablesort/sortable.min.js';

globalThis.ApexCharts = ApexCharts;
globalThis.$ = globalThis.jQuery = $;

init();
coloris({
  el: '#coloris-picker',
  alpha: false
});


document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.js-theme-toggler').forEach(element => {
    element.addEventListener('click', event => {
      event.preventDefault();
      localStorage.setItem('theme', element.getAttribute('href').split('=')[1]);
      document.body.setAttribute("data-bs-theme", localStorage.getItem('theme'))
    });
  });


  /**
   * Enable Bootstrap Tooltip
   */
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl, {
      'trigger': 'hover'
    })
  });


  /**
   * Copy To Clipboard
   */
  const clipboard = new ClipboardJS('.clipboard-copy');
  clipboard.on('success', function (e) {
    let originalTitle = e.trigger.dataset.bsOriginalTitle;
    let tooltip = bootstrap.Tooltip.getInstance(e.trigger);
    e.trigger.dataset.bsOriginalTitle = 'Copied'
    tooltip.show();
    setTimeout(() => {
      e.trigger.dataset.bsOriginalTitle = originalTitle
      tooltip.hide();
    }, 2000);
  })
});
