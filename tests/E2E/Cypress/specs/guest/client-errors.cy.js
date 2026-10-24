describe('client error notifications', () => {
  beforeEach(() => {
    cy.visit('/login');
  });

  ['exception', 'rejection'].forEach((kind) => {
    it(`does not notify for an unrelated ${kind}`, () => {
      cy.on('uncaught:exception', (error) => {
        if (error.message.includes('Unrelated script failure')) return false;
      });
      cy.window().then((win) => {
        const notify = cy.spy(win.FOSSBilling, 'message');
        return new Cypress.Promise((resolve) => {
          const eventName = kind === 'exception' ? 'error' : 'unhandledrejection';
          win.addEventListener(eventName, (event) => {
            const error = kind === 'exception' ? event.error : event.reason;
            // Wait until all error listeners have run, without waiting for toast expiry.
            win.setTimeout(() => resolve({ notify, error }), 0);
          }, { once: true });
          if (kind === 'exception') {
            win.setTimeout(() => { throw new win.Error('Unrelated script failure'); }, 0);
          } else {
            win.Promise.reject(new win.Error('Unrelated script failure'));
          }
        });
      }).then(({ notify, error }) => {
        expect(error.message).to.eq('Unrelated script failure');
        expect(notify).not.to.have.been.called;
      });
    });
  });

  it('shows API form failures and restores the submit button', () => {
    cy.intercept('POST', '**/api/guest/client/login*', {
      body: { error: { message: 'Invalid login details', code: 422 } },
    }).as('login');
    const form = 'form[action*="/api/guest/client/login"]';
    cy.get(form).submit();
    cy.wait('@login');
    cy.get('.toast-body').should('have.text', 'Invalid login details (422)');
    cy.get(`${form} button[type="submit"]`).should('not.be.disabled');
  });

  it('continues to display application notifications', () => {
    cy.window().then((win) => win.FOSSBilling.message('Application notice'));
    cy.get('.toast-body').should('have.text', 'Application notice');
  });
});
