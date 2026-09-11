/**
 * Tooltip initialization shared by the default admin and client themes.
 *
 * The caller passes its own theme's Tooltip constructor (each theme's Tabler
 * bundle) plus any default options — admin uses `{ trigger: 'hover' }`,
 * client uses none.
 */

export interface TooltipConstructor {
  new (element: Element, options?: Record<string, unknown>): unknown;
}

export function initTooltips(Tooltip: TooltipConstructor, options: Record<string, unknown> = {}): void {
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
    new Tooltip(el, options);
  });
}
