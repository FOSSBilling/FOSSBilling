import { tabTarget } from './tab-navigation.ts';
import { dropdownMenuFor } from './dropdown-focus.ts';

type HelpInstance = {hide(): void};
type HelpLibrary = {getInstance(element: HTMLElement): HelpInstance | null};
type HelpLibraries = {Tooltip: HelpLibrary; Popover: HelpLibrary};
const initialized = new WeakSet<Document>();

/** Tabler owns rendering, sanitization, positioning and each trigger's configuration. */
export default function initFloatingHelp(library: HelpLibraries): void {
  if (initialized.has(document)) return;
  initialized.add(document);
  const visible = new Map<HTMLElement, {kind: 'Tooltip' | 'Popover'; tip: HTMLElement | null}>();
  for (const [kind, name] of [['Tooltip', 'tooltip'], ['Popover', 'popover']] as const) {
    document.addEventListener(`shown.bs.${name}`, event => {
      const trigger = event.target as HTMLElement;
      const id = trigger.getAttribute('aria-describedby');
      visible.set(trigger, {kind, tip: id ? document.getElementById(id) : null});
      if (trigger.hasAttribute('data-fb-help-popover')) trigger.setAttribute('aria-expanded', 'true');
    });
    document.addEventListener(`hidden.bs.${name}`, event => {
      const trigger = event.target as HTMLElement;
      const help = visible.get(trigger);
      if (help?.tip?.contains(document.activeElement) && trigger.getClientRects().length) trigger.focus({preventScroll: true});
      visible.delete(trigger);
      if (trigger.hasAttribute('data-fb-help-popover')) trigger.setAttribute('aria-expanded', 'false');
    });
  }
  window.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || event.defaultPrevented) return;
    for (const [trigger, {kind}] of visible) {
      if (!trigger.isConnected || !library[kind].getInstance(trigger)) visible.delete(trigger);
    }
    if (!visible.size) return;
    // Dismiss floating help before the surrounding menu/dialog handles Escape.
    event.preventDefault();
    event.stopPropagation();
    for (const [trigger, {kind}] of visible) library[kind].getInstance(trigger)?.hide();
  }, {capture: true});
  document.addEventListener('pointerdown', event => {
    for (const [trigger, {kind, tip}] of visible) {
      // Only the shared text-help control opts into outside dismissal.
      if (trigger.hasAttribute('data-fb-help-popover') && !trigger.contains(event.target as Node) && !tip?.contains(event.target as Node)) library[kind].getInstance(trigger)?.hide();
    }
  });
  for (const name of ['collapse', 'modal', 'tab', 'dropdown']) {
    document.addEventListener(`hidden.bs.${name}`, event => {
      const target = event.target as HTMLElement;
      const id = name === 'tab' ? tabTarget(target) : null;
      const container = name === 'dropdown' ? dropdownMenuFor(target) : id ? document.getElementById(id) : target;
      if (!container) return;
      for (const [trigger, {kind}] of visible) if (container.contains(trigger)) library[kind].getInstance(trigger)?.hide();
    });
  }
}
