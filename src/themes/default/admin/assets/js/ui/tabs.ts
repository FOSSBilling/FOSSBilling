import { initTabNavigation } from '../../../../../../../frontend/ui/tab-navigation.ts';
import { pruneListParamsForTab } from '../utils.ts';

export default function initTabs(): void {
  initTabNavigation({
    jumpLinks: true,
    updateUrl(url, pane) {
      const namespace = pane.getAttribute('data-list-ns');
      const others = [...document.querySelectorAll<HTMLElement>('.tab-pane[data-list-ns]')]
        .filter(other => other !== pane).map(other => other.getAttribute('data-list-ns') ?? '');
      url.search = pruneListParamsForTab(url.search, namespace, others);
      url.searchParams.delete('tab');
    },
    onActiveClick(event, trigger, pane) {
      const namespace = pane.getAttribute('data-list-ns');
      if (namespace === null) return;
      const url = new URL(location.href);
      const pruned = pruneListParamsForTab(url.search, null, [namespace]);
      const target = `#${pane.id}`;
      if (pruned === url.search && url.hash === target) return;
      event.preventDefault();
      url.search = pruned;
      url.hash = target;
      location.assign(url);
    },
  });
}
