/**
 * Tab deep-linking shared by the default admin and client themes.
 *
 * Shows the tab matching the URL hash on load and on hash change, and syncs
 * the hash when the user switches tabs. Options cover both themes' needs:
 * admin additionally wires `[data-tab-jump]` links and drops the legacy
 * `tab` query param; client additionally covers `[data-bs-toggle="list"]`.
 */

export interface TabController {
  getOrCreateInstance(element: Element): { show(): void };
}

export interface TabDeepLinkOptions {
  selectors?: string;
  enableJumpLinks?: boolean;
  clearTabParam?: boolean;
}

export function tabIdFromHash(hash: string): string {
  return hash.startsWith('#') ? hash.slice(1) : '';
}

export function initTabDeepLinking(Tab: TabController, options: TabDeepLinkOptions = {}): void {
  const {
    selectors = '[data-bs-toggle="tab"], [data-bs-toggle="pill"]',
    enableJumpLinks = false,
    clearTabParam = false,
  } = options;
  const toggles = selectors.split(',').map((selector) => selector.trim()).filter(Boolean);

  const getTabTargetSelector = (tabTrigger: Element): string | null => {
    const dataTarget = tabTrigger.getAttribute('data-bs-target');
    if (dataTarget && dataTarget.startsWith('#')) {
      return dataTarget;
    }

    const hrefTarget = tabTrigger.getAttribute('href');
    if (hrefTarget && hrefTarget.startsWith('#')) {
      return hrefTarget;
    }

    return null;
  };

  const findTabTrigger = (tabId: string): Element | null => {
    if (!tabId) {
      return null;
    }

    for (const toggle of toggles) {
      const byTarget = document.querySelector(`${toggle}[data-bs-target="#${tabId}"]`);
      if (byTarget) {
        return byTarget;
      }
      const byHref = document.querySelector(`${toggle}[href="#${tabId}"]`);
      if (byHref) {
        return byHref;
      }
    }

    return null;
  };

  const showTabById = (tabId: string): boolean => {
    const tabTrigger = findTabTrigger(tabId);
    if (!tabTrigger) {
      return false;
    }

    Tab.getOrCreateInstance(tabTrigger).show();

    return true;
  };

  const syncTabUrl = (tabId: string): void => {
    if (!tabId) {
      return;
    }

    const url = new URL(window.location.href);
    url.hash = tabId;
    if (clearTabParam) {
      url.searchParams.delete('tab');
    }
    window.history.replaceState({}, '', url);
  };

  showTabById(tabIdFromHash(window.location.hash));

  document.querySelectorAll(selectors).forEach((tabTrigger) => {
    tabTrigger.addEventListener('shown.bs.tab', function (this: Element) {
      const targetSelector = getTabTargetSelector(this);
      if (targetSelector) {
        syncTabUrl(targetSelector.slice(1));
      }
    });
  });

  window.addEventListener('hashchange', () => {
    showTabById(tabIdFromHash(window.location.hash));
  });

  if (enableJumpLinks) {
    document.addEventListener('click', (event) => {
      if (!(event.target instanceof Element)) {
        return;
      }

      const trigger = event.target.closest('[data-tab-jump]');
      if (!trigger) {
        return;
      }

      const targetSelector = trigger.getAttribute('href');
      if (!targetSelector || !targetSelector.startsWith('#')) {
        return;
      }

      event.preventDefault();
      showTabById(targetSelector.slice(1));
    });
  }
}
