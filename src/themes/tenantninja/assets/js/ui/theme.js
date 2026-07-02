/**
 * Light / dark theme controller for the TenantNinja client theme.
 *
 * Two-state, matching the admin theme's UX. The user's choice is persisted
 * in localStorage['theme']; the server-rendered data-bs-theme attribute is
 * used as the default for visitors with no stored choice. The companion
 * FOUC-prevention partial (partials/theme_init.html.twig) does the same
 * resolution on the very first script execution so there is never a flash
 * of the wrong theme - this module is the post-paint controller.
 *
 * UI contract:
 *   - .js-theme-toggler : anchor (or button) with href="?theme=light|dark".
 *                         The active theme's link is hidden via
 *                         .hide-theme-light / .hide-theme-dark.
 */

const STORAGE_KEY = 'theme';
const VALID = ['light', 'dark'];

const isValid = (value) => VALID.includes(value);

const themeFromElement = (el) => {
  const candidate = new URL(el.href, location.href).searchParams.get(STORAGE_KEY);
  return isValid(candidate) ? candidate : null;
};

export default function initTheme() {
  document.querySelectorAll('.js-theme-toggler').forEach((el) => {
    el.addEventListener('click', (event) => {
      event.preventDefault();
      const theme = themeFromElement(el);
      if (!theme) {
        return;
      }
      try { localStorage.setItem(STORAGE_KEY, theme); } catch { /* private mode etc. */ }
      document.documentElement.setAttribute('data-bs-theme', theme);
    });
  });
}
