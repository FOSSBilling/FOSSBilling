const { defineConfig } = require("cypress");

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://localhost',
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
