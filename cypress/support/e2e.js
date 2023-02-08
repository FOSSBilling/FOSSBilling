Cypress.Commands.add('staffLogin', (username, password) => {
    // If the username and the password are not provided, use the credentials from the config file
    username = username || Cypress.config("credentials").staff.email
    password = password || Cypress.config("credentials").staff.password

    cy.session(
      username,
      () => {
        cy.visit('/admin/staff/login');

        cy.get('#inputEmail').type(username);
        cy.get('#inputPassword').type(password);

        cy.get('.btn').click();

        cy.url().should('eq', `${Cypress.config().baseUrl}/admin/index`)
      },
      {
        validate: () => {
          cy.getCookie('PHPSESSID').should('exist')
        },
      }
    )
  })

Cypress.Commands.add('clientLogin', (username, password) => {
    // If the username and the password are not provided, use the credentials from the config file
    username = username || Cypress.config("credentials").client.email
    password = password || Cypress.config("credentials").client.password

    cy.session(
      username,
      () => {
        cy.visit('/login');

        cy.get('#icon').type(username);
        cy.get('#password').type(password);

        cy.get('.btn').click();

        cy.url().should('eq', `${Cypress.config().baseUrl}/`)
      },
      {
        validate: () => {
          cy.getCookie('PHPSESSID').should('exist')
        },
      }
    )
  })