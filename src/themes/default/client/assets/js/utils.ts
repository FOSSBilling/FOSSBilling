import { showToast } from '../../../../../../frontend/ui/tabler-toast.ts';
import { announceMessage } from './ui/announcements.ts';

window.FOSSBilling = Object.assign(window.FOSSBilling || {}, {
  message: (message: string, type = 'info') => {
    const labels = document.querySelector<HTMLElement>('.toast-container')?.dataset;
    showToast(message, type, {
      title: labels?.clientMessage || 'System Message',
      closeLabel: labels?.clientClose || 'Close',
      autohide: type !== 'error',
      restoreFocus: true,
      announce: announceMessage,
    });
  }
});
