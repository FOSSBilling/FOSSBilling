import { Collapse } from '@tabler/core';
import { bindNavigationDisclosure } from '../../../../../../../frontend/ui/navigation-disclosure.ts';

export default function initNavigation(): void {
  const menu = document.getElementById('navbarSupportedContent');
  const toggle = document.querySelector<HTMLElement>('[aria-controls="navbarSupportedContent"]');
  if (!menu || !toggle) return;
  bindNavigationDisclosure(menu, toggle, () => (Collapse.getOrCreateInstance(menu, {toggle: false}) as Collapse).hide());
}
