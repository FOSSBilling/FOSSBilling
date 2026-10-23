export type ThemePreference = 'system' | 'light' | 'dark';
const initialized = new WeakSet<Document>();
const valid = (value: unknown): value is ThemePreference => value === 'system' || value === 'light' || value === 'dark';

export function resolveTheme(preference: ThemePreference, systemDark: boolean): 'light' | 'dark' {
  return preference === 'dark' || (preference === 'system' && systemDark) ? 'dark' : 'light';
}

/** Shared preference handling for Tabler controls, retaining legacy extension links. */
export default function initThemePreference(): void {
  if (initialized.has(document)) return;
  initialized.add(document);
  const root = document.documentElement;
  const configured = root.dataset.fbThemeDefault;
  const fallback: ThemePreference = valid(configured) ? configured : 'system';
  const read = (): ThemePreference => {
    try {
      const stored = localStorage.getItem('theme');
      if (valid(stored)) return stored;
    } catch { /* Keep the configured default when storage is unavailable. */ }
    return fallback;
  };
  const system = window.matchMedia('(prefers-color-scheme: dark)');
  const choices = document.querySelectorAll<HTMLElement>('[data-theme-preference]');
  const legacy = document.querySelectorAll<HTMLAnchorElement>('.js-theme-toggler');
  let preference = read();
  const fromLink = (link: HTMLAnchorElement): ThemePreference | null => {
    const candidate = new URL(link.href, location.href).searchParams.get('theme');
    return valid(candidate) ? candidate : null;
  };
  const apply = () => {
    root.setAttribute('data-bs-theme', resolveTheme(preference, system.matches));
    choices.forEach(button => {
      const selected = button.dataset.themePreference === preference;
      button.classList.toggle('active', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
    document.querySelectorAll<HTMLElement>('[data-theme-icon]').forEach(icon => {
      icon.classList.toggle('d-none', icon.dataset.themeIcon !== preference);
    });
  };
  const choose = (next: ThemePreference) => {
    preference = next;
    try { localStorage.setItem('theme', next); } catch { /* Session-only choice. */ }
    apply();
  };
  choices.forEach(button => button.addEventListener('click', () => {
    const next = button.dataset.themePreference;
    if (!valid(next)) return;
    tabler.Tooltip.getInstance(button)?.hide();
    choose(next);
    // A compact picker closes after selection; keep focus on its visible trigger.
    button.closest('[data-theme-picker]')?.querySelector<HTMLElement>('[data-bs-toggle="dropdown"]')?.focus({preventScroll: true});
  }));
  legacy.forEach(link => link.addEventListener('click', event => {
    const next = fromLink(link);
    if (!next) return;
    event.preventDefault();
    const hadFocus = document.activeElement === link;
    tabler.Tooltip.getInstance(link)?.hide();
    choose(next);
    if (hadFocus && !link.getClientRects().length) {
      [...legacy].find(candidate => candidate.getClientRects().length)?.focus({preventScroll: true});
    }
  }));
  system.addEventListener('change', apply);
  window.addEventListener('storage', event => {
    if (event.key === 'theme' || event.key === null) {
      preference = read();
      apply();
    }
  });
  apply();
}
