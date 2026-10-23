import initRequiredChoices from './required-choices.ts';
import { tabTriggerSelector, tabTarget } from './tab-navigation.ts';

type Field = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;
type ErrorLabels = {required?: string; formError?: string};
type PresenterOptions = {
  idPrefix?: string;
  summaryDataKey?: string;
  getLabels?: () => ErrorLabels;
};

/** Shared Tabler feedback; adapters retain translated labels and legacy selectors. */
export function createFormErrors({
  idPrefix = 'fb',
  summaryDataKey,
  getLabels = () => {
    const labels = document.querySelector<HTMLElement>('.toast-container')?.dataset || {};
    return {required: labels.required, formError: labels.formError};
  },
}: PresenterOptions = {}) {
  type FieldState = {
    feedback: HTMLElement; target: HTMLElement; invalid: string | null; hadClass: boolean;
    wrapper?: HTMLElement; wrapperHadClass?: boolean;
  };
  const errors = new WeakMap<HTMLElement, FieldState>();
  const summaries = new WeakMap<HTMLFormElement, HTMLElement>();
  let nextId = 0;
  let initialized = false;

  type AccessibleEditor = {focus(): void; getAccessibleElement?(): HTMLElement; setAccessibilityAttribute?(name: string, value: string | null): void};
  function editorFor(field: HTMLElement): AccessibleEditor | undefined {
    return (field as HTMLElement & {editor?: AccessibleEditor}).editor;
  }
  type EnhancedSelect = {focus(): void; focus_node: HTMLElement; wrapper: HTMLElement};
  function selectFor(field: HTMLElement): EnhancedSelect | undefined {
    return (field as HTMLElement & {tomselect?: EnhancedSelect}).tomselect;
  }

  function setFieldAttribute(field: HTMLElement, target: HTMLElement, name: string, value: string | null): void {
    const editor = editorFor(field);
    if (editor?.setAccessibilityAttribute && target !== field) editor.setAccessibilityAttribute(name, value);
    else if (value === null) target.removeAttribute(name);
    else target.setAttribute(name, value);
  }

  function focusField(field: HTMLElement): void {
    const pane = field.closest<HTMLElement>('.tab-pane:not(.active)');
    if (pane?.id) {
      const trigger = [...document.querySelectorAll<HTMLElement>(tabTriggerSelector)]
        .find(tab => tabTarget(tab) === pane.id);
      if (trigger && !trigger.matches('.disabled, :disabled, [aria-disabled="true"]') && typeof tabler !== 'undefined') {
        const instance = tabler.Tab?.getOrCreateInstance?.(trigger);
        if (instance) {
          trigger.addEventListener('shown.bs.tab', () => focusField(field), {once: true});
          instance.show();
          return;
        }
      }
    }
    const editor = editorFor(field);
    if (editor) editor.focus();
    else if (selectFor(field)) selectFor(field)!.focus();
    else field.focus();
    const target = editor?.getAccessibleElement?.() || selectFor(field)?.focus_node || field;
    if (target.getClientRects().length) target.scrollIntoView({block: 'center', inline: 'nearest', behavior: 'instant'});
  }

  function clearFieldError(field: HTMLElement): void {
    const state = errors.get(field);
    if (!state) return;
    const ids = (state.target.getAttribute('aria-describedby') || '').split(/\s+/).filter(id => id && id !== state.feedback.id);
    setFieldAttribute(field, state.target, 'aria-describedby', ids.length ? ids.join(' ') : null);
    setFieldAttribute(field, state.target, 'aria-invalid', state.invalid);
    if (!state.hadClass) field.classList.remove('is-invalid');
    if (state.wrapper && !state.wrapperHadClass) state.wrapper.classList.remove('is-invalid');
    state.feedback.remove();
    errors.delete(field);
  }

  function showFieldError(field: HTMLElement, message?: string): void {
    const labels = getLabels();
    let state = errors.get(field);
    if (!state) {
      // Older admin and extension forms may omit IDs; summary links still need
      // a stable target. Keep every existing ID and submission name unchanged.
      field.id ||= `${idPrefix}-field-${++nextId}`;
      const label = field.previousElementSibling as HTMLLabelElement | null;
      if (label?.tagName === 'LABEL' && !label.htmlFor) label.htmlFor = field.id;
      // Describe the visible control when an editor or select replaces its source.
      const select = selectFor(field);
      const target = editorFor(field)?.getAccessibleElement?.() || select?.focus_node || field;
      const feedback = document.createElement('div');
      feedback.id = `${idPrefix}-field-error-${++nextId}`;
      feedback.className = 'invalid-feedback d-block text-break';
      state = {feedback, target, invalid: target.getAttribute('aria-invalid'), hadClass: field.classList.contains('is-invalid')};
      if (select?.wrapper) {
        state.wrapper = select.wrapper;
        state.wrapperHadClass = select.wrapper.classList.contains('is-invalid');
        select.wrapper.classList.add('is-invalid');
      }
      errors.set(field, state);
      const container = field.closest('.iti, .input-group, [data-required-checkbox-group], [role="radiogroup"]')
        || field.closest('.form-check') || (field.nextElementSibling?.matches('.ck-editor') ? field.nextElementSibling : select?.wrapper || field);
      container.after(feedback);
      const ids = (target.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
      setFieldAttribute(field, target, 'aria-describedby', [...ids, feedback.id].join(' '));
      setFieldAttribute(field, target, 'aria-invalid', 'true');
      field.classList.add('is-invalid');
    }
    state.feedback.textContent = message || (field as Field).validationMessage || labels.required || 'This field is required.';
  }

  function clearFormError(form: HTMLFormElement): void {
    summaries.get(form)?.remove();
    summaries.delete(form);
    // Values can be filled programmatically without input/change events.
    for (const field of Array.from(form.elements) as Field[]) {
      if (field.validity?.valid && !(field as Field & {editor?: unknown}).editor) clearFieldError(field);
    }
  }

  function showFormError(form: HTMLFormElement, message: string): void {
    summaries.get(form)?.remove();
    const labels = getLabels();
    const summary = document.createElement('div');
    summary.className = 'alert alert-danger d-block text-break';
    summary.tabIndex = -1;
    summary.setAttribute('role', 'region');
    summary.setAttribute('data-fb-form-error', '');
    if (summaryDataKey) summary.dataset[summaryDataKey] = '';
    const title = document.createElement('h2');
    title.className = 'h3 mb-2';
    title.id = `${idPrefix}-form-error-${++nextId}`;
    title.textContent = labels.formError || 'There Was a Problem';
    summary.setAttribute('aria-labelledby', title.id);
    const description = document.createElement('p');
    description.className = 'mb-0';
    description.id = `${title.id}-description`;
    summary.setAttribute('aria-describedby', description.id);
    description.textContent = message;
    summary.append(title, description);
    const list = document.createElement('ul');
    list.className = 'mt-2 mb-0';
    for (const field of Array.from(form.elements) as HTMLElement[]) {
      const state = errors.get(field);
      if (!state || !field.id) continue;
      const item = document.createElement('li');
      const link = document.createElement('a');
      link.href = `#${field.id}`;
      link.textContent = [...((field as Field).labels || [])].map(label => label.textContent?.replace(/\s*\*\s*$/, '').trim()).join(' ') || state.feedback.textContent;
      link.addEventListener('click', event => { event.preventDefault(); focusField(field); });
      item.append(link); list.append(item);
    }
    if (list.childElementCount) summary.append(list);
    const container = form.closest('.modal') ? form.querySelector<HTMLElement>('.modal-body') || form : form;
    container.prepend(summary);
    summaries.set(form, summary);
    if (form.getClientRects().length) {
      summary.focus();
      summary.scrollIntoView({block: 'start', inline: 'nearest', behavior: 'instant'});
    }
    else FOSSBilling.message(message, 'error');
  }

  function initFormErrors(): void {
    if (initialized) return;
    initialized = true;
    // An extension can provide its own error presentation hooks.
    FOSSBilling.ui.formError ||= showFormError;
    FOSSBilling.ui.clearFormError ||= clearFormError;
    FOSSBilling.ui.fieldError ||= showFieldError;
    FOSSBilling.ui.clearFieldError ||= clearFieldError;
    initRequiredChoices();
    document.addEventListener('invalid', event => {
      let field = event.target as Field;
      if (field.type === 'radio' && !field.validity.customError && field.form) {
        field = [...field.form.elements].find(control => (control as Field).name === field.name
          && (control as Field).type === 'radio' && !(control as Field).disabled) as Field || field;
      }
      if (field.matches('input:not([type="hidden"]), select, textarea')) FOSSBilling.ui.fieldError(field);
      if (selectFor(field) || (!field.getClientRects().length && field.closest('.tab-pane:not(.active)'))) {
        // Reveal tabbed fields and focus enhanced controls only when they are
        // first in the invalid-control order. Hidden sources cannot show bubbles.
        event.preventDefault();
        const first = field.form && [...field.form.elements].find(control =>
          (control as Field).willValidate && !(control as Field).validity.valid);
        if (!first || first === field) focusField(field);
      }
    }, true);
    const clearCorrected = (event: Event) => {
      const field = event.target as Field;
      if (!field.matches?.('input, select, textarea')) return;
      if (field.type === 'radio' && field.form) {
        for (const control of [...field.form.elements] as Field[]) {
          if (control.name === field.name && control.type === 'radio' && control.validity.valid) FOSSBilling.ui.clearFieldError(control);
        }
      }
      if (field.validity.valid) FOSSBilling.ui.clearFieldError(field);
      else if (errors.has(field)) FOSSBilling.ui.fieldError(field);
    };
    document.addEventListener('input', clearCorrected);
    document.addEventListener('change', clearCorrected);
  }

  return {initFormErrors, clearFieldError, showFieldError, clearFormError, showFormError};
}
