Cypress.Commands.add('loginAsAdmin', () => {
  const email = Cypress.env('adminEmail');
  const password = Cypress.env('adminPassword');

  expect(email, 'admin email').to.be.a('string').and.not.be.empty;
  expect(password, 'admin password').to.be.a('string').and.not.be.empty;

  cy.visit('/admin/staff/login');
  cy.intercept('POST', '**/api/guest/staff/login*').as('staffLogin');

  cy.get('form[action*="/api/guest/staff/login"]').within(() => {
    cy.get('input[name="email"]').clear().type(email);
    cy.get('input[name="password"]').clear().type(password, { log: false });
    cy.root().submit();
  });

  cy.wait('@staffLogin').its('response.statusCode').should('eq', 200);
  cy.location('pathname', { timeout: 10000 }).should('match', /^\/admin\/?$/);
});
