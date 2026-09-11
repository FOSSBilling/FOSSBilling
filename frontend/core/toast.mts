/**
 * Toast notifications shared by the default admin and client themes.
 *
 * Both themes render into an identical `.toast-container` and only differ in
 * which Toast constructor they pass (each theme's own Tabler bundle) — the
 * markup, per-type titles, and color mapping are shared. Adopted titles are
 * the admin variant (Error/Warning/Success/Info).
 */

export interface ToastConstructor {
  new (element: Element): { show(): void };
}

const toastTitles: Record<string, string> = {
  error: 'Error',
  warning: 'Warning',
  success: 'Success',
};

const toastColors: Record<string, string> = {
  error: 'danger',
  warning: 'warning',
  success: 'success',
};

export function showToastMessage(message: string, type = 'info', Toast: ToastConstructor): void {
  const container = document.querySelector('.toast-container');
  if (!container) {
    console.warn('Toast container not found for FOSSBilling.message()');
    return;
  }

  const element = document.createElement('div');
  container.appendChild(element);
  element.classList.add('toast', 'show');
  element.setAttribute('role', 'alert');
  element.setAttribute('aria-live', 'assertive');
  element.setAttribute('aria-atomic', 'true');

  const headerDiv = document.createElement('div');
  headerDiv.className = 'toast-header';

  const spanEl = document.createElement('span');
  spanEl.className = `p-2 border border-light bg-${toastColors[type] || 'primary'} rounded-circle me-2`;
  headerDiv.appendChild(spanEl);

  const strongEl = document.createElement('strong');
  strongEl.className = 'me-auto';
  strongEl.textContent = toastTitles[type] || 'Info';
  headerDiv.appendChild(strongEl);

  const closeButton = document.createElement('button');
  closeButton.type = 'button';
  closeButton.className = 'btn-close';
  closeButton.setAttribute('data-bs-dismiss', 'toast');
  closeButton.setAttribute('aria-label', 'Close');
  headerDiv.appendChild(closeButton);

  element.appendChild(headerDiv);

  const bodyDiv = document.createElement('div');
  bodyDiv.className = 'toast-body';
  bodyDiv.textContent = message;
  element.appendChild(bodyDiv);

  element.addEventListener('hidden.bs.toast', () => {
    container.removeChild(element);
  });

  new Toast(element).show();
}
