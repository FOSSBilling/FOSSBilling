describe('The Support Page', () => {

  it('redirects to the login page when unauthorized', function() {
    cy.visit('/support');

    cy.location('pathname').should('eq', '/login');
  });

  it('creates a new ticket and closes it', function () {
    cy.clientLogin()

    cy.visit('/support');
    cy.location('pathname').should('eq', '/support');

    // The "submit new ticket" button should now be visible
    cy.get('#new-ticket-button').should('have.text', 'Submit new ticket');
    cy.get('#new-ticket-button').click();

    // The modal should now be visible
    cy.get('input[name="subject"]').type('Testing')
    cy.get('textarea[name="content"]').type('This is a test message.')

    cy.get('.modal-footer > .btn').should('be.visible');
    cy.get('.modal-footer > .btn').click();

    // Make sure we are redirected to the ticket and the subject is correct
    cy.get(':nth-child(2) > .data-block > .data-container > :nth-child(1) > h2').should('have.text', 'Testing');
    
    // Close the ticket
    cy.get('#ticket-close').should('be.visible');
    cy.get('#ticket-close').click();
    
    // Make sure we are redirected to the support index
    cy.location('pathname').should('eq', '/support');
  })

})