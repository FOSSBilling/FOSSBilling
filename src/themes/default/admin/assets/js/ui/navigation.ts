import { bindNavigationDisclosure } from '../../../../../../../frontend/ui/navigation-disclosure.ts';

export default function initNavigation(): void {
  const menu = document.getElementById('navbar-menu');
  const toggle = document.querySelector<HTMLElement>('[aria-controls="navbar-menu"]');
  if (!menu || !toggle) return;
  bindNavigationDisclosure(menu, toggle, () => (tabler.Collapse.getOrCreateInstance(menu, {toggle: false}) as {hide(): void}).hide());
}
