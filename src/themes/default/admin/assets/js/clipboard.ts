import { Clipboard } from '@tabler/core';

const CLIPBOARD_CONTROL_SELECTOR = '.clipboard-copy, [data-bs-toggle="clipboard"]';
const LEGACY_TARGET_SELECTOR = /^#[A-Za-z0-9_-]+$/;

// Tabler uses the Clipboard API; retain the fallback for HTTP and denied access.
function copyWithLegacyFallback(text: string, button: HTMLElement): boolean {
  const textarea = document.createElement('textarea');

  try {
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    return document.execCommand('copy');
  } catch {
    return false;
  } finally {
    textarea.remove();
    button.focus({ preventScroll: true });
  }
}

function initializeClipboard(button: HTMLElement): void {
  // Map the legacy attribute for third-party extensions.
  const legacyTarget = button.dataset.clipboardTarget;
  if (!button.hasAttribute('data-bs-target') && legacyTarget && LEGACY_TARGET_SELECTOR.test(legacyTarget)) {
    button.dataset.bsTarget = legacyTarget;
  }

  Clipboard.getOrCreateInstance(button);
}

export default function initClipboard(): void {
  document.querySelectorAll<HTMLElement>(CLIPBOARD_CONTROL_SELECTOR).forEach(initializeClipboard);

  // Capture clicks to initialize dynamically added controls before they bubble.
  document.addEventListener('click', event => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    const button = target.closest<HTMLElement>(CLIPBOARD_CONTROL_SELECTOR);
    if (button) {
      initializeClipboard(button);
    }
  }, true);

  document.addEventListener('error.bs.clipboard', event => {
    const button = event.target;
    if (!(button instanceof HTMLElement) || !button.matches('.clipboard-copy')) {
      return;
    }

    const clipboard = Clipboard.getInstance(button) as Clipboard | null;
    if (!clipboard?.text) {
      return;
    }

    if (copyWithLegacyFallback(clipboard.text, button)) {
      // Tabler has no public method for showing feedback after a fallback copy.
      clipboard._showCopied();
    } else if (typeof FOSSBilling !== 'undefined' && FOSSBilling.message) {
      FOSSBilling.message('Failed to copy to clipboard. Please select the text and copy it manually.', 'error');
    }
  });
}
