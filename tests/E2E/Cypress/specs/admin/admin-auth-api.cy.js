describe('admin auth and API protections', () => {
  it('redirects logged-out admin pages to the staff login', () => {
    cy.clearCookies();
    cy.clearLocalStorage();

    cy.visit('/admin/client');

    cy.location('pathname').should('match', /^\/admin\/staff\/login\/?$/);
    cy.contains('body', 'Login').should('be.visible');
  });

  it('allows authenticated admin profile API requests', () => {
    cy.loginAsAdmin();

    cy.getCookie('fossbilling_csrf').should('exist').then((cookie) => {
      cy.request({
        url: '/api/admin/profile/get',
        qs: { CSRFToken: cookie.value },
      }).then((response) => {
        expect(response.status).to.eq(200);
        expect(response.body.error).to.eq(null);
        expect(response.body.result.id, 'admin id').to.exist;
        expect(response.body.result.email, 'admin email').to.eq(Cypress.env('adminEmail'));
      });
    });
  });

  it('rejects admin API POST requests without a CSRF token', () => {
    cy.loginAsAdmin();

    cy.request({
      method: 'POST',
      url: '/api/admin/profile/get',
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.status).to.eq(403);
      expect(response.body.result).to.eq(null);
      expect(response.body.error.message).to.eq('CSRF token invalid');
    });
  });
});
