import { createFormLoading } from '../../../../../../../frontend/ui/form-loading.ts';

// Preserve the client entrypoint and translated dataset key.
const presenter = createFormLoading(() =>
  document.querySelector<HTMLElement>('.toast-container')?.dataset.clientLoading || 'Submitting. Please wait.');
export const {setFormLoading} = presenter;
export default presenter.initFormLoading;
