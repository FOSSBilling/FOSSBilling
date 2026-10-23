import { expect, test } from '../../fixtures/e2e';

const pages = [
  { path: '/', text: 'Dashboard' },
  { path: '/client/profile', text: 'Profile' },
  { path: '/order/service', text: 'Services' },
  { path: '/invoice', text: 'Invoices' },
  { path: '/support', text: 'Support Tickets' },
  { path: '/email', text: 'Emails' },
];

test('loads core client pages successfully', async ({ clientContext, clientPage }) => {
  for (const { path, text } of pages) {
    const response = await clientContext.request.get(path);
    expect(response.status()).toBe(200);

    await clientPage.goto(path);
    await expect(clientPage.locator('body')).toBeVisible();
    await expect(clientPage.getByRole('heading', { name: text, level: 1 })).toBeVisible();
  }
});
