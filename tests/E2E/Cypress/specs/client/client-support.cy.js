describe('client support tickets', () => {
  let client;

  before(() => {
    cy.testClient().then((testClient) => {
      client = testClient;
    });
  });

  it('opens, replies to, and closes a support ticket', () => {
    const suffix = `${Date.now()}-${Cypress._.random(100000, 999999)}`;
    const subject = `Cypress support lifecycle ${suffix}`;
    const initialMessage = `Initial support request from Cypress ${suffix}.`;
    const replyMessage = `Follow-up support reply from Cypress ${suffix}.`;
    let ticketId;

    cy.loginAsClient(client);
    cy.visit('/support');
    cy.contains('body', 'Support Tickets').should('be.visible');

    cy.intercept('POST', '**/api/client/support/ticket_create*').as('ticketCreate');
    cy.openBootstrapModal('[data-bs-target="#open-ticket-modal"]', '#open-ticket-modal');
    cy.get('#open-ticket-modal select[name="support_helpdesk_id"]').invoke('val').should('not.be.empty');
    cy.setEditorContent('#open-ticket-modal textarea[name="content"]', initialMessage);
    cy.typeAndVerify('#open-ticket-modal input[name="subject"]', subject);
    cy.get('#ticket-submit').submit();

    cy.wait('@ticketCreate').then(({ request, response }) => {
      const requestBody = typeof request.body === 'string' ? JSON.parse(request.body) : request.body;

      expect(requestBody.subject).to.eq(subject);
      expect(response.statusCode).to.eq(200);
      expect(response.body.error).to.eq(null);
      expect(response.body.result, 'ticket id').to.exist;
      ticketId = response.body.result;
    });

    cy.then(() => {
      cy.location('pathname', { timeout: 10000 }).should('eq', `/support/ticket/${ticketId}`);
      cy.contains('body', subject).should('be.visible');
      cy.contains('article.markdown-body', initialMessage).should('be.visible');
    });

    cy.intercept('POST', '**/api/client/support/ticket_reply*').as('ticketReply');
    cy.setEditorContent('#ticket-reply-text', replyMessage);
    cy.get('#ticket-reply-form').submit();

    cy.wait('@ticketReply').then(({ response }) => {
      expect(response.statusCode).to.eq(200);
      expect(response.body.error).to.eq(null);
      expect(response.body.result).to.eq(true);
    });
    cy.contains('article.markdown-body', replyMessage, { timeout: 10000 }).should('be.visible');

    cy.intercept('POST', '**/api/client/support/ticket_close*').as('ticketClose');
    cy.contains('button', 'Close Ticket').click();

    cy.wait('@ticketClose').then(({ response }) => {
      expect(response.statusCode).to.eq(200);
      expect(response.body.error).to.eq(null);
      expect(response.body.result).to.eq(true);
    });
    cy.contains('button', 'Close Ticket').should('not.exist');

    cy.then(() => {
      cy.getCookie('fossbilling_csrf').should('exist').then((cookie) => {
        cy.request({
          url: '/api/client/support/ticket_get',
          qs: {
            CSRFToken: cookie.value,
            id: ticketId,
          },
        }).then((response) => {
          expect(response.status).to.eq(200);
          expect(response.body.error).to.eq(null);
          expect(response.body.result.status).to.eq('closed');
          expect(response.body.result.subject).to.eq(subject);
          expect(response.body.result.messages).to.have.length(2);
        });
      });
    });
  });
});
