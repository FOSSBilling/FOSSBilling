const { defineConfig } = require("cypress");

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://localhost',
    experimentalStudio: true,
    credentials: {
      staff: {
        email: "bb@bb.com",
        password: "Bb123123+"
      },
      client: {
        email: "bb@bb.com",
        password: "Bb123123+"
      }
    }
  },
});
