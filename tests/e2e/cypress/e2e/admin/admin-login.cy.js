describe('admin authentication', () => {
  it('logs in as the installed administrator', () => {
    cy.loginAsAdmin();
    cy.title().should('include', 'Dashboard');
    cy.contains('body', 'Clients').should('be.visible');
  });
});
