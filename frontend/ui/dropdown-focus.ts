const initialized = new WeakSet<Document>();
const selector = '[data-bs-toggle="dropdown"], [data-tblr-toggle="dropdown"]';

export function dropdownMenuFor(trigger: HTMLElement): HTMLElement | null {
  const controlled = trigger.getAttribute('aria-controls');
  return (controlled ? document.getElementById(controlled) : null)
    || trigger.parentElement?.querySelector<HTMLElement>(':scope > .dropdown-menu') || null;
}

/** Recover focus only when a hidden Tabler dropdown would leave it invisible. */
export default function initDropdownFocus(): void {
  if (initialized.has(document)) return;
  initialized.add(document);
  document.addEventListener('hidden.bs.dropdown', event => {
    const trigger = event.target as HTMLElement;
    if (!trigger?.matches?.(selector) || !trigger.getClientRects().length || trigger.matches(':disabled, .disabled, [aria-disabled="true"]')) return;
    const menu = dropdownMenuFor(trigger);
    if (menu?.contains(document.activeElement)) trigger.focus({preventScroll: true});
  });
}
