import TomSelect from 'tom-select';
import axios from 'axios';

globalThis.TomSelect = TomSelect;

document.addEventListener('DOMContentLoaded', () => {

  /**
   * Locale Selector
   */
  const localeSelectorTemplate = (data, escape) => {
    if (data.customProperties) {
      return `<div><span class="dropdown-item-indicator">${data.customProperties}</span>${escape(data.text)}</div>`;
    }
    return `<div>${escape(data.text)}</div>`;
  }
  let localeSelector = new TomSelect('.js-language-selector', {
    copyClassesToDropdown: false,
    controlClass: 'ts-control locale',
    dropdownClass: 'dropdown-menu ts-dropdown',
    optionClass: 'dropdown-item',
    controlInput: false,
    items: [bb.cookieRead('BBLANG')],
    render: {
      item: (data, escape) => {
        return localeSelectorTemplate(data, escape);
      },
      option: (data, escape) => {
        return localeSelectorTemplate(data, escape);
      },
    },
  });
  localeSelector.on('change', (value) => {
    bb.cookieCreate('BBLANG', value, 7);
    bb.reload();
  })


  /**
   * Autocomplete selector
   */
  const autocompleteTemplate = (item, escape) => {
    return `<div class="py-2 d-flex">
                <span>${escape(item.label)}</span>
             </div>`;
  }
  let autocompleteSelectorEl = document.querySelector('.autocomplete-selector');
  if (autocompleteSelectorEl !== null) {
    new TomSelect('.autocomplete-selector', {
      copyClassesToDropdown: false,
      dropdownClass: 'dropdown-menu ts-dropdown',
      optionClass: 'dropdown-item',
      valueField: 'value',
      labelField: 'label',
      searchField: 'label',
      load: (query, callback) => {
        let items;
        axios({
          url: bb.restUrl(autocompleteSelectorEl.dataset.resturl),
          params: {
            per_page: 5,
            search: query,
            CSRFToken: autocompleteSelectorEl.dataset.csrf
          },
        }).then(function (response) {
          items = Object.entries(response.data.result).map(([key, value]) => {
            return {label: value, value: key}
          });
          callback(items);
        });
      },
      render: {
        option: function (item, escape) {
          return autocompleteTemplate(item, escape)
        },
        item: function (item, escape) {
          return `<span>${escape(item.label)}</span>`;
        }
      }
    });
  }

});
