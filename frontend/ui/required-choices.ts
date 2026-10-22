const messages = new WeakMap<HTMLInputElement, string>();

export function validateRequiredChoices(group: HTMLElement): void {
  const boxes = [...group.querySelectorAll<HTMLInputElement>('input[type="checkbox"]')];
  const first = boxes[0];
  if (!first) return;
  // Do not overwrite custom validation supplied by an extension.
  if (first.validity.customError && first.validationMessage !== messages.get(first)) return;
  const message = boxes.some(box => box.checked) ? '' : group.dataset.requiredMessage || 'Select at least one option.';
  first.setCustomValidity(message);
  messages.set(first, message);
  if (first.validity.valid) FOSSBilling.ui.clearFieldError?.(first);
  else if (first.getAttribute('aria-invalid') === 'true') FOSSBilling.ui.fieldError?.(first);
}

export default function initRequiredChoices(): void {
  const selector = '[data-required-checkbox-group]';
  document.querySelectorAll<HTMLElement>(selector).forEach(validateRequiredChoices);
  document.addEventListener('change', event => {
    const group = (event.target as Element)?.closest?.<HTMLElement>(selector);
    if (group) validateRequiredChoices(group);
  });
  document.addEventListener('submit', event => {
    const form = event.target as HTMLFormElement;
    if (event.defaultPrevented || !form.matches?.('form') || form.noValidate || (event.submitter as HTMLButtonElement)?.formNoValidate) return;
    // Extension-rendered forms may have arrived after the initial page setup.
    const groups = [...form.querySelectorAll<HTMLElement>(selector)];
    groups.forEach(validateRequiredChoices);
    const invalid = groups.map(group => group.querySelector<HTMLInputElement>('input[type="checkbox"]'))
      .find(first => first && !first.validity.valid);
    if (invalid) {
      event.preventDefault();
      invalid.reportValidity();
    }
  }, true);
}
