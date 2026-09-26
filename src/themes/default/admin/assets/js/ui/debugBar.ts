// PHP DebugBar reserves body padding; fixed elements need the same viewport inset.
export default function initDebugBarLayout() {
  const debugBar = document.querySelector<HTMLElement>('.phpdebugbar');
  if (!debugBar) return;

  const syncInsets = () => {
    const closed = debugBar.classList.contains('phpdebugbar-closed');
    const launcherAtTop = debugBar.getAttribute('data-openBtnPosition')?.startsWith('top');
    const launcherHeight = closed ? debugBar.getBoundingClientRect().height : 0;
    const launcherTop = launcherAtTop ? launcherHeight : 0;
    const launcherBottom = launcherAtTop ? 0 : launcherHeight;
    const root = document.documentElement.style;
    root.setProperty('--admin-debugbar-launcher-top', `${launcherTop}px`);
    root.setProperty('--admin-debugbar-launcher-bottom', `${launcherBottom}px`);
    root.setProperty('--admin-debugbar-top', `${(parseFloat(document.body.style.paddingTop) || 0) + launcherTop}px`);
    root.setProperty('--admin-debugbar-bottom', `${(parseFloat(document.body.style.paddingBottom) || 0) + launcherBottom}px`);
  };

  const observer = new MutationObserver(syncInsets);
  observer.observe(document.body, {
    attributes: true,
    attributeFilter: ['style'],
  });
  observer.observe(debugBar, {
    attributes: true,
    attributeFilter: ['class', 'data-openBtnPosition'],
  });
  new ResizeObserver(syncInsets).observe(debugBar);
  syncInsets();
}
