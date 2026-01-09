import './js/ui/modals';
import ClipboardJS from "clipboard";
import * as tabler from '@tabler/core/js/tabler.js';
import './js/tomselect'
import './js/datepicker'
import ApexCharts from 'apexcharts';
import './js/ui/theme_settings';
import './js/fossbilling';
import './js/utils';
import 'sortable-tablesort/dist/sortable.min.js';

globalThis.ApexCharts = ApexCharts;
globalThis.bootstrap = tabler.bootstrap;

document.addEventListener('DOMContentLoaded', () => {
  const colorPickerElement = document.getElementById('coloris-picker');
  if (colorPickerElement) {
    import('@melloware/coloris').then(module => {
      module.init();
      module.coloris({
        el: '#coloris-picker',
        alpha: false
      });
    }).catch(error => {
      console.error('Failed to load coloris:', error);
    });
  }
});


document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.js-theme-toggler').forEach(element => {
    element.addEventListener('click', event => {
      event.preventDefault();
      localStorage.setItem('theme', element.getAttribute('href').split('=')[1]);
      document.documentElement.setAttribute("data-bs-theme", localStorage.getItem('theme'))
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
