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
