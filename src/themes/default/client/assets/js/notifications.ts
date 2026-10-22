type ReadSummary = { unread_count: number };

function post(endpoint: string, data: Record<string, unknown>): Promise<ReadSummary> {
  return new Promise((resolve, reject) => {
    FOSSBilling.api.client.post(endpoint, data, (result) => resolve(result as ReadSummary), reject);
  });
}

export default function initNotifications(): void {
  const menu = document.querySelector<HTMLElement>('[data-client-notifications]');
  if (!menu) return;

  const entries = [...menu.querySelectorAll<HTMLAnchorElement>('[data-notification-id]')];
  const readAll = menu.querySelector<HTMLButtonElement>('[data-notification-read-all]');

  const update = (summary: ReadSummary, matches: (entry: HTMLAnchorElement) => boolean) => {
    const count = menu.querySelector<HTMLElement>('[data-notification-count]');
    if (count) count.textContent = String(summary.unread_count);
    const label = menu.querySelector<HTMLElement>('[data-notification-count-label]');
    if (label) label.textContent = String(summary.unread_count);
    if (count) count.hidden = summary.unread_count === 0;
    if (readAll) {
      const hadFocus = document.activeElement === readAll;
      readAll.hidden = summary.unread_count === 0;
      if (hadFocus && readAll.hidden) menu.querySelector<HTMLElement>('[data-bs-toggle="dropdown"]')?.focus();
    }
    for (const entry of entries.filter(matches)) {
      entry.dataset.notificationRead = '1';
      entry.classList.remove('fw-semibold');
      const unread = entry.querySelector<HTMLElement>('[data-notification-unread]');
      if (unread) unread.hidden = true;
    }
  };

  for (const entry of entries) {
    entry.addEventListener('click', async (event) => {
      // Preserve new-tab and other native link gestures. The conversation page marks viewed replies read.
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || entry.dataset.notificationRead === '1') return;
      event.preventDefault();
      try {
        await post('support/notification_mark_read', { id: entry.dataset.notificationId });
        window.location.assign(entry.href);
      } catch (error) {
        FOSSBilling.message(error.message, 'error');
      }
    });
  }

  readAll?.addEventListener('click', async () => {
    const hadFocus = document.activeElement === readAll;
    readAll.disabled = true;
    try {
      const summary = await post('support/notification_mark_all_read', { through_id: menu.dataset.latestId });
      update(summary, () => true);
      // Disabling the button can move focus to the document before the response arrives.
      if (hadFocus && readAll.hidden && (document.activeElement === document.body || document.activeElement === readAll)) {
        menu.querySelector<HTMLElement>('[data-bs-toggle="dropdown"]')?.focus();
      }
    } catch (error) {
      FOSSBilling.message(error.message, 'error');
    } finally {
      readAll.disabled = false;
    }
  });

  const ticket = document.querySelector<HTMLElement>('[data-client-ticket-read]');
  if (ticket) {
    post('support/notification_read_ticket', {
      ticket_id: ticket.dataset.clientTicketRead,
      through_message_id: ticket.dataset.throughMessageId,
    }).then((summary) => {
      update(summary, (entry) => entry.dataset.notificationTicket === ticket.dataset.clientTicketRead
        && BigInt(entry.dataset.notificationMessage) <= BigInt(ticket.dataset.throughMessageId));
    }).catch((error) => FOSSBilling.message(error.message, 'error'));
  }
}
