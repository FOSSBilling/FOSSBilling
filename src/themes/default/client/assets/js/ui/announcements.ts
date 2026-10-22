const pending = new WeakMap<HTMLElement, ReturnType<typeof setTimeout>>();

/** Update a pre-existing live region without moving focus or announcing toast controls. */
export function announceMessage(message: string, type: string): void {
  const channel = type === 'error' ? 'error' : 'status';
  let region = document.querySelector<HTMLElement>(`[data-client-announcement="${channel}"]`);
  if (!region) {
    // Layout overrides supplied by extensions may not include the shared partial.
    region = document.createElement('div');
    region.className = 'visually-hidden';
    region.dataset.clientAnnouncement = channel;
    region.setAttribute('role', channel === 'error' ? 'alert' : 'status');
    region.setAttribute('aria-atomic', 'true');
    document.body.append(region);
  }
  clearTimeout(pending.get(region));
  region.textContent = '';
  const target = region;
  pending.set(target, setTimeout(() => {
    target.textContent = message;
    pending.delete(target);
  }, 40));
}
