import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost';

export default defineConfig({
  testDir: './tests/E2E/Playwright/specs',
  timeout: 60_000,
  expect: {
    timeout: 10_000,
  },
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI
    ? [
        ['github'],
        ['html', { outputFolder: 'tests/E2E/Playwright/artifacts/report', open: 'never' }],
      ]
    : [
        ['list'],
        ['html', { outputFolder: 'tests/E2E/Playwright/artifacts/report', open: 'on-failure' }],
      ],
  outputDir: 'tests/E2E/Playwright/artifacts/results',
  use: {
    baseURL,
    actionTimeout: 10_000,
    navigationTimeout: 30_000,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
