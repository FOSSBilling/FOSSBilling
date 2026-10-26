// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
import { formatCurrencyAmount } from './currency-format.mts';
import {
  EDITOR_CRASH_LIMIT,
  EDITOR_CRASH_WINDOW_MS,
  normalizeEditor,
  readElementData,
  recordEditorCrash,
  writeElementData,
} from './editor-helpers.mts';

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
  // Per-element crash-recovery bookkeeping: adapter, options, crash history,
  // and the unregister function for the adapter's error reporting.
  const crashRecords = new WeakMap();
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

  FOSSBilling.currency = Object.assign({
    format: formatCurrencyAmount,
  }, FOSSBilling.currency || {});

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

  function syncElementData(element, editor) {
    writeElementData(element, editor.getData());
  }

  function registerEditorInstance(element, adapter, options, rawEditor) {
    const editor = normalizeEditor(element, rawEditor);
    // Normal destruction must also drop the crash-recovery bookkeeping, so
    // the page-level error registry does not retain the editor.
    const destroy = editor.destroy;
    editor.destroy = () => {
      crashRecords.get(element)?.off?.();
      crashRecords.delete(element);
      return destroy();
    };

    syncElementData(element, editor);
    editor.onChange(() => syncElementData(element, editor));

    editorsByElement.set(element, editor);
    if (editor.name) {
      editorsByName.set(editor.name, editor);
    }

    element.editor = editor;
    element.setAttribute('data-editor', 'true');

    attachCrashRecovery(element, adapter, options, rawEditor);

    return editor;
  }

  function attachCrashRecovery(element, adapter, options, rawEditor) {
    if (typeof adapter.onFatalError !== 'function') {
      return;
    }

    const previous = crashRecords.get(element);
    previous?.off?.();

    const record = {
      adapter,
      options,
      // Crash history survives recreations of the same element; a fresh
      // registration after a permanent lock starts over.
      times: previous && !previous.locked ? previous.times : [],
      recovering: false,
      locked: false,
      off: null,
    };

    record.off = adapter.onFatalError(rawEditor, (error) => {
      handleFatalError(element, error);
    });

    crashRecords.set(element, record);
  }

  function handleFatalError(element, error) {
    const record = crashRecords.get(element);
    if (!record || record.recovering || record.locked) {
      return;
    }

    const decision = recordEditorCrash(record.times, Date.now(), {
      limit: EDITOR_CRASH_LIMIT,
      windowMs: EDITOR_CRASH_WINDOW_MS,
    });
    record.times = decision.times;

    if (!decision.shouldRecover) {
      record.locked = true;
      record.off?.();
      record.adapter.lock?.(editorsByElement.get(element)?.raw);
      console.error(
        'FOSSBilling editor crashed repeatedly and is now read-only. Its content is kept in the form field - reload the page to get a fresh editor.',
        error,
      );
      return;
    }

    record.recovering = true;
    recreateEditor(element, record)
      .catch((recoveryError) => {
        record.locked = true;
        record.off?.();
        console.error(
          'FOSSBilling editor recovery failed. Its last content is kept in the form field - reload the page to get a fresh editor.',
          recoveryError,
        );
      })
      .finally(() => {
        record.recovering = false;
      });
  }

  async function recreateEditor(element, record) {
    // Read the content from the synced form element, not editor.getData():
    // the element holds the last known-good copy of the content.
    const previous = editorsByElement.get(element);
    const data = readElementData(element);

    try {
      await previous?.raw?.destroy?.();
    } catch (destroyError) {
      console.error('FOSSBilling editor could not destroy the crashed instance:', destroyError);
    }

    // The destroyed editor may write its own state back to the element
    // (updateSourceElementOnDestroy), so restore the captured copy.
    writeElementData(element, data);

    const rawEditor = await record.adapter.create(element, { ...record.options, initialData: data });

    // Reuse the existing wrapper so references callers hold stay valid;
    // replaceRaw() re-attaches their change listeners to the new instance.
    if (previous) {
      previous.replaceRaw(rawEditor);
      syncElementData(element, previous);
      attachCrashRecovery(element, record.adapter, record.options, rawEditor);
      return previous;
    }

    return registerEditorInstance(element, record.adapter, record.options, rawEditor);
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

      return registerEditorInstance(element, adapter, options, rawEditor);
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
