type Editable = (HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement) & {editor?: {getData(): string}};

/** Compare values in memory only; never persist drafts, passwords or uploads. */
export function formSnapshot(form: HTMLFormElement): string {
  return JSON.stringify([...form.elements].filter((element): element is Editable => {
    const field = element as Editable;
    return !!field.name && ['INPUT', 'SELECT', 'TEXTAREA'].includes(field.tagName)
      && (!['hidden', 'submit', 'button', 'reset', 'image'].includes(field.type) || /(^|\[)phone_cc\]?$/.test(field.name))
      && !field.closest('[data-unsaved-ignore]');
  }).map(field => {
    if (field.editor) {
      try { return [field.name, field.editor.getData()]; }
      catch { return [field.name, field.value]; } // The source retains the last editor value after a crash.
    }
    if (field.type === 'checkbox' || field.type === 'radio') return [field.name, field.value, (field as HTMLInputElement).checked];
    if (field.type === 'file') return [field.name, [...((field as HTMLInputElement).files || [])].map(file => [file.name, file.size, file.lastModified])];
    if (field.type === 'tel') {
      let number = field.value.replace(/[\s().-]/g, '');
      const dialName = field.name.replace(/phone(\]?)$/, 'phone_cc$1');
      const dialCode = ([...form.elements].find(element => (element as Editable).name === dialName) as Editable)?.value;
      if (dialCode && number.startsWith(`+${dialCode}`)) number = number.slice(dialCode.length + 1);
      return [field.name, number];
    }
    if (field.tagName === 'SELECT') return [field.name, [...(field as HTMLSelectElement).selectedOptions].map(option => option.value)];
    return [field.name, field.value];
  }));
}

export class UnsavedForms {
  private states = new WeakMap<HTMLFormElement, {baseline: string; visited: boolean}>();
  private submissions = new WeakMap<HTMLFormElement, string>();

  register(form: HTMLFormElement): void {
    if (!this.states.has(form)) this.states.set(form, {baseline: formSnapshot(form), visited: false});
  }

  visit(form: HTMLFormElement): void {
    this.register(form);
    const state = this.states.get(form)!;
    state.visited = true;
  }

  saved(form: HTMLFormElement): void {
    this.states.set(form, {baseline: this.submissions.get(form) ?? formSnapshot(form), visited: true});
  }

  submitting(form: HTMLFormElement, busy: boolean): void {
    if (busy) this.submissions.set(form, formSnapshot(form));
    else this.submissions.delete(form);
  }

  dirty(form: HTMLFormElement): boolean {
    const state = this.states.get(form);
    return form.getAttribute('aria-busy') === 'true'
      || !!state?.visited && state.baseline !== formSnapshot(form);
  }
}

let initialized = false;
export default function initUnsavedChanges(): void {
  if (initialized) return;
  initialized = true;
  const forms = new UnsavedForms();
  const selector = 'form[data-unsaved-changes]';
  document.querySelectorAll<HTMLFormElement>(selector).forEach(form => forms.register(form));
  for (const name of ['focusin', 'input', 'change']) {
    document.addEventListener(name, event => {
      const form = (event.target as Element)?.closest?.<HTMLFormElement>(selector);
      if (form) forms.visit(form);
    });
  }
  // Additive hook: keep an extension's presenter and notify this tracker too.
  const previousSaved = FOSSBilling.ui.formSaved;
  FOSSBilling.ui.formSaved = form => {
    if (form?.matches(selector)) forms.saved(form);
    previousSaved?.(form);
  };
  const previousLoading = FOSSBilling.ui.formLoading;
  FOSSBilling.ui.formLoading = (form, busy, submitter) => {
    if (form.matches(selector)) forms.submitting(form, busy);
    previousLoading?.(form, busy, submitter);
  };
  window.addEventListener('beforeunload', event => {
    const dirty = [...document.querySelectorAll<HTMLFormElement>(selector)].some(form => forms.dirty(form));
    if (!dirty) return;
    event.preventDefault();
    event.returnValue = '';
  });
}
