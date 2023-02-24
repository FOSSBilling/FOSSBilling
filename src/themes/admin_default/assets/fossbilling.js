import './scss/fossbilling.scss';

import './js/sprite';
import './js/jquery.min';
import './js/ui/jquery.alerts';
import 'spectrum-colorpicker/spectrum';
import './js/forms/forms';
import './js/jquery.scrollTo-min';
import './js/jquery-ui';
import '@tabler/core/src/js/tabler';
import TomSelect from 'tom-select';
import ApexCharts from 'apexcharts';
import './js/fossbilling';

globalThis.ApexCharts = ApexCharts;
globalThis.TomSelect = TomSelect;

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

  const tomSelectTemplate = (data, escape) => {
    if (data.customProperties) {
      return '<div><span class="dropdown-item-indicator">' + data.customProperties + '</span>' + escape(data.text) + '</div>';
    }
    return '<div>' + escape(data.text) + '</div>';
  }
  let localeSelector = new TomSelect('.js-language-selector', {
    copyClassesToDropdown: false,
    dropdownClass: 'dropdown-menu ts-dropdown',
    optionClass: 'dropdown-item',
    controlInput: false,
    items: [bb.cookieRead('BBLANG')],
    render: {
      item: (data, escape) => { return tomSelectTemplate(data, escape); },
      option: (data, escape) => { return tomSelectTemplate(data, escape); },
    },
  });
  localeSelector.on('change', (value) => {
    bb.cookieCreate('BBLANG', value, 7);
    bb.reload();
  })

});
