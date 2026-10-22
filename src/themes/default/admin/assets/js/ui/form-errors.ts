import { createFormErrors } from '../../../../../../../frontend/ui/form-errors.ts';

const presenter = createFormErrors();
export const {clearFieldError, showFieldError, clearFormError, showFormError} = presenter;
export default presenter.initFormErrors;
