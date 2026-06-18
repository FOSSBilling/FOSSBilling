module.exports = {
  projectId: 'zap1kb',
  e2e: {
    baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost',
    specPattern: 'tests/E2E/Cypress/specs/**/*.cy.js',
    supportFile: 'tests/E2E/Cypress/support/e2e.js',
  },
  defaultCommandTimeout: 10000,
  viewportWidth: 1280,
  viewportHeight: 720,
  env: {
    adminEmail: process.env.CYPRESS_ADMIN_EMAIL || '',
    adminPassword: process.env.CYPRESS_ADMIN_PASSWORD || '',
  },
  video: false,
  screenshotOnRunFailure: true,
  screenshotsFolder: 'tests/E2E/Cypress/artifacts/screenshots',
  videosFolder: 'tests/E2E/Cypress/artifacts/videos',
  downloadsFolder: 'tests/E2E/Cypress/artifacts/downloads',
};
