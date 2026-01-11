/**
 * Huraga theme TomSelect setup - lazy loaded only when needed
 */

import TomSelect from 'tom-select';

globalThis.TomSelect = TomSelect;

export default function initLanguageSelector() {
  const localeSelectorEl = document.querySelector('.js-language-selector');
  if (localeSelectorEl === null) {
    return;
  }

  const localeSelectorTemplate = (data, escape) => {
    if (data.customProperties) {
      return `<div><span class="dropdown-item-indicator">${data.customProperties}</span>${escape(data.text)}</div>`;
    }
    return `<div>${escape(data.text)}</div>`;
  };

  let localeSelector = new TomSelect(".js-language-selector", {
    copyClassesToDropdown: false,
    controlClass: "ts-control locale",
    dropdownClass: "dropdown-menu ts-dropdown locale-selector-dropdown",
    optionClass: "dropdown-item",
    controlInput: false,
    items: [getCookie("BBLANG")],
    render: {
      item: (data, escape) => {
        return localeSelectorTemplate(data, escape);
      },
      option: (data, escape) => {
        return localeSelectorTemplate(data, escape);
      },
    },
  });

  localeSelector.on("change", (value) => {
    setCookie("BBLANG", value, 365);
    window.location.reload();
  });
}

function getCookie(name) {
  var nameEQ = name + "=";
  var ca = document.cookie.split(";");
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) == " ") c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
  }
  return null;
}

function setCookie(name, value, days) {
  if (days) {
    var date = new Date();
    date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
    var expires = "; expires=" + date.toGMTString();
  } else var expires = "";
  document.cookie = name + "=" + value + expires + "; path=/ ";
}
