type ToastOptions = {
  title: string;
  closeLabel: string;
  autohide?: boolean;
  assertive?: boolean;
  restoreFocus?: boolean;
  announce?: (message: string, type: string) => void;
};

/** Shared Tabler presentation; callers retain their announcement and dismissal policy. */
export function showToast(message: string, type: string, options: ToastOptions): void {
  const container = document.querySelector<HTMLElement>('.toast-container');
  if (!container) {
    console.warn('Toast container not found for FOSSBilling.message()');
    return;
  }

  const colors: Record<string, string> = {error: 'danger', warning: 'warning', success: 'success'};
  const color = Object.hasOwn(colors, type) ? colors[type] : 'primary';
  const origin = document.activeElement as HTMLElement | null;
  const element = document.createElement('div');
  element.className = 'toast';
  if (options.assertive) {
    element.setAttribute('role', 'alert');
    element.setAttribute('aria-live', 'assertive');
    element.setAttribute('aria-atomic', 'true');
  }

  const header = document.createElement('div');
  header.className = 'toast-header';
  const indicator = document.createElement('span');
  indicator.className = `p-2 border border-light bg-${color} rounded-circle me-2`;
  const title = document.createElement('strong');
  title.className = 'me-auto';
  title.textContent = options.title;
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'btn-close';
  close.setAttribute('data-bs-dismiss', 'toast');
  close.setAttribute('aria-label', options.closeLabel);
  header.append(indicator, title, close);

  const body = document.createElement('div');
  body.className = 'toast-body';
  body.textContent = message;
  element.append(header, body);
  element.addEventListener('hidden.bs.toast', () => {
    if (options.restoreFocus && element.contains(document.activeElement) && origin?.isConnected && origin.getClientRects().length) {
      origin.focus();
    }
    tabler.Toast.getInstance(element)?.dispose();
    element.remove();
  }, {once: true});

  container.append(element);
  tabler.Toast.getOrCreateInstance(element, {autohide: options.autohide ?? true}).show();
  options.announce?.(message, type);
}
