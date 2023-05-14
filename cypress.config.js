const { defineConfig } = require("cypress");

module.exports = defineConfig({
  projectId: "zap1kb",
  e2e: {
    baseUrl: 'http://127.0.0.1:8000',
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
    }
  },
});
