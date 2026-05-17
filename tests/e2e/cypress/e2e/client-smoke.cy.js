describe('client smoke pages', () => {
  let client;

  const pages = [
    { path: '/', text: 'Dashboard' },
    { path: '/client/profile', text: 'Update Details' },
    { path: '/order/service', text: 'Services' },
    { path: '/invoice', text: 'Invoices' },
    { path: '/support', text: 'Support Tickets' },
    { path: '/email', text: 'Emails' },
  ];

  before(() => {
    cy.testClient().then((testClient) => {
      client = testClient;
    });
  });

  it('loads core client pages successfully', () => {
    cy.loginAsClient(client);

    pages.forEach(({ path, text }) => {
      cy.request(path).its('status').should('eq', 200);

      cy.visit(path);
      cy.get('body').should('be.visible');
      cy.contains('body', text).should('be.visible');
    });
  });
});
