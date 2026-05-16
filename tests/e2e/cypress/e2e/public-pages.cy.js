describe('public pages', () => {
  const pages = [
    { path: '/', text: null },
    { path: '/login', text: 'Login to Your Account' },
    { path: '/signup', text: 'Create a new account' },
    { path: '/password-reset', text: 'Reset Your Password' },
    { path: '/order', text: null },
  ];

  pages.forEach(({ path, text }) => {
    it(`loads ${path}`, () => {
      cy.request(path).its('status').should('eq', 200);
      cy.visit(path);
      cy.get('body').should('be.visible');

      if (text) {
        cy.contains('body', text).should('be.visible');
      }
    });
  });
});
