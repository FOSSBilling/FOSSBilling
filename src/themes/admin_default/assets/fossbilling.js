import './scss/fossbilling.scss';

import './js/sprite';
import $ from 'jquery';
import 'jquery.browser'; // Temporary package until the removal of jquery.alerts
import './js/ui/jquery.alerts';
import './js/ui/modals';
import "@melloware/coloris/dist/coloris.css";
import { coloris, init } from '@melloware/coloris';
import '@tabler/core/src/js/tabler';
import './js/tomselect'
import './js/datepicker'
import ApexCharts from 'apexcharts';
import './js/fossbilling';

globalThis.ApexCharts = ApexCharts;
globalThis.$ = globalThis.jQuery = $;

init();
coloris({
  el: '#coloris-picker',
  alpha: false,
  themeMode: localStorage.getItem('theme')
});

document.addEventListener('DOMContentLoaded', () => {

  if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('theme-dark');
  }
  document.querySelectorAll('.js-theme-toggler').forEach(element => {
    element.addEventListener('click', event => {
      event.preventDefault();
      document.body.classList.toggle('theme-dark');
      localStorage.setItem('theme', element.getAttribute('href').split('=')[1]);
    });
  });

});
