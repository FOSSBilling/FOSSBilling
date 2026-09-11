/**
 * Locale-selector TomSelect wiring shared by the default admin and client
 * themes.
 *
 * The TomSelect config is identical in both themes; only the option/item
 * templates differ (admin renders an indicator, client a flag icon), so the
 * caller passes its own render functions plus the TomSelect constructor from
 * its theme bundle. Selection is persisted in the locale cookie and the page
 * reloads — wired on `change` so programmatic sets behave like user picks.
 */

export interface TomSelectConstructor {
  new (selector: string, options: Record<string, unknown>): { on(event: string, callback: (value: string) => void): void };
}

export interface LocaleSelectTemplates {
  item: (data: Record<string, unknown>, escape: (value: string) => string) => string;
  option: (data: Record<string, unknown>, escape: (value: string) => string) => string;
}

// Browser runtime API from frontend/core/api.js, which every layout loads
// before the theme bundles.
declare const FOSSBilling: {
  cookieNames?: { locale?: string };
  cookieRead: (name: string) => string | null;
  cookieCreate: (name: string, value: string, days: number) => void;
};

export function initLocaleSelector(TomSelect: TomSelectConstructor, templates: LocaleSelectTemplates): void {
  const localeSelectorEl = document.querySelector('.js-locale-selector');
  if (localeSelectorEl === null) {
    return;
  }

  const localeCookie = FOSSBilling.cookieNames?.locale || 'fossbilling_locale';
  const selectedLang = FOSSBilling.cookieRead(localeCookie) || (localeSelectorEl as HTMLSelectElement).value;

  const selector = new TomSelect('.js-locale-selector', {
    copyClassesToDropdown: false,
    controlClass: 'ts-control locale',
    dropdownClass: 'dropdown-menu ts-dropdown locale-selector-dropdown',
    optionClass: 'dropdown-item',
    controlInput: false,
    items: selectedLang ? [selectedLang] : [],
    render: {
      item: templates.item,
      option: templates.option,
    },
  });

  selector.on('change', (value) => {
    FOSSBilling.cookieCreate(localeCookie, value, 365);
    window.location.reload();
  });
}
