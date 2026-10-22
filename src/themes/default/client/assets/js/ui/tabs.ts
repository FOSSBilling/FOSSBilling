import { Tab } from '@tabler/core';
import { initTabNavigation } from '../../../../../../../frontend/ui/tab-navigation.ts';

export default function initTabs(): void {
  initTabNavigation({getOrCreateInstance: element => Tab.getOrCreateInstance(element) as Tab});
}
