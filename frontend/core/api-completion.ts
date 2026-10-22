import { queueFlashMessage } from './flash.ts';

type CompletionOptions = {callback?: string; redirect?: string; reload?: unknown; message?: string};

/** Shared completion policy for API forms and links; callbacks own their outcome. */
export function completeApiAction(options: CompletionOptions, result: unknown): unknown {
  if (Object.hasOwn(options, 'callback')) {
    if (typeof window[options.callback] === 'function') return window[options.callback](result);
    console.warn('Invalid callback function:', options.callback);
  }

  const hasMessage = Object.hasOwn(options, 'message');
  const message = typeof options.message === 'string' ? options.message : '';
  if (Object.hasOwn(options, 'redirect')) {
    const destination = new URL(options.redirect, window.location.href);
    if (message && destination.origin === window.location.origin) queueFlashMessage(message, 'success');
    if (destination.href === window.location.href) window.location.reload();
    else window.location.assign(destination.href);
    return;
  }

  // Preserve the existing presence-based reload contract, including reload:false.
  if (Object.hasOwn(options, 'reload')) {
    if (message) queueFlashMessage(message, 'success');
    window.location.reload();
    return;
  }

  if (hasMessage) {
    if (message) window.FOSSBilling.ui.notify(message, 'success');
    return;
  }

  if (result) {
    const label = document.querySelector<HTMLElement>('.toast-container')?.dataset.formUpdated || 'Changes Saved';
    window.FOSSBilling.ui.notify(label, 'success');
    return;
  }

  console.warn('Unhandled API response in _afterComplete:', options);
}
