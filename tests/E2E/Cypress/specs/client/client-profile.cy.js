describe('client profile', () => {
  let client;

  beforeEach(() => {
    cy.testClient().then((testClient) => {
      client = testClient;
      cy.loginAsClient(client);
    });
  });

  it('updates profile details', () => {
    const updatedProfile = {
      firstName: 'Updated',
      lastName: 'Profile',
      company: 'Updated Cypress Company',
      phoneCountryCode: '44',
      phone: '7700900123',
      address: '42 Updated Road',
      city: 'Updated City',
      state: 'Updated State',
      postcode: 'UP123',
    };

    cy.visit('/client/profile');
    cy.contains('body', 'Update Details').should('be.visible');
    cy.intercept('POST', '**/api/client/profile/update*').as('profileUpdate');

    cy.get('form#profile-update').within(() => {
      cy.get('input[name="first_name"]').clear().type(updatedProfile.firstName);
      cy.get('input[name="last_name"]').clear().type(updatedProfile.lastName);
      cy.get('input[name="company"]').clear().type(updatedProfile.company);
      cy.get('input[name="phone_cc"]').clear().type(updatedProfile.phoneCountryCode);
      cy.get('input[name="phone"]').clear().type(updatedProfile.phone);
      cy.get('input[name="address_1"]').clear().type(updatedProfile.address);
      cy.get('input[name="city"]').clear().type(updatedProfile.city);
      cy.get('input[name="state"]').clear().type(updatedProfile.state);
      cy.get('input[name="postcode"]').clear().type(updatedProfile.postcode);
      cy.root().submit();
    });

    cy.wait('@profileUpdate').then(({ response }) => {
      expect(response.statusCode).to.eq(200);
      expect(response.body.error).to.eq(null);
      expect(response.body.result).to.eq(true);
    });

    cy.reload();
    cy.get('input[name="first_name"]').should('have.value', updatedProfile.firstName);
    cy.get('input[name="last_name"]').should('have.value', updatedProfile.lastName);
    cy.get('input[name="company"]').should('have.value', updatedProfile.company);
    cy.get('input[name="phone_cc"]').should('have.value', updatedProfile.phoneCountryCode);
    cy.get('input[name="phone"]').should('have.value', updatedProfile.phone);
    cy.get('input[name="address_1"]').should('have.value', updatedProfile.address);
    cy.get('input[name="city"]').should('have.value', updatedProfile.city);
    cy.get('input[name="state"]').should('have.value', updatedProfile.state);
    cy.get('input[name="postcode"]').should('have.value', updatedProfile.postcode);
  });

  it('changes the client password', () => {
    const oldPassword = client.password;
    const newPassword = 'CypressClient2!';

    cy.visit('/client/profile');
    cy.get('#pass-tab').click();
    cy.intercept('POST', '**/api/client/profile/change_password*').as('passwordChange');

    cy.get('#pass-tab-pane form').within(() => {
      cy.get('input[name="current_password"]').type(oldPassword, { log: false });
      cy.get('input[name="new_password"]').type(newPassword, { log: false });
      cy.get('input[name="confirm_password"]').type(newPassword, { log: false });
      cy.root().submit();
    });

    cy.wait('@passwordChange').then(({ response }) => {
      expect(response.statusCode).to.eq(200);
      expect(response.body.error).to.eq(null);
      expect(response.body.result).to.eq(true);
    });

    cy.request({
      method: 'POST',
      url: '/api/guest/client/login',
      body: {
        email: client.email,
        password: oldPassword,
      },
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.body.result).to.eq(null);
      expect(response.body.error.message).to.eq('Please check your login details.');
    });

    cy.clearCookies();
    cy.clearLocalStorage();
    cy.loginAsClient({ ...client, password: newPassword });
  });
});
