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
      cy.visit(path);
      cy.location('pathname').should('eq', path);
      cy.get('body').should('be.visible');
    });
  });
});
