describe('Admin:Index', () => {

  it('redirects unauthorized users to the login page', function() {
    cy.visit('/admin/index');

    cy.location('pathname').should('eq', '/admin/staff/login')
  });

  it('successfully loads when logged in', function () {
    cy.staffLogin()

    cy.visit('/admin/index');

    cy.location('pathname').should('eq', '/admin/index');
    cy.get('.breadcrumb > .active').should('have.text', 'Dashboard');
  })

})