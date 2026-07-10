/**
 * TenantNinja theme utilities - minimal version of FOSSBilling utilities
 * Only includes functionality actually used by the TenantNinja theme
 */

const FOSSBilling = (globalThis.FOSSBilling = globalThis.FOSSBilling || {});

// Toast v2 - tinted icon chip, inline action, countdown hairline, rise-in.
// Ported from the Claude Design project (redesign/ds-extra/Toast.jsx) so the
// runtime toast matches the design spec exactly. Replaces the old Bootstrap
// `.toast` markup while keeping the FOSSBilling.message(message, type) contract.
const TOAST_ICONS = {
  success: '<path d="M5 10.5L8.5 14L15 6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
  warning: '<path d="M10 6v5M10 14.2v.1M2 16.5L10 3l8 13.5H2z" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
  danger:  '<path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
  info:    '<path d="M10 9v5M10 6v.1M2 10a8 8 0 1116 0 8 8 0 01-16 0z" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>',
  neutral: '<path d="M2 10a8 8 0 1116 0 8 8 0 01-16 0z" stroke="currentColor" stroke-width="1.6" fill="none"/>',
};

// Map the legacy FOSSBilling message types onto the design's toast variants.
const TOAST_VARIANT = { error: "danger", warning: "warning", success: "success", info: "info" };

function getToastStack() {
  // Reuse the existing fixed container but promote it to the v2 stack layout.
  const container = document.querySelector(".toast-container");
  if (!container) {
    console.warn("Toast container not found for FOSSBilling.toast()");
    return null;
  }
  container.classList.add("tn-toast-stack");
  // Bootstrap's positioning utility classes fight our fixed stack; drop them.
  container.classList.remove("position-fixed", "bottom-0", "end-0", "p-3");
  container.style.removeProperty("z-index");
  return container;
}

/**
 * Render a Toast v2 notification.
 * @param {Object} opts
 * @param {'success'|'warning'|'danger'|'info'|'neutral'} [opts.variant]
 * @param {string} [opts.title]        - bold heading (defaults to a system label)
 * @param {string} [opts.description]  - supporting line
 * @param {string} [opts.action]       - inline action label
 * @param {Function} [opts.onAction]   - inline action handler
 * @param {number} [opts.duration]     - ms before auto-dismiss (0 = sticky)
 */
FOSSBilling.toast = (opts = {}) => {
  const stack = getToastStack();
  if (!stack) return;

  const variant = opts.variant || "neutral";
  const duration = opts.duration ?? 6000;

  const el = document.createElement("div");
  el.className = `tn-toast tn-toast--${variant}`;
  el.setAttribute("role", "status");

  const chip = document.createElement("div");
  chip.className = "tn-toast__chip";
  chip.innerHTML = `<svg width="16" height="16" viewBox="0 0 20 20" fill="none">${TOAST_ICONS[variant] || TOAST_ICONS.neutral}</svg>`;
  el.appendChild(chip);

  const body = document.createElement("div");
  body.className = "tn-toast__body";
  if (opts.title) {
    const t = document.createElement("div");
    t.className = "tn-toast__title";
    t.textContent = opts.title;
    body.appendChild(t);
  }
  if (opts.description) {
    const d = document.createElement("div");
    d.className = "tn-toast__desc";
    d.textContent = opts.description;
    body.appendChild(d);
  }
  if (opts.action) {
    const a = document.createElement("button");
    a.className = "tn-toast__action";
    a.type = "button";
    a.innerHTML = `<span></span><svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M2 6h8M6.5 2.5L10 6l-3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
    a.firstChild.textContent = opts.action;
    a.addEventListener("click", () => {
      if (typeof opts.onAction === "function") opts.onAction();
      dismiss();
    });
    body.appendChild(a);
  }
  el.appendChild(body);

  const close = document.createElement("button");
  close.className = "tn-toast__close";
  close.type = "button";
  close.setAttribute("aria-label", "Dismiss");
  close.innerHTML = `<svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M2 2l8 8M10 2l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>`;
  el.appendChild(close);

  let timer;
  const dismiss = () => {
    if (el.dataset.dismissing) return;
    el.dataset.dismissing = "1";
    clearTimeout(timer);
    el.classList.add("tn-toast--out");
    el.addEventListener("animationend", () => el.remove(), { once: true });
    // Fallback in case reduced-motion removes the exit animation.
    setTimeout(() => el.remove(), 400);
  };
  close.addEventListener("click", dismiss);

  if (duration > 0) {
    const bar = document.createElement("div");
    bar.className = "tn-toast__timer";
    bar.style.animationDuration = `${duration}ms`;
    el.appendChild(bar);
    timer = setTimeout(dismiss, duration);
  }

  stack.appendChild(el);
  return el;
};

// Legacy entry point kept intact for core + module callers.
FOSSBilling.message = (message, type = "info") => {
  FOSSBilling.toast({
    variant: TOAST_VARIANT[type] || "neutral",
    title: type === "error" ? "Something went wrong" : "System message",
    description: message,
  });
};

globalThis.FOSSBilling = Object.assign(globalThis.FOSSBilling || {}, {
  message: FOSSBilling.message,
  toast: FOSSBilling.toast
});
