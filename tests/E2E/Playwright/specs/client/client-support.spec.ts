import { authenticatedGet, expect, test } from '../../fixtures/e2e';
import { uniqueSuffix } from '../../helpers/client-factory';
import {
  openBootstrapModal,
  setEditorContent,
  submitForm,
  typeAndVerify,
  waitForApiResponse,
} from '../../helpers/forms';

test('opens, replies to, and closes a support ticket', async ({ clientPage }) => {
  const suffix = uniqueSuffix();
  const subject = `Playwright support lifecycle ${suffix}`;
  const initialMessage = `Initial support request from Playwright ${suffix}.`;
  const replyMessage = `Follow-up support reply from Playwright ${suffix}.`;

  await clientPage.goto('/support');
  await expect(clientPage.locator('body')).toContainText('Support Tickets');

  // Open the ticket creation modal and submit a new ticket.
  const ticketCreate = waitForApiResponse(clientPage, '/api/client/support/ticket_create');
  const modal = clientPage.locator('#open-ticket-modal');
  await openBootstrapModal(clientPage.locator('[data-bs-target="#open-ticket-modal"]'), modal);

  await expect(modal.locator('select[name="support_helpdesk_id"]')).not.toHaveValue('');
  await setEditorContent(modal, initialMessage);
  await typeAndVerify(modal.locator('input[name="subject"]'), subject);
  await submitForm(modal.locator('#ticket-submit'));

  expect((await ticketCreate).status()).toBe(200);

  // The app redirects to /support/ticket/{id}; the final API check below proves
  // every persisted detail of this exchange.
  const ticketPathPattern = /^\/support\/ticket\/(\d+)$/;
  await expect
    .poll(() => new URL(clientPage.url()).pathname.match(ticketPathPattern)?.[1] ?? '', { timeout: 10_000 })
    .not.toBe('');
  const ticketId = new URL(clientPage.url()).pathname.match(ticketPathPattern)![1];

  await expect(clientPage.locator('body')).toContainText(subject);
  await expect(clientPage.locator('article.markdown-body')).toContainText(initialMessage);

  // Reply to the ticket.
  const ticketReply = waitForApiResponse(clientPage, '/api/client/support/ticket_reply');
  await setEditorContent(clientPage.locator('#reply-to'), replyMessage);
  await submitForm(clientPage.locator('#ticket-reply-form'));

  expect((await ticketReply).status()).toBe(200);
  await expect(clientPage.locator('article.markdown-body').last()).toContainText(replyMessage);

  // Close the ticket.
  const ticketClose = waitForApiResponse(clientPage, '/api/client/support/ticket_close');
  await clientPage.getByRole('button', { name: 'Close Ticket' }).click();

  expect((await ticketClose).status()).toBe(200);
  await expect(clientPage.getByRole('button', { name: 'Close Ticket' })).toHaveCount(0);

  // Verify the final state through the API.
  const finalTicket = await authenticatedGet(clientPage.context(), '/api/client/support/ticket_get', {
    id: ticketId,
  });
  expect(finalTicket.status()).toBe(200);

  const finalTicketBody = await finalTicket.json();
  expect(finalTicketBody.error).toBeNull();
  expect(finalTicketBody.result.status).toBe('closed');
  expect(finalTicketBody.result.subject).toBe(subject);
  expect(finalTicketBody.result.messages).toHaveLength(2);
});
