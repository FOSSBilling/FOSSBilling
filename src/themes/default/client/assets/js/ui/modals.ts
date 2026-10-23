import { Modal } from '@tabler/core';
import { bindModalLifecycle, hideModals, labelModal } from '../../../../../../../frontend/ui/tabler-modal.ts';

type ClientModalOptions = {
  type?: string;
  title?: string;
  content?: string;
  label?: string;
  value?: string;
  confirmButton?: string;
  confirmButtonColor?: string;
  confirmCallback?: () => void;
  promptConfirmCallback?: (value: string) => void;
  cancelButton?: string;
  closeButton?: string;
  cancelCallback?: () => void;
  closeCallback?: () => void;
};

let nextDialogId = 0;
const modalLibrary = {getInstance: (element: HTMLElement) => Modal.getInstance(element) as Modal | null};

function createDialogContent(options: ClientModalOptions, labels: DOMStringMap, prompt: boolean) {
  const element = document.createElement('div');
  element.className = 'modal fade';
  element.dataset.clientDialog = '';
  element.tabIndex = -1;
  element.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title"></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><form class="client-modal-form"><div class="modal-body text-break"></div><div class="modal-footer"></div></form></div></div>';
  const title = element.querySelector<HTMLElement>('.modal-title')!;
  title.id = `client-api-dialog-${++nextDialogId}`;
  title.textContent = options.title || labels.clientConfirm || 'Confirm';
  labelModal(element);
  element.querySelector('.btn-close')!.setAttribute('aria-label', labels.clientClose || 'Close');
  const body = element.querySelector<HTMLElement>('.modal-body')!;
  if (options.content) {
    const content = document.createElement('p');
    content.className = 'mb-0';
    content.textContent = options.content;
    content.id = `${title.id}-description`;
    element.setAttribute('aria-describedby', content.id);
    body.append(content);
  }
  let input: HTMLInputElement | undefined;
  if (prompt) {
    const label = document.createElement('label');
    label.className = 'form-label';
    label.htmlFor = `${title.id}-input`;
    label.textContent = options.label || options.title || '';
    input = document.createElement('input');
    input.className = 'form-control';
    input.id = label.htmlFor;
    input.value = options.value || '';
    body.append(label, input);
  }
  return {element, input};
}

function createDialogActions(element: HTMLElement, options: ClientModalOptions, labels: DOMStringMap, type: string, confirm: boolean) {
  const footer = element.querySelector<HTMLElement>('.modal-footer')!;
  const cancel = document.createElement('button');
  cancel.type = 'button';
  cancel.className = 'btn btn-outline-secondary';
  cancel.textContent = confirm ? options.cancelButton || labels.clientCancel || 'Cancel' : options.closeButton || labels.clientClose || 'Close';
  footer.append(cancel);
  let button: HTMLButtonElement | null = null;
  if (confirm) {
    button = document.createElement('button');
    button.type = 'submit';
    const color = options.confirmButtonColor || (type === 'danger' ? 'danger' : 'primary');
    button.className = `btn btn-${['primary', 'secondary', 'danger', 'success', 'warning'].includes(color) ? color : 'primary'}`;
    button.textContent = options.confirmButton || labels.clientConfirm || 'Confirm';
    footer.append(button);
  }
  return {cancel, button};
}

// Keep a library supplied by an extension; otherwise give API links Tabler dialogs.
export default function initModals(): void {
  if (window.Modals?.create) return;
  const labels = document.querySelector<HTMLElement>('.toast-container')?.dataset || {};

  window.Modals = {
    create(typeOrOptions: string | ClientModalOptions, supplied: ClientModalOptions = {}) {
      const pending = document.querySelector<HTMLElement>('[data-client-dialog]');
      if (pending) return Modal.getInstance(pending);
      const options = typeof typeOrOptions === 'string' ? {...supplied, type: typeOrOptions} : typeOrOptions;
      const type = options.type || 'confirm';
      const prompt = type === 'prompt';
      const confirm = prompt || ['confirm', 'small-confirm', 'danger', 'success'].includes(type);
      const {element, input} = createDialogContent(options, labels, prompt);
      const {cancel, button} = createDialogActions(element, options, labels, type, confirm);
      document.body.append(element);
      const instance = Modal.getOrCreateInstance(element) as Modal;
      const lifecycle = bindModalLifecycle(element, instance, modalLibrary, {
        confirmButton: button,
        initialFocus: input || cancel,
        onConfirm: value => {
          if (prompt) options.promptConfirmCallback?.(value!);
          else options.confirmCallback?.();
        },
        onCancel: () => options.cancelCallback?.(),
        onClose: () => options.closeCallback?.(),
      });
      cancel.addEventListener('click', () => lifecycle.cancel());
      element.querySelector('form')!.addEventListener('submit', event => {
        event.preventDefault();
        if (confirm) lifecycle.confirm(input?.value);
      });
      lifecycle.show();
      return instance;
    },
    hideAll() {
      hideModals(modalLibrary);
    },
  };
}
