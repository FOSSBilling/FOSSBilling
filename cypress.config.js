module.exports = {
  projectId: process.env.CYPRESS_PROJECT_ID || 'zap1kb',
  e2e: {
    baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost',
    specPattern: 'tests/e2e/cypress/e2e/**/*.cy.js',
    supportFile: 'tests/e2e/cypress/support/e2e.js',
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
  screenshotsFolder: 'tests/e2e/cypress/screenshots',
  videosFolder: 'tests/e2e/cypress/videos',
  downloadsFolder: 'tests/e2e/cypress/downloads',
};
