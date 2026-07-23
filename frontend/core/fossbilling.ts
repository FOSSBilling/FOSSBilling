// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
/**
 * FOSSBilling browser runtime.
 *
 * This file contains browser platform APIs shared by themes and modules.
 */
(function (window, document) {
  'use strict';

  const FOSSBilling = window.FOSSBilling || {};
  const readyCallbacks = [];
  const editorsByElement = new WeakMap();
  const editorsByName = new Map();
  const adapters = new Map();
  const cookieNames = Object.freeze({
    locale: 'fossbilling_locale',
    timezone: 'fossbilling_timezone',
  });

  function runReadyCallback(callback) {
    try {
      callback(FOSSBilling);
    } catch (error) {
      console.error('FOSSBilling ready callback failed:', error);
    }
  }

  FOSSBilling.ready = function (callback) {
    if (typeof callback !== 'function') {
      return;
    }

    if (document.readyState === 'loading') {
      readyCallbacks.push(callback);
      return;
    }

    runReadyCallback(callback);
  };

  document.addEventListener('DOMContentLoaded', function () {
    while (readyCallbacks.length > 0) {
      runReadyCallback(readyCallbacks.shift());
    }
    migrateCookie(cookieNames.locale, 'fb_locale', 365);
    initTimezone();
  });

  FOSSBilling.ui = Object.assign({
    notify(message, type = 'info') {
      if (typeof FOSSBilling.message === 'function') {
        FOSSBilling.message(message, type);
        return;
      }

      if (type === 'error') {
        console.error(message);
        return;
      }

      console.info(message);
    },
  }, FOSSBilling.ui || {});

  FOSSBilling.cookieCreate = FOSSBilling.cookieCreate || function (name, value, days) {
    let expires = '';

    if (days) {
      const date = new Date();
      date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
      expires = `; expires=${date.toUTCString()}`;
    }

    document.cookie = `${name}=${value}${expires}; path=/`;
  };

  FOSSBilling.cookieRead = FOSSBilling.cookieRead || function (name) {
    const nameEQ = `${name}=`;
    const cookies = document.cookie.split(';');

    for (let i = 0; i < cookies.length; i++) {
      let cookie = cookies[i];
      while (cookie.charAt(0) === ' ') {
        cookie = cookie.substring(1);
      }
      if (cookie.indexOf(nameEQ) === 0) {
        return cookie.substring(nameEQ.length);
      }
    }

    return null;
  };

  function migrateCookie(name, legacyName, days) {
    const currentValue = FOSSBilling.cookieRead(name);
    const legacyValue = FOSSBilling.cookieRead(legacyName);

    if (!currentValue && legacyValue) {
      FOSSBilling.cookieCreate(name, legacyValue, days);
    }
    if (legacyValue !== null) {
      FOSSBilling.cookieCreate(legacyName, '', -1);
    }

    return currentValue || legacyValue;
  }

  FOSSBilling.cookieNames = cookieNames;

  // Returns the IANA timezone identifier the browser is running in, or null.
  // Used by signup and the public layout so guests see dates in their own zone.
  FOSSBilling.detectTimezone = FOSSBilling.detectTimezone || function () {
    try {
      const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (typeof tz === 'string' && tz.length > 0) {
        return tz;
      }
    } catch (error) {
      // Intl not available - silently fall through.
    }

    return null;
  };

  // Seeds the `fossbilling_timezone` cookie and pre-selects the detected timezone on any
  // `<select data-timezone-select>` that doesn't already have a value. Lets the
  // server pre-fill a stored timezone without being clobbered by the auto-detect.
  function initTimezone() {
    const detected = FOSSBilling.detectTimezone();
    if (!detected) {
      return;
    }

    if (!migrateCookie(cookieNames.timezone, 'fb_timezone', 365)) {
      FOSSBilling.cookieCreate(cookieNames.timezone, detected, 365);
    }

    const selects = document.querySelectorAll('select[data-timezone-select]');
    selects.forEach(function (select) {
      if (select.value) {
        return;
      }

      const option = Array.from(select.options).find(function (opt) {
        return opt.value === detected;
      });
      if (option) {
        select.value = detected;
      }
    });
  }

  FOSSBilling.initTimezone = initTimezone;

  function getEditorName(element) {
    return element.getAttribute('name') || element.id || null;
  }

  function syncElementData(element, editor) {
    const data = editor.getData();
    if ('value' in element) {
      element.value = data;
    } else {
      element.textContent = data;
    }
  }

  function normalizeEditor(element, editor) {
    if (!editor || typeof editor.getData !== 'function' || typeof editor.setData !== 'function') {
      throw new Error('Editor adapters must return getData() and setData() methods.');
    }

    return {
      raw: editor.raw || editor,
      getData: () => editor.getData(),
      setData: (value) => editor.setData(value),
      focus: () => {
        if (typeof editor.focus === 'function') {
          editor.focus();
          return;
        }

        if (editor.editing?.view?.focus) {
          editor.editing.view.focus();
        }
      },
      destroy: () => {
        if (typeof editor.destroy === 'function') {
          return editor.destroy();
        }

        return Promise.resolve();
      },
      onChange: (callback) => {
        if (typeof editor.onChange === 'function') {
          return editor.onChange(callback);
        }

        if (editor.model?.document?.on) {
          editor.model.document.on('change:data', callback);
        }
      },
      element,
      name: getEditorName(element),
      required: element.dataset.editorRequired === 'true',
    };
  }

  FOSSBilling.editor = Object.assign({
    registerAdapter(name, adapter) {
      if (!name || !adapter || typeof adapter.create !== 'function') {
        throw new Error('Editor adapter registration requires a name and create() method.');
      }

      adapters.set(name, adapter);
    },

    async create(element, options = {}) {
      if (!element) {
        throw new Error('Cannot initialize an editor without an element.');
      }

      const adapterName = options.adapter || 'ckeditor';
      const adapter = adapters.get(adapterName);
      if (!adapter) {
        throw new Error(`Editor adapter "${adapterName}" is not registered.`);
      }

      if (element.hasAttribute('required')) {
        element.dataset.editorRequired = 'true';
        element.removeAttribute('required');
      }

      const rawEditor = await adapter.create(element, options);
      const editor = normalizeEditor(element, rawEditor);

      syncElementData(element, editor);
      editor.onChange(() => syncElementData(element, editor));

      editorsByElement.set(element, editor);
      if (editor.name) {
        editorsByName.set(editor.name, editor);
      }

      element.editor = editor;
      element.setAttribute('data-editor', 'true');

      return editor;
    },

    init(selector, options = {}) {
      document.querySelectorAll(selector).forEach((element) => {
        if (editorsByElement.has(element)) {
          return;
        }

        FOSSBilling.editor.create(element, options).catch((error) => {
          console.error('Editor initialization error:', error);
        });
      });
    },

    get(name) {
      return editorsByName.get(name) || null;
    },

    getForElement(element) {
      return editorsByElement.get(element) || null;
    },

    all() {
      return Array.from(editorsByName.values());
    },

    syncForm(form, formData) {
      const formElements = new Set(Array.from(form.elements || []));

      editorsByName.forEach((editor, name) => {
        if (!formElements.has(editor.element)) {
          return;
        }

        syncElementData(editor.element, editor);
        formData.set(name, editor.getData());
      });
    },

    validateForm(form) {
      const formElements = new Set(Array.from(form.elements || []));

      for (const editor of editorsByName.values()) {
        if (!formElements.has(editor.element)) {
          continue;
        }

        if (editor.required && editor.getData().trim() === '') {
          return false;
        }
      }

      return true;
    },
  }, FOSSBilling.editor || {});

  window.FOSSBilling = FOSSBilling;
}(window, document));
