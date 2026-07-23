Cypress.Commands.add('loginAsAdmin', () => {
  const email = Cypress.env('adminEmail');
  const password = Cypress.env('adminPassword');

  expect(email, 'admin email').to.be.a('string').and.not.be.empty;
  expect(password, 'admin password').to.be.a('string').and.not.be.empty;

  cy.session(['admin', email], () => {
    cy.visit('/admin/staff/login');
    cy.intercept('POST', '**/api/guest/staff/login*').as('staffLogin');

    cy.get('form[action*="/api/guest/staff/login"]').within(() => {
      cy.get('input[name="email"]').clear().type(email);
      cy.get('input[name="password"]').clear().type(password, { log: false });
      cy.root().submit();
    });

    cy.wait('@staffLogin').its('response.statusCode').should('eq', 200);
    cy.location('pathname', { timeout: 10000 }).should('match', /^\/admin\/?$/);
  }, {
    cacheAcrossSpecs: true,
    validate() {
      cy.request({ url: '/admin', followRedirect: false }).its('status').should('eq', 200);
    },
  });

  cy.visit('/admin');
  cy.location('pathname', { timeout: 10000 }).should('match', /^\/admin\/?$/);
});

const defaultClientPassword = 'CypressClient1!';

const uniqueSuffix = () => `${Date.now()}-${Cypress._.random(100000, 999999)}`;

Cypress.Commands.add('testClient', (overrides = {}) => {
  const suffix = uniqueSuffix();
  const client = {
    first_name: overrides.first_name || 'Cypress',
    last_name: overrides.last_name || 'Client',
    email: overrides.email || `cypress-client-${suffix}@example.com`,
    password: overrides.password || defaultClientPassword,
  };

  return cy.request({
    method: 'POST',
    url: '/api/guest/client/create',
    body: {
      ...client,
      password_confirm: client.password,
    },
  }).then((response) => {
    expect(response.status).to.eq(200);
    expect(response.body.error).to.eq(null);
    expect(response.body.result, 'client id').to.exist;

    const createdClient = {
      ...client,
      id: response.body.result,
    };

    return cy.clearCookies()
      .then(() => cy.clearLocalStorage())
      .then(() => createdClient);
  });
});

Cypress.Commands.add('fillClientSignupForm', (client) => {
  const values = {
    first_name: client.first_name || 'Cypress',
    last_name: client.last_name || 'Client',
    email: client.email,
    company: client.company || 'FOSSBilling Test Company',
    birthday: client.birthday || '1990-01-01',
    address_1: client.address_1 || '1 Cypress Street',
    address_2: client.address_2 || 'Suite 2',
    city: client.city || 'Test City',
    state: client.state || 'Test State',
    postcode: client.postcode || '12345',
    phone_cc: client.phone_cc || '1',
    phone: client.phone || '5551234567',
    password: client.password,
    password_confirm: client.password,
  };

  cy.get('form[action*="/api/guest/client/create"]').then(($form) => {
    Object.entries(values).forEach(([name, value]) => {
      const $field = $form.find(`[name="${name}"]`).first();

      if ($field.length && ($field.attr('type') || '').toLowerCase() !== 'hidden') {
        cy.wrap($field).clear().type(value, name.includes('password') ? { log: false } : {});
      }
    });

    $form.find('input[required], textarea[required]').each((index, field) => {
      const $field = Cypress.$(field);
      const type = ($field.attr('type') || '').toLowerCase();
      const name = $field.attr('name');

      if (!name || Object.prototype.hasOwnProperty.call(values, name) || ['checkbox', 'radio', 'hidden', 'password'].includes(type)) {
        return;
      }

      if (!$field.val()) {
        cy.wrap(field).clear().type('Cypress test value');
      }
    });

    const $gender = $form.find('select[name="gender"]').first();
    if ($gender.length) {
      cy.wrap($gender).select('other');
    }

    $form.find('select[required]').each((index, select) => {
      const $select = Cypress.$(select);
      if ($select.val()) {
        return;
      }

      const firstValue = $select.find('option[value!=""]').first().val();
      if (firstValue) {
        cy.wrap(select).select(firstValue);
      }
    });

    const $country = $form.find('select[name="country"]').first();
    if ($country.length && !$country.val()) {
      const firstCountry = $country.find('option[value!=""]').first().val();
      if (firstCountry) {
        cy.wrap($country).select(firstCountry);
      }
    }

    $form.find('input[type="checkbox"][required]').each((index, checkbox) => {
      cy.wrap(checkbox).check({ force: true });
    });
  });
});

Cypress.Commands.add('loginAsClient', (client) => {
  expect(client.email, 'client email').to.be.a('string').and.not.be.empty;
  expect(client.password, 'client password').to.be.a('string').and.not.be.empty;

  cy.session(['client', client.email, client.password], () => {
    cy.visit('/login');
    cy.intercept('POST', '**/api/guest/client/login*').as('clientLogin');

    cy.get('form[action*="/api/guest/client/login"]').within(() => {
      cy.get('input[name="email"]').clear().type(client.email);
      cy.get('input[name="password"]').clear().type(client.password, { log: false });
      cy.root().submit();
    });

    cy.wait('@clientLogin').then(({ response }) => {
      expect(response.statusCode).to.eq(200);
      expect(response.body.error).to.eq(null);
    });
    cy.location('pathname', { timeout: 10000 }).should('eq', '/');
  }, {
    cacheAcrossSpecs: true,
    validate() {
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
    },
  });

  cy.visit('/');
  cy.contains('body', client.email).should('be.visible');
});

Cypress.Commands.add('setEditorContent', (selector, content) => {
  cy.get(selector)
    .should(($field) => {
      expect($field[0].editor, `${selector} editor`).to.exist;
    })
    .then(($field) => {
      const field = $field[0];
      field.editor.setData(content);
      field.value = content;
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
    });
});

Cypress.Commands.add('openBootstrapModal', (triggerSelector, modalSelector) => {
  let modalShown;

  cy.window().then((win) => {
    const modal = win.document.querySelector(modalSelector);

    expect(modal, `${modalSelector} modal`).to.exist;
    modalShown = modal.classList.contains('show')
      ? Cypress.Promise.resolve()
      : new Cypress.Promise((resolve) => {
        modal.addEventListener('shown.bs.modal', resolve, { once: true });
      });
  });

  cy.get(triggerSelector).should('be.visible').click();
  cy.then(() => modalShown);
  cy.get(modalSelector).should('be.visible').and('have.class', 'show');
});

Cypress.Commands.add('typeAndVerify', (selector, value, options = {}) => {
  cy.get(selector)
    .should('be.visible')
    .and('not.be.disabled')
    .clear()
    .type(value, { delay: 0, ...options })
    .then(($input) => {
      const el = $input[0];
      const rawMaxLength = el.getAttribute('maxlength');
      const maxLength = rawMaxLength === null ? -1 : Number.parseInt(rawMaxLength, 10);
      const expectedValue = Number.isInteger(maxLength) && maxLength >= 0
        ? String(value).slice(0, maxLength)
        : String(value);

      cy.wrap($input).should('have.value', expectedValue);
    });
});
