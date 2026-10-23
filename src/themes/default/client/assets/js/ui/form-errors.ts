import { createFormErrors } from '../../../../../../../frontend/ui/form-errors.ts';

// Compatibility entrypoint for client modules and extensions.
const presenter = createFormErrors({
  idPrefix: 'client',
  summaryDataKey: 'clientFormError',
  getLabels: () => {
    const labels = document.querySelector<HTMLElement>('.toast-container')?.dataset || {};
    return {required: labels.clientRequired, formError: labels.clientFormError};
  },
});
export const {clearFieldError, showFieldError, clearFormError, showFormError} = presenter;
export default presenter.initFormErrors;
