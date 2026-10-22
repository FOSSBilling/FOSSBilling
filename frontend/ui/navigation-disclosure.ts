const bound = new WeakSet<HTMLElement>();
const dropdownToggle = '[data-bs-toggle="dropdown"], [data-tblr-toggle="dropdown"]';

/** Tabler owns transitions; both navigation disclosures share Escape and focus recovery. */
export function bindNavigationDisclosure(menu: HTMLElement, toggle: HTMLElement, hide: () => void): void {
  if (bound.has(menu)) return;
  bound.add(menu);
  let returnFocus = false;
  const closeMenu = (event: KeyboardEvent) => {
    if (event.key !== 'Escape' || event.defaultPrevented || !menu.classList.contains('show') || !toggle.getClientRects().length) return;
    const target = event.target as Element;
    // An open dropdown consumes the first Escape; the next closes navigation.
    if (target.closest('.dropdown, .dropup, .dropend, .dropstart')?.querySelector('.dropdown-menu.show')) return;
    event.preventDefault();
    returnFocus = true;
    hide();
  };
  menu.addEventListener('keydown', closeMenu);
  // Tabler consumes Escape on a closed dropdown toggle in document capture.
  window.addEventListener('keydown', event => {
    const target = event.target as Element;
    if (menu.contains(target) && target.matches(dropdownToggle) && target.getAttribute('aria-expanded') === 'false') closeMenu(event);
  }, {capture: true});
  menu.addEventListener('hidden.bs.collapse', event => {
    if (event.target !== menu) return;
    if (returnFocus || menu.contains(document.activeElement)) toggle.focus();
    returnFocus = false;
  });
}
