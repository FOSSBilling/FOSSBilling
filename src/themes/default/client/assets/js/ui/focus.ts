/** Keep Tab at a modal's boundaries inside the dialog, including at the end of the page. */
export function wrapModalFocus(event: KeyboardEvent): void {
  if (event.key !== 'Tab' || event.defaultPrevented) return;
  const target = event.target as HTMLElement;
  const modal = target.closest<HTMLElement>('.modal.show[aria-modal="true"]');
  if (!modal) return;
  const controls = [...modal.querySelectorAll<HTMLElement>('a[href], button, input, select, textarea, [tabindex], [contenteditable="true"]')]
    .filter(element => element.tabIndex >= 0 && !element.matches(':disabled') && !element.closest('[inert]')
      && element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden');
  const first = controls[0];
  const last = controls.at(-1);
  if (!first) {
    event.preventDefault(); modal.focus();
  } else if (event.shiftKey && (target === first || target === modal)) {
    event.preventDefault(); last.focus();
  } else if (!event.shiftKey && (target === last || target === modal)) {
    event.preventDefault(); first.focus();
  }
}

let initialized = false;
export default function initFocus(): void {
  if (initialized) return;
  initialized = true;
  document.addEventListener('keydown', wrapModalFocus);
}
