export default function initSidebar() {
  const root = document.documentElement;
  const buttons = document.querySelectorAll<HTMLButtonElement>('[data-bs-toggle="sidebar-folded"]');

  const updateControls = (folded: boolean) => {
    buttons.forEach(button => {
      const label = folded ? button.dataset.sidebarLabelCollapsed : button.dataset.sidebarLabelExpanded;
      button.setAttribute('aria-pressed', String(folded));
      if (label) {
        button.setAttribute('aria-label', label);
        button.title = label;
      }
    });
  };

  // Tabler's toggle defaults to hover expansion. Keep an explicitly folded rail
  // folded while it is being used, including by keyboard.
  document.addEventListener('tabler:sidebar-folded', event => {
    if (!(event instanceof CustomEvent) || typeof event.detail?.folded !== 'boolean') return;
    const folded = event.detail.folded;
    if (folded) root.setAttribute('data-bs-sidebar', 'folded');
    else root.removeAttribute('data-bs-sidebar');
    try {
      localStorage.setItem('tabler-sidebar', folded ? 'folded' : 'default');
    } catch {}
    updateControls(folded);
  });

  updateControls(root.getAttribute('data-bs-sidebar') === 'folded');
}
