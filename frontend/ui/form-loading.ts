type LoadingState = {buttons: HTMLButtonElement[]; status: HTMLElement};

/** Theme adapters retain their labels; the API owns locking and request state. */
export function createFormLoading(getMessage = () =>
  document.querySelector<HTMLElement>('.toast-container')?.dataset.loading || 'Submitting. Please wait.',
) {
  const pending = new WeakMap<HTMLFormElement, LoadingState>();

  function setFormLoading(form: HTMLFormElement, busy: boolean, submitter?: HTMLElement | null): void {
    if (!busy) {
      const state = pending.get(form);
      state?.buttons.forEach(button => button.classList.remove('btn-loading'));
      state?.status.remove();
      pending.delete(form);
      return;
    }
    if (pending.has(form)) return;
    // Form.elements includes controls associated through a form attribute.
    // Inputs cannot display Tabler's pseudo-element spinner; the live status
    // still describes their progress and the API disables them during requests.
    const buttons = [...form.elements].filter((control): control is HTMLButtonElement =>
      control.matches('button[type="submit"], button:not([type])')
      && !(control as HTMLButtonElement).disabled && !control.classList.contains('btn-loading')
      && (!submitter || control === submitter));
    const status = document.createElement('p');
    status.setAttribute('role', 'status');
    status.setAttribute('aria-atomic', 'true');
    status.setAttribute('data-fb-form-loading', '');
    const modal = form.closest('.modal');
    if (modal) {
      // Stay inside the accessible dialog, outside its aria-busy form, without
      // inserting a sibling beside .modal-content that would break its layout.
      status.className = 'visually-hidden';
      modal.append(status);
    } else {
      status.className = 'small text-secondary mb-3';
      form.before(status);
    }
    status.textContent = getMessage();
    buttons.forEach(button => button.classList.add('btn-loading'));
    pending.set(form, {buttons, status});
  }

  function initFormLoading(): void {
    FOSSBilling.ui.formLoading ??= setFormLoading;
  }

  return {setFormLoading, initFormLoading};
}
