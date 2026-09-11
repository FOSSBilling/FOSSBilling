/**
 * Light/dark theme controller shared by the default admin and client themes.
 *
 * Two-state, matching both themes' UX. The user's choice is persisted in
 * localStorage['theme']; the server-rendered data-bs-theme attribute is the
 * default for visitors with no stored choice.
 *
 * UI contract:
 *   - .js-theme-toggler : anchor (or button) with href="?theme=light|dark".
 */

const storageKey = 'theme';
const validThemes = ['light', 'dark'];

export function themeFromHref(href: string, base: string): string | null {
  let candidate: string | null = null;
  try {
    candidate = new URL(href, base).searchParams.get(storageKey);
  } catch {
    return null;
  }

  return candidate !== null && validThemes.includes(candidate) ? candidate : null;
}

export function initThemeToggle(): void {
  document.querySelectorAll('.js-theme-toggler').forEach((el) => {
    el.addEventListener('click', (event) => {
      event.preventDefault();
      const theme = themeFromHref(el.getAttribute('href') || '', window.location.href);
      if (!theme) {
        return;
      }
      try {
        localStorage.setItem(storageKey, theme);
      } catch {
        // Private mode etc. — the attribute swap below still applies.
      }
      document.documentElement.setAttribute('data-bs-theme', theme);
    });
  });
}
