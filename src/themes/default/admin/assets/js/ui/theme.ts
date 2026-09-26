type ThemePreference = 'system' | 'light' | 'dark';

function readPreference(): ThemePreference {
  try {
    const stored = localStorage.getItem('theme');
    if (stored === 'light' || stored === 'dark') return stored;
  } catch {}
  return 'system';
}

export default function initThemePreference() {
  const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
  const buttons = document.querySelectorAll<HTMLButtonElement>('[data-theme-preference]');
  let preference = readPreference();

  const apply = () => {
    const dark = preference === 'dark' || (preference === 'system' && systemTheme.matches);
    document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    buttons.forEach(button => {
      const selected = button.dataset.themePreference === preference;
      button.classList.toggle('active', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
  };

  buttons.forEach(button => button.addEventListener('click', () => {
    const next = button.dataset.themePreference;
    if (next !== 'system' && next !== 'light' && next !== 'dark') return;
    preference = next;
    try {
      localStorage.setItem('theme', preference);
    } catch {}
    apply();
  }));

  systemTheme.addEventListener('change', apply);
  window.addEventListener('storage', event => {
    if (event.key === 'theme' || event.key === null) {
      preference = readPreference();
      apply();
    }
  });
  apply();
}
