/** Let unstyled email documents follow the embedding theme's colour scheme. */
export function prepareEmailPreview(source: string): string {
  const document = new DOMParser().parseFromString(source, 'text/html');
  // An email may deliberately declare its own supported colour schemes.
  if (document.querySelector('meta[name="color-scheme" i]')) return source;
  // Authored styles may rely on a light canvas (for example black text without
  // an explicit background). Keep those messages intact rather than inverting
  // branding or creating dark text on a dark background.
  if (document.querySelector('style, link[rel~="stylesheet" i], [color], [bgcolor]')
      || [...document.querySelectorAll<HTMLElement>('[style]')].some(element => element.style.color || element.style.background)) return source;
  const scheme = document.createElement('meta');
  scheme.name = 'color-scheme';
  scheme.content = 'light dark';
  document.head.prepend(scheme);
  const doctype = document.doctype ? new XMLSerializer().serializeToString(document.doctype) : '';
  return doctype + document.documentElement.outerHTML;
}

/** Preserve a helper installed by an extension. */
export function initEmailPreviewSupport(): void {
  FOSSBilling.ui.prepareEmailPreview ??= prepareEmailPreview;
}
