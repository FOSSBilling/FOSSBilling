describe('Huraga:Login', () => {

  it('successfully loads', function() {
    cy.visit('/login');

    cy.get('h2').should('have.text', 'Login');
    cy.get('.btn').should('be.visible');
  });

  it('displays an error for failed login attempts', function() {
    cy.visit('/login');

    cy.get('#icon').type('this-will-fail@fossbilling.org');
    cy.get('#password').type('fossbilling.org');

    cy.get('.btn').click();
    /* ==== Generated with Cypress Studio ==== */
    cy.get('.jGrowl-message').should('have.text', 'Please check your login details.');
    /* ==== End Cypress Studio ==== */
  });

  it('successfully logs in', function () {
    cy.clientLogin()
  })

})