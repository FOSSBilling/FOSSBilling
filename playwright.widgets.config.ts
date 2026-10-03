import { defineConfig } from '@playwright/test';
import base from './playwright.config';

export default defineConfig(base, {
  testMatch: '**/admin/tabler-widgets.spec.ts',
  fullyParallel: true,
  workers: 2,
});
