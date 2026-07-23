// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
import TomSelect from 'tom-select';
import { getCSRFToken, getBaseURL } from './utils.ts';
globalThis.TomSelect = TomSelect;

const createTomSelectTemplate = (data, escape, options = {}) => {
  const { showIndicator = false, indicatorField = 'customProperties', labelField = 'text' } = options;

  let content = escape(data[labelField] || data.text || '');

  if (showIndicator && data[indicatorField]) {
    content = `<span class="dropdown-item-indicator">${data[indicatorField]}</span>${content}`;
  }

  return `<div>${content}</div>`;
};

export default function initTomSelectControls() {
  const localeSelectorEl = document.querySelector('.js-locale-selector');
  if (localeSelectorEl !== null) {
    const localeCookie = FOSSBilling.cookieNames?.locale || "fossbilling_locale";
    const selectedLang = FOSSBilling.cookieRead(localeCookie) || localeSelectorEl.value;
    const localeSelector = new TomSelect(".js-locale-selector", {
      copyClassesToDropdown: false,
      controlClass: "ts-control locale",
      dropdownClass: "dropdown-menu ts-dropdown locale-selector-dropdown",
      optionClass: "dropdown-item",
      controlInput: false,
      items: selectedLang ? [selectedLang] : [],
      render: {
        item: (data, escape) => createTomSelectTemplate(data, escape, { showIndicator: true }),
        option: (data, escape) => createTomSelectTemplate(data, escape, { showIndicator: true }),
      },
    });

    localeSelector.on("change", (value) => {
      FOSSBilling.cookieCreate(localeCookie, value, 365);
      window.location.reload();
    });
  }


  const autocompleteTemplate = (item, escape) => {
    return `<div class="py-2 d-flex align-items-center text-body">
                <span>${escape(item.label)}</span>
                <small class="text-body-secondary ms-1 lh-1">#${escape(item.value)}</small>
             </div>`;
  };

  const autocompleteSelectorEls = document.querySelectorAll('.autocomplete-selector');
  if (autocompleteSelectorEls.length > 0) {
    autocompleteSelectorEls.forEach((autocompleteSelectorEl) => {
      // Skip if required data attributes are missing
      if (!autocompleteSelectorEl.dataset.resturl) {
        console.warn('Autocomplete selector missing required data-resturl attribute');
        return;
      }

      const autocompleteSelector = new TomSelect(autocompleteSelectorEl, {
        copyClassesToDropdown: false,
        dropdownClass: "dropdown-menu ts-dropdown",
        optionClass: "dropdown-item",
        valueField: "value",
        labelField: "label",
        searchField: ["label", "value"],
        load: (query, callback) => {
          try {
            const restUrl = new URL(getBaseURL(autocompleteSelectorEl.dataset.resturl));
            restUrl.searchParams.append("search", query);
            restUrl.searchParams.append("per_page", 5);

            fetch(restUrl, {
              headers: {
                'X-CSRF-Token': getCSRFToken() || '',
              }
            })
              .then((response) => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
              })
              .then((json) => {
                const items = Object.entries(json.result || {}).map(([key, value]) => ({
                  label: value,
                  value: key
                }));
                callback(items);
              })
              .catch(error => {
                console.error('Autocomplete fetch error:', error);
                callback([]);
              });
          } catch (error) {
            console.error('Autocomplete URL error:', error);
            callback([]);
          }
        },
        render: {
          option: (item, escape) => autocompleteTemplate(item, escape),
          item: (item, escape) => `<span>${escape(item.label)}</span>`,
        },
      });

      autocompleteSelector.wrapper.classList.add('autocomplete-selector-wrapper');
    });
  }


  document.querySelectorAll('.canned_response_select').forEach((selectEl) => {
    new TomSelect(selectEl, {
      controlInput: false,
      maxItems: 1,
      render: {
        item: (data, escape) => `<span>${escape(data.text || '')}</span>`,
        option: (data, escape) => createTomSelectTemplate(data, escape),
      },
    });
  });

  const cannedResponseSelectorEl = document.querySelector('.canned_ticket_response');
  if (cannedResponseSelectorEl !== null && cannedResponseSelectorEl.dataset.resturl) {
    const cannedResponseSelector = cannedResponseSelectorEl.tomselect;

    cannedResponseSelector?.on('change', (value) => {
      if (!value) return;

      try {
        // Validate we have the required data attribute
        const restUrlPath = cannedResponseSelectorEl.dataset.resturl;
        if (!restUrlPath) {
          console.error('Canned response selector missing required data-resturl attribute');
          return;
        }

        // Build URL safely
        let finalUrl;
        try {
          // Try direct URL construction first
          finalUrl = new URL(restUrlPath);
        } catch (e) {
          // Fallback: treat as relative URL
          if (!restUrlPath.startsWith('/')) {
            finalUrl = new URL('/' + restUrlPath, window.location.origin);
          } else {
            finalUrl = new URL(restUrlPath, window.location.origin);
          }
        }

        finalUrl.searchParams.append('id', value);

        fetch(finalUrl, {
          headers: {
            'X-CSRF-Token': getCSRFToken() || '',
          }
        })
          .then((response) => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
          })
          .then((json) => {
            const content = json.result?.content || '';

            // Try element-based approach first: look for active editor
            const activeEditorElement = document.activeElement.closest('[data-editor]');
            if (activeEditorElement?.editor?.setData) {
              activeEditorElement.editor.setData(content);
              return;
            }

            if (window.FOSSBilling?.editor) {
              window.FOSSBilling.editor.all().forEach(editor => {
                editor.setData(content);
              });
              return;
            }

            document.querySelectorAll('[data-editor]').forEach(el => {
              if (el.editor?.setData) {
                el.editor.setData(content);
              }
            });
          })
          .catch(error => {
            console.error('Canned response fetch error:', error);
          });
      } catch (error) {
        console.error('Canned response error:', error);
      }
    });
  }

}
