import { Datepicker } from '@tabler/core';
import * as VanillaCalendarPro from 'vanilla-calendar-pro';

declare global {
  interface Window {
    VanillaCalendarPro: typeof VanillaCalendarPro;
  }
}

// Tabler expects its calendar dependency on window.
window.VanillaCalendarPro = VanillaCalendarPro;

function validDate(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const [year, month, day] = value.split('-').map(Number);
  const date = new Date(year, month - 1, day);
  return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
}

function rangeField(input: HTMLInputElement, name: string): HTMLInputElement {
  // Names repeat across forms; never update another form's filter.
  const existing = input.form?.elements.namedItem(name);
  if (existing instanceof HTMLInputElement) return existing;
  const field = document.createElement('input');
  field.type = 'hidden';
  field.name = name;
  input.after(field);
  return field;
}

export default function initDatepickers() {
  document.querySelectorAll<HTMLInputElement>('.datepicker').forEach(input => {
    if (Datepicker.getInstance(input)) return;
    const range = Boolean(input.dataset.nameFrom && input.dataset.nameTo);
    const from = range ? rangeField(input, input.dataset.nameFrom!) : null;
    const to = range ? rangeField(input, input.dataset.nameTo!) : null;
    const parse = () => input.value.split(/\s+to\s+|\s*–\s*/).map(value => value.trim());
    const originalValue = input.value;
    const initial = parse().filter(validDate);
    const picker = new Datepicker(input, {
      selectionMode: range ? 'multiple-ranged' : 'single',
      selectedDates: initial,
      dateMin: '1930-01-01',
      dateFormat: date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`,
      placement: 'auto',
      locale: document.documentElement.lang || 'default',
      vcpOptions: {
        // A second click on the same day must finish a range, not deselect it.
        enableDateToggle: false,
      },
    });
    input.autocomplete = 'off';
    let calendarChange = false;
    const publish = (dates: string[]) => {
      const sorted = [...dates].sort();
      const first = sorted[0] ?? '';
      const last = sorted.length > 1 ? sorted[sorted.length - 1] : '';
      input.value = range && last ? `${first} to ${last}` : first;
      if (from && to) {
        from.value = first;
        to.value = last;
      }
      input.setCustomValidity('');
      // Notify form listeners without feeding the selection back to the calendar.
      calendarChange = true;
      try {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      } finally {
        calendarChange = false;
      }
    };
    // Tabler formats the initial range with an en dash; preserve the form's value.
    input.value = originalValue;
    const syncInput = () => {
      if (calendarChange) return;
      const parts = input.value.trim() ? parse() : [];
      if (parts.some(value => value && !validDate(value)) || parts.length > (range ? 2 : 1)) {
        input.setCustomValidity(input.dataset.invalidDateLabel || 'Enter a valid date in YYYY-MM-DD format.');
        return;
      }
      picker.setSelectedDates(parts.filter(Boolean));
      input.setCustomValidity('');
      if (from && to) {
        from.value = parts[0] ?? '';
        to.value = parts[1] ?? '';
      }
    };
    syncInput();

    input.addEventListener('change.bs.datepicker', event => {
      const dates = (event as Event & { dates: string[] }).dates;
      publish(dates);
    });
    input.addEventListener('change', syncInput);
    input.addEventListener('input', syncInput);
    input.addEventListener('focus', () => void picker.show());

    const clear = document.createElement('button');
    clear.type = 'button';
    clear.disabled = input.disabled || input.readOnly;
    clear.className = 'btn btn-sm btn-ghost-secondary datepicker-clear';
    clear.textContent = '×';
    clear.setAttribute('aria-label', input.dataset.clearLabel || 'Clear date');
    clear.addEventListener('click', () => {
      picker.setSelectedDates([]);
      publish([]);
      void picker.hide();
    });
    const wrapper = input.closest('.input-icon');
    if (wrapper) {
      wrapper.classList.add('datepicker-wrapper');
      wrapper.append(clear);
    } else {
      input.after(clear);
    }
    input.form?.addEventListener('reset', () => {
      // The browser restores defaultValue after dispatching the reset event.
      setTimeout(syncInput, 0);
    });
  });
}
