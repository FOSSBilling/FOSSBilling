/**
 * Huraga theme utilities - minimal version of FOSSBilling utilities
 */
import { showToastMessage } from "../../../../../../frontend/core/toast.mts";

window.FOSSBilling = Object.assign(window.FOSSBilling || {}, {
  message: (message: string, type = "info") => showToastMessage(message, type, bootstrap.Toast),
});
