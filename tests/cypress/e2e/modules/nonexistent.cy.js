describe('Test the API route for a non-existent module', () => {

    it('the guest API returns an error', function() {
        cy.request('/api/guest/nonexistent/testing').then((response) => {
            // expect(response.status).to.eq(400) // FOSSBilling returns 200
            expect(response.body.result).to.be.null
            expect(response.body.error.message).eq('FOSSBilling module nonexistent is not installed/activated')
        })
    })

})