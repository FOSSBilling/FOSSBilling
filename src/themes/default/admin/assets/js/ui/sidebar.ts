export default function initSidebar() {
  const root = document.documentElement;
  const buttons = document.querySelectorAll<HTMLButtonElement>('[data-bs-toggle="sidebar-folded"]');

  const updateControls = (folded: boolean) => {
    buttons.forEach(button => {
      const label = folded ? button.dataset.sidebarLabelCollapsed : button.dataset.sidebarLabelExpanded;
      button.setAttribute('aria-pressed', String(!folded));
      if (label) {
        button.setAttribute('aria-label', label);
        button.title = label;
      }
    });
  };

  // Tabler owns hover expansion and persistence; keep the control's labels in sync.
  document.addEventListener('tabler:sidebar-folded', event => {
    if (!(event instanceof CustomEvent) || typeof event.detail?.folded !== 'boolean') return;
    updateControls(event.detail.folded);
  });

  updateControls((root.getAttribute('data-bs-sidebar') ?? '').startsWith('folded'));
}
