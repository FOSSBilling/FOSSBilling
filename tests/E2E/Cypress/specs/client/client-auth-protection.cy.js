describe('client area authentication protections', () => {
  const protectedPages = [
    '/client',
    '/client/profile',
    '/order/service',
    '/invoice',
    '/support',
    '/email',
  ];

  protectedPages.forEach((path) => {
    it(`redirects logged-out requests for ${path} to the client login`, () => {
      cy.clearCookies();
      cy.clearLocalStorage();

      cy.request({
        url: path,
        followRedirect: false,
        failOnStatusCode: false,
      }).then((response) => {
        expect(response.status).to.eq(302);
        expect(response.headers.location).to.match(/\/login\/?$/);
      });

      cy.visit(path);
      cy.location('pathname').should('match', /^\/login\/?$/);
      cy.contains('body', 'Login to Your Account').should('be.visible');
    });
  });
});
