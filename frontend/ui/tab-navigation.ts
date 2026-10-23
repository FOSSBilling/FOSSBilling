export const tabTriggerSelector = '[data-bs-toggle="tab"], [data-bs-toggle="pill"], [data-bs-toggle="list"], [data-tblr-toggle="tab"], [data-tblr-toggle="pill"], [data-tblr-toggle="list"]';

type TabNavigationOptions = {
  updateUrl?: (url: URL, pane: HTMLElement) => void;
  onActiveClick?: (event: MouseEvent, trigger: HTMLElement, pane: HTMLElement) => void;
  jumpLinks?: boolean;
};
const initialized = new WeakSet<Document>();

/** Local pane IDs only: avoid treating extension-provided URLs as CSS selectors. */
export function tabTarget(trigger: Element): string | null {
  const target = trigger.getAttribute('data-bs-target') || trigger.getAttribute('data-tblr-target') || trigger.getAttribute('href');
  return target?.startsWith('#') && target.length > 1 ? target.slice(1) : null;
}

function enabled(trigger: HTMLElement): boolean {
  return !trigger.matches(':disabled, .disabled, [aria-disabled="true"]');
}

export function initTabNavigation(options: TabNavigationOptions = {}): void {
  if (initialized.has(document)) return;
  initialized.add(document);
  const showLinkedTab = (id = location.hash.slice(1)) => {
    if (!id || !document.getElementById(id)) return;
    const trigger = [...document.querySelectorAll<HTMLElement>(tabTriggerSelector)]
      .find(element => tabTarget(element) === id && enabled(element));
    if (trigger) tabler.Tab.getOrCreateInstance(trigger).show();
  };

  // Keep initial deep links from rewriting server-rendered list parameters.
  showLinkedTab();
  window.addEventListener('hashchange', () => showLinkedTab());
  document.addEventListener('shown.bs.tab', event => {
    const trigger = event.target as HTMLElement;
    if (!trigger?.matches?.(tabTriggerSelector) || !enabled(trigger)) return;
    const id = tabTarget(trigger);
    const pane = id ? document.getElementById(id) : null;
    if (!pane) return;
    const url = new URL(location.href);
    options.updateUrl?.(url, pane);
    url.hash = id!;
    history.replaceState(history.state, '', url);

    // Reveal the selected tab in a scrolling nav without moving the page vertically.
    const nav = trigger.closest<HTMLElement>('[role="tablist"], .nav');
    if (nav && nav.scrollWidth > nav.clientWidth) {
      const bounds = nav.getBoundingClientRect();
      const tabBounds = trigger.getBoundingClientRect();
      if (tabBounds.left < bounds.left) nav.scrollLeft += tabBounds.left - bounds.left;
      else if (tabBounds.right > bounds.right) nav.scrollLeft += tabBounds.right - bounds.right;
    }
  });
  if (!options.onActiveClick && !options.jumpLinks) return;
  // Capture before Tabler's document handler activates a newly clicked pane.
  window.addEventListener('click', event => {
    if (!(event.target instanceof Element) || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    const trigger = event.target.closest<HTMLElement>(tabTriggerSelector);
    if (trigger && enabled(trigger)) {
      const id = tabTarget(trigger);
      const pane = id ? document.getElementById(id) : null;
      if (pane?.classList.contains('active')) options.onActiveClick?.(event, trigger, pane);
    }
    if (!options.jumpLinks) return;
    const jump = event.target.closest<HTMLElement>('[data-tab-jump]');
    const id = jump ? tabTarget(jump) : null;
    if (id && jump && enabled(jump)) {
      event.preventDefault();
      showLinkedTab(id);
    }
  }, true);
}
