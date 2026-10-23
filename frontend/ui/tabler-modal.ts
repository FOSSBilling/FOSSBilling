export type DialogInstance = {
  show(): void;
  hide(): void;
  dispose(): void;
};

type ModalLibrary = {
  getInstance(element: HTMLElement): DialogInstance | null;
};

type DialogOptions = {
  confirmButton?: HTMLButtonElement | HTMLAnchorElement | null;
  initialFocus?: HTMLElement | null;
  onConfirm?: (value?: string) => void;
  onCancel?: () => void;
  onClose?: () => void;
  // Admin extensions historically run action callbacks immediately; client API
  // dialogs wait until hidden so errors and subsequent dialogs can receive focus.
  callbackTiming?: 'action' | 'hidden';
};

/** Tabler lifecycle shared by the themes; rendering and extension APIs stay local. */
export function bindModalLifecycle(
  element: HTMLElement,
  instance: DialogInstance,
  library: ModalLibrary,
  options: DialogOptions,
) {
  const origin = document.activeElement as HTMLElement | null;
  const current = document.querySelector<HTMLElement>('.modal.show');
  const previous = current ? library.getInstance(current) : null;
  let ready = false;
  let closed = false;
  let action: 'confirm' | 'cancel' | null = null;
  let value: string | undefined;

  const disableConfirm = (disabled: boolean) => {
    const button = options.confirmButton;
    if (!button) return;
    if ('disabled' in button) button.disabled = disabled;
    else button.setAttribute('aria-disabled', String(disabled));
  };
  disableConfirm(true);

  element.addEventListener('shown.bs.modal', () => {
    ready = true;
    disableConfirm(false);
    (options.initialFocus || element).focus();
  }, {once: true});

  element.addEventListener('hidden.bs.modal', () => {
    closed = true;
    ready = false;
    instance.dispose();
    element.remove();

    if (previous && current?.isConnected && action !== 'confirm') {
      current.addEventListener('shown.bs.modal', () => {
        if (origin?.isConnected) origin.focus();
      }, {once: true});
      previous.show();
    } else if (origin?.isConnected && origin.getClientRects().length) {
      origin.focus();
    }

    try {
      if (options.callbackTiming !== 'action') {
        if (action === 'confirm') options.onConfirm?.(value);
        else if (action === 'cancel') options.onCancel?.();
      }
    } finally {
      options.onClose?.();
    }
  }, {once: true});

  function settle(next: 'confirm' | 'cancel', result?: string): void {
    if (!ready || action) return;
    action = next;
    value = result;
    disableConfirm(true);
    try {
      if (options.callbackTiming === 'action') {
        if (next === 'confirm') options.onConfirm?.(value);
        else options.onCancel?.();
      }
    } finally {
      instance.hide();
    }
  }

  return {
    confirm: (result?: string) => settle('confirm', result),
    cancel: () => settle('cancel'),
    show() {
      // Tabler supports one visible modal. Resume the underlying dialog on
      // dismissal, but leave it closed when its action has been confirmed.
      if (previous && current) {
        current.addEventListener('hidden.bs.modal', () => {
          if (!closed) instance.show();
        }, {once: true});
        previous.hide();
      } else instance.show();
    },
  };
}

let nextTitleId = 0;

/** Add dialog names without replacing extension-supplied IDs or descriptions. */
export function labelModal(element: HTMLElement): void {
  const title = element.querySelector<HTMLElement>('.modal-title, .modal-body h3');
  if (title && !element.hasAttribute('aria-labelledby')) {
    title.id ||= `fb-api-dialog-${++nextTitleId}`;
    element.setAttribute('aria-labelledby', title.id);
  }
  const input = element.querySelector<HTMLInputElement>('#prompt-input');
  const label = element.querySelector<HTMLLabelElement>('label');
  if (input && label && !label.htmlFor) label.htmlFor = input.id;
}

export function hideModals(library: ModalLibrary): void {
  document.querySelectorAll<HTMLElement>('.modal.show').forEach(element => library.getInstance(element)?.hide());
}
