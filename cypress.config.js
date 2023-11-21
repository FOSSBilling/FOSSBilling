const { defineConfig } = require("cypress");

module.exports = defineConfig({
  projectId: "zap1kb",
  e2e: {
    baseUrl: 'http://127.0.0.1:8000',
    specPattern: 'tests/cypress/e2e/**/*.cy.{js,jsx,ts,tsx}',
    supportFile: 'tests/cypress/support/e2e.{js,jsx,ts,tsx}',
    experimentalStudio: true,
    experimentalRunAllSpecs: true,
    credentials: {
      staff: {
        email: "admin@fossbilling.org",
        password: "Admin123+"
      },
      client: {
        email: "client@fossbilling.org",
        password: "Client123+"
      }
    },
    downloadsFolder: 'tests/cypress/downloads',
    fixturesFolder: 'tests/cypress/fixtures',
    screenshotsFolder: 'tests/cypress/screenshots',
    videosFolder: 'tests/cypress/videos',
  },
});
