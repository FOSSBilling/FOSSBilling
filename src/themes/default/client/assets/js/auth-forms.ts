const initialized = new WeakSet<HTMLFormElement>();

export default function initAuthForms() {
  document.querySelectorAll<HTMLFormElement>('form[data-auth-passwords]').forEach(form => {
    if (initialized.has(form)) return;
    initialized.add(form);
    const password = form.querySelector<HTMLInputElement>('[data-auth-new-password]');
    const confirmation = form.querySelector<HTMLInputElement>('[data-auth-confirm-password]');
    if (password && confirmation) {
      const validateConfirmation = () => {
        confirmation.setCustomValidity(confirmation.value && confirmation.value !== password.value
          ? form.dataset.passwordMismatch || 'The passwords do not match.' : '');
        // Changing either password can correct an error on the confirmation field.
        if (confirmation.getAttribute('aria-invalid') === 'true') {
          if (confirmation.validity.valid) FOSSBilling.ui.clearFieldError?.(confirmation);
          else FOSSBilling.ui.fieldError?.(confirmation);
        }
      };
      validateConfirmation();
      form.addEventListener('submit', event => {
        validateConfirmation();
        if (!confirmation.validity.valid) {
          event.preventDefault();
          confirmation.reportValidity();
        }
      }, true);
      [password, confirmation].forEach(input => {
        input.addEventListener('input', validateConfirmation);
        input.addEventListener('change', validateConfirmation);
      });
      // Password managers can fill both values without emitting input/change.
      // Refresh before native constraint validation runs for a tap or Enter.
      form.addEventListener('focusin', validateConfirmation);
      form.addEventListener('click', validateConfirmation, true);
      form.addEventListener('keydown', event => {
        if (event.key === 'Enter') validateConfirmation();
      }, true);
    }
    form.querySelector<HTMLInputElement>('[data-auth-show-passwords]')?.addEventListener('change', event => {
      const visible = (event.currentTarget as HTMLInputElement).checked;
      form.querySelectorAll<HTMLInputElement>('input[data-auth-password]').forEach(input => {
        input.type = visible ? 'text' : 'password';
      });
    });
  });
}
