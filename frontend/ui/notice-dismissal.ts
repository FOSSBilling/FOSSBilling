const initialized = new WeakSet<Document>();

/** Let Tabler dismiss notices; recover focus only when the removed notice owned it. */
export default function initNoticeDismissal(): void {
  if (initialized.has(document)) return;
  initialized.add(document);
  const closing = new WeakMap<HTMLElement, {restore: boolean; candidates: HTMLElement[]}>();
  document.addEventListener('close.bs.alert', event => {
    const notice = event.target as HTMLElement;
    if (!notice?.matches?.('[data-fb-notice]')) return;
    const previous = closing.get(notice);
    const state = previous || {restore: false, candidates: []};
    state.restore = notice.contains(document.activeElement);
    state.candidates = [...document.querySelectorAll<HTMLElement>('a[href], button, input, select, textarea, [tabindex]')]
      .filter(element => !notice.contains(element) && Boolean(notice.compareDocumentPosition(element) & 4));
    if (previous) return;
    closing.set(notice, state);
    // closed.bs.alert is emitted after removal, so it no longer bubbles to document.
    notice.addEventListener('closed.bs.alert', () => {
      closing.delete(notice);
      if (!state.restore || (document.activeElement !== document.body && !notice.contains(document.activeElement))) return;
      const next = state.candidates.find(element => element.isConnected && element.getClientRects().length
        && element.tabIndex >= 0 && !element.matches(':disabled, .disabled, [aria-disabled="true"], [type="hidden"]') && !element.closest('[inert]'));
      next?.focus({preventScroll: true});
    }, {once: true});
  });
}
