describe('client authentication', () => {
  it('creates a new client account and automatically logs in', () => {
    const suffix = `${Date.now()}-${Cypress._.random(100000, 999999)}`;
    const client = {
      first_name: 'Cypress',
      last_name: 'Signup',
      email: `cypress-signup-${suffix}@example.com`,
      password: 'CypressClient1!',
    };

    cy.visit('/signup');
    cy.contains('body', 'Create a new account').should('be.visible');
    cy.intercept('POST', '**/api/guest/client/create*').as('clientSignup');

    cy.fillClientSignupForm(client);
    cy.get('form[action*="/api/guest/client/create"]').submit();

    cy.wait('@clientSignup').then(({ response }) => {
      expect(response.statusCode).to.eq(200);
      expect(response.body.error).to.eq(null);
      // guest/client/create no longer returns the new client's id (doing so
      // would reopen the email-enumeration issue it was hardened against);
      // account creation and the logged-in session are proven below instead.
      expect(response.body.result).to.eq(true);
    });

    cy.location('pathname', { timeout: 10000 }).should('eq', '/');
    cy.getCookie('fossbilling_csrf').should('exist').then((cookie) => {
      cy.request({
        url: '/api/client/profile/get',
        qs: { CSRFToken: cookie.value },
      }).then((response) => {
        expect(response.status).to.eq(200);
        expect(response.body.error).to.eq(null);
        expect(response.body.result.email).to.eq(client.email);
      });
    });
  });
});
