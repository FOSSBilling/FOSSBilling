import {prepareEmailPreview, initEmailPreviewSupport} from '../../../../../../../frontend/ui/email-preview.ts';
export {prepareEmailPreview};

const initialized = new WeakSet<HTMLIFrameElement>();
export default function initEmailPreviews(): void {
  initEmailPreviewSupport();
  document.querySelectorAll<HTMLIFrameElement>('iframe.client-email-content[srcdoc]').forEach(frame => {
    if (initialized.has(frame)) return;
    initialized.add(frame);
    // Parsing stays inert; the document is only displayed in the existing sandbox.
    const source = frame.srcdoc;
    const prepared = FOSSBilling.ui.prepareEmailPreview!(source);
    if (prepared !== source) frame.srcdoc = prepared;
  });
}
