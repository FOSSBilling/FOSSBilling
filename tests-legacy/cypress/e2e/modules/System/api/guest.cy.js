describe('/api/guest/system', () => {

    it('returns the version', function() {
        cy.request('/api/guest/system/version').then((response) => {
            expect(response.status).to.eq(200)
            expect(response.body.result).to.be.a('string')
            expect(response.body.error).to.be.null

            cy.log(`This instance of FOSSBilling is running on the version ${response.body.result}.`)
        })
    })

    it('returns the company information', function() {
        cy.request('/api/guest/system/company').then((response) => {
            expect(response.status).to.eq(200)
            expect(response.body.result).to.be.a('object')
            expect(response.body.result.name).to.be.a('string')
            expect(response.body.error).to.be.null

            cy.log(`This instance of FOSSBilling is named "${response.body.result.name}".`)
        })
    })

})