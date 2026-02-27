import './js/ui/modals';
import { initAvatars } from './js/avatar.js';
import { coloris, init } from '@melloware/coloris';
import * as tabler from '@tabler/core/js/tabler.js';
import './js/tomselect'
import './js/datepicker'
import ApexCharts from 'apexcharts';
import './js/ui/theme_settings';
import './js/fossbilling';
import 'sortable-tablesort/dist/sortable.min.js';

globalThis.ApexCharts = ApexCharts;
globalThis.bootstrap = tabler.bootstrap;

init();
coloris({
  el: '#coloris-picker',
  alpha: false
});


document.addEventListener('DOMContentLoaded', () => {
  initAvatars();

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
  tooltipTriggerList.forEach(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl, {
      'trigger': 'hover'
    })
  });


  /**
   * Copy To Clipboard
   */
  document.addEventListener('click', async function(event) {
    const button = event.target.closest('.clipboard-copy');
    if (!button) return;

    const targetSelector = button.dataset.clipboardTarget;
    const targetElement = document.querySelector(targetSelector);
    if (!targetElement) return;

    const text = targetElement.value || targetElement.textContent || targetElement.innerText;
    let success = false;

    if (navigator.clipboard?.writeText) {
      try {
        await navigator.clipboard.writeText(text);
        success = true;
      } catch (err) {
        // Fall through to legacy fallback
      }
    }

    if (!success) {
      let textarea;
      try {
        textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        success = document.execCommand('copy');
      } catch (err) {
        // Fall through to error
      } finally {
        if (textarea && textarea.parentNode) {
          textarea.parentNode.removeChild(textarea);
        }
      }
    }

    if (success) {
      let tooltip = bootstrap.Tooltip.getInstance(button);
      if (!tooltip) {
        tooltip = new bootstrap.Tooltip(button, {
          trigger: 'manual'
        });
      }

      const originalTitle = button.dataset.bsOriginalTitle;
      button.dataset.bsOriginalTitle = 'Copied';
      tooltip.show();
      setTimeout(() => {
        button.dataset.bsOriginalTitle = originalTitle;
        tooltip.hide();
      }, 2000);
    } else {
      if (typeof FOSSBilling !== 'undefined' && FOSSBilling.message) {
        FOSSBilling.message('Failed to copy to clipboard', 'error');
      }
    }
  });
});
