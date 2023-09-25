describe('Admin:Login', () => {

  it('successfully loads', function() {
    cy.visit('/admin');

    cy.location('pathname').should('eq', '/admin/staff/login') // test the redirect

    cy.get('.h2').should('have.text', 'Log into your account');
    cy.get('.btn').should('be.visible');

  });

  it('displays an error for failed login attempts', function() {
    cy.visit('/admin/staff/login');

    cy.get('#inputEmail').type('this-will-fail@fossbilling.org');
    cy.get('#inputPassword').type('fossbilling.org');

    cy.get('.btn').click();
    cy.get('.toast-body').should('have.text', 'Check your login details (403)');
  });

  it('successfully logs in', function () {
    cy.staffLogin()
  })

})