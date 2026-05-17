describe('admin smoke pages', () => {
  const pages = [
    '/admin',
    '/admin/client',
    '/admin/order',
    '/admin/invoice',
    '/admin/product',
    '/admin/system',
    '/admin/extension',
  ];

  it('loads core admin pages successfully', () => {
    cy.loginAsAdmin();

    pages.forEach((path) => {
      cy.request(path).its('status').should('eq', 200);

      cy.visit(path);
      cy.get('body').should('be.visible');
    });
  });
});
