describe('Huraga:Index', () => {

  it('successfully loads', function() {
    cy.visit('/');
    cy.location('pathname').should('eq', '/');


    /* ==== Generated with Cypress Studio ==== */
    cy.get(':nth-child(3) > .show-tip').should('have.text', 'Login');
    cy.get(':nth-child(4) > .show-tip').should('have.text', 'Register');
    /* ==== End Cypress Studio ==== */
  });

  it('successfully loads when logged in', function () {
    cy.clientLogin()

    cy.visit('/');
    cy.location('pathname').should('eq', '/');


    /* ==== Generated with Cypress Studio ==== */
    cy.get(':nth-child(3) > .show-tip').should('have.text', 'Profile');
    cy.get(':nth-child(4) > .show-tip').should('have.text', 'Sign out');
    /* ==== End Cypress Studio ==== */
  })

})