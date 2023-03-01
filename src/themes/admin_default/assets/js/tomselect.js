import TomSelect from "tom-select";

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

