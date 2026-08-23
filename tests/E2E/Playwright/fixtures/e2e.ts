import {
  test as base,
  expect,
  type Browser,
  type BrowserContext,
  type Page,
} from '@playwright/test';
import { createTestClient, type TestClient } from '../helpers/client-factory';
import { submitForm } from '../helpers/forms';

type SessionState = Awaited<ReturnType<BrowserContext['storageState']>>;

interface Credentials {
  email: string;
  password: string;
}

export function adminCredentials(): Credentials {
  const email = process.env.ADMIN_EMAIL;
  const password = process.env.ADMIN_PASSWORD;

  if (!email || !password) {
    throw new Error('The ADMIN_EMAIL and ADMIN_PASSWORD environment variables must be set to run the admin tests.');
  }

  return { email, password };
}

async function csrfToken(context: BrowserContext): Promise<string> {
  const cookie = (await context.cookies()).find(({ name }) => name === 'fossbilling_csrf');

  if (!cookie) {
    throw new Error('The fossbilling_csrf cookie was not found in the browser context.');
  }

  return cookie.value;
}

export async function authenticatedGet(context: BrowserContext, url: string, params: Record<string, unknown> = {}) {
  return context.request.get(url, {
    params: {
      ...params,
      CSRFToken: await csrfToken(context),
    },
  });
}

/**
 * Performs a login through the real login form and waits for the backing API call.
 * Only the response status is asserted here; the response body cannot be read
 * reliably once the login redirect navigates away. Session validity is verified
 * separately by the caller.
 */
async function loginThroughForm(
  page: Page,
  options: {
    visitPath: string;
    apiPath: string;
    credentials: Credentials;
    expectedPath: RegExp;
  },
): Promise<void> {
  const { visitPath, apiPath, credentials, expectedPath } = options;

  await page.goto(visitPath);

  const form = page.locator(`form[action*="${apiPath}"]`);
  await form.locator('input[name="email"]').fill(credentials.email);
  await form.locator('input[name="password"]').fill(credentials.password);

  const loginResponse = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.includes(apiPath),
  );

  await submitForm(form);

  expect((await loginResponse).status(), `${apiPath} login response status`).toBe(200);

  await expect(page).toHaveURL(
    (url) => expectedPath.test(new URL(url).pathname),
    { timeout: 10_000 },
  );
}

/**
 * Caches login storage state promises per worker process. A failed login evicts its
 * entry so the next test retries instead of reusing the failed promise.
 */
const storageStates = new Map<string, Promise<SessionState>>();

function cachedStorageState(key: string, build: () => Promise<SessionState>): Promise<SessionState> {
  if (!storageStates.has(key)) {
    const creation = build().catch((error) => {
      storageStates.delete(key);
      throw error;
    });
    storageStates.set(key, creation);
  }

  return storageStates.get(key)!;
}

function getAdminStorageState(browser: Browser): Promise<SessionState> {
  const { email, password } = adminCredentials();

  return cachedStorageState(`admin:${email}`, async () => {
    const context = await browser.newContext();

    try {
      const page = await context.newPage();
      await loginThroughForm(page, {
        visitPath: '/admin/staff/login',
        apiPath: '/api/guest/staff/login',
        credentials: { email, password },
        expectedPath: /^\/admin\/?$/,
      });

      const probe = await context.request.get('/admin', { maxRedirects: 0 });
      expect(probe.status(), 'admin session validation').toBe(200);

      return await context.storageState();
    } finally {
      await context.close();
    }
  });
}

function getClientStorageState(browser: Browser, client: Credentials): Promise<SessionState> {
  return cachedStorageState(`client:${client.email}:${client.password}`, async () => {
    const context = await browser.newContext();

    try {
      const page = await context.newPage();
      await loginThroughForm(page, {
        visitPath: '/login',
        apiPath: '/api/guest/client/login',
        credentials: client,
        expectedPath: /^\/$/,
      });

      const probe = await authenticatedGet(context, '/api/client/profile/get');
      expect(probe.status(), 'client session validation').toBe(200);
      expect((await probe.json()).result?.email, 'client session validation').toBe(client.email);

      return await context.storageState();
    } finally {
      await context.close();
    }
  });
}

interface Fixtures {
  incognitoPage: Page;
  adminContext: BrowserContext;
  adminPage: Page;
  testClient: TestClient;
  clientContext: BrowserContext;
  clientPage: Page;
}

export const test = base.extend<Fixtures>({
  incognitoPage: async ({ browser }, use) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await use(page);

    await context.close();
  },

  adminContext: async ({ browser }, use) => {
    const storageState = await getAdminStorageState(browser);
    const context = await browser.newContext({ storageState });

    await use(context);

    await context.close();
  },

  adminPage: async ({ adminContext }, use) => {
    const page = await adminContext.newPage();
    await page.goto('/admin');
    await expect(page).toHaveURL((url) => /^\/admin\/?$/.test(new URL(url).pathname), { timeout: 10_000 });

    await use(page);

    await page.close();
  },

  testClient: async ({ request }, use) => {
    await use(await createTestClient(request));
  },

  clientContext: async ({ browser, testClient }, use) => {
    const storageState = await getClientStorageState(browser, testClient);
    const context = await browser.newContext({ storageState });

    await use(context);

    await context.close();
  },

  clientPage: async ({ clientContext, testClient }, use) => {
    const page = await clientContext.newPage();
    await page.goto('/');
    await expect(page.locator('body')).toContainText(testClient.email);

    await use(page);

    await page.close();
  },
});

/**
 * Opens an independent logged-in client session for a client that is not the shared
 * fixture client (e.g. after its password has been changed).
 */
export async function openClientSession(browser: Browser, client: Credentials): Promise<BrowserContext> {
  const storageState = await getClientStorageState(browser, client);
  const context = await browser.newContext({ storageState });
  const page = await context.newPage();

  await page.goto('/');
  await expect(page.locator('body')).toContainText(client.email);

  return context;
}

export { expect };
