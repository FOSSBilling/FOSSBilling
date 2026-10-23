type FlashOptions = {message?: string; reload?: boolean | string; type?: string};

/** Preserve the legacy plain-text keys used by client modules and extensions. */
export function queueFlashMessage(message: string, type = 'info'): void {
  try {
    sessionStorage.setItem('flash-message', message);
    sessionStorage.setItem('flash-message-type', type);
  } catch {
    // Avoid leaving a new message paired with an old severity after a partial write.
    try {
      sessionStorage.removeItem('flash-message');
      sessionStorage.removeItem('flash-message-type');
    } catch { /* Storage may be unavailable in private or embedded contexts. */ }
  }
}

function notify(message: string, type: string): void {
  if (FOSSBilling.ui?.notify) FOSSBilling.ui.notify(message, type);
  else FOSSBilling.message(message, type);
}

export default function flashMessage({message = '', reload = false, type = 'info'}: FlashOptions = {}): void {
  if (!message) {
    let saved: string | null;
    let savedType: string;
    try {
      saved = sessionStorage.getItem('flash-message');
      savedType = sessionStorage.getItem('flash-message-type') || type;
      sessionStorage.removeItem('flash-message');
      sessionStorage.removeItem('flash-message-type');
    } catch { return; }
    if (saved) notify(saved, savedType);
    return;
  }

  if (reload) {
    queueFlashMessage(message, type);
    if (typeof reload === 'string') window.location.assign(reload);
    else window.location.reload();
  } else notify(message, type);
}
