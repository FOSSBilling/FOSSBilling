/**
 * Shows the "Top" shortcut only once the page actually has enough content to
 * scroll, and toggles it in/out as the user scrolls back up.
 */
export default function initBackToTop() {
  const button = document.getElementById('js-back-to-top');
  if (!button) {
    return;
  }

  const SCROLL_THRESHOLD = 240;

  const isScrollable = () => document.documentElement.scrollHeight > window.innerHeight + SCROLL_THRESHOLD;

  const update = () => {
    const show = isScrollable() && window.scrollY > SCROLL_THRESHOLD;
    button.classList.toggle('d-none', !show);
  };

  update();
  window.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update);
}
