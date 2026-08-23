import type { APIRequestContext } from '@playwright/test';

export interface TestClient {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  password: string;
}

export const defaultClientPassword = 'PlaywrightClient1!';

export function uniqueSuffix(): string {
  const random = Math.floor(100000 + Math.random() * 900000);
  return `${Date.now()}-${random}`;
}

export async function createTestClient(request: APIRequestContext): Promise<TestClient> {
  const suffix = uniqueSuffix();
  const client = {
    first_name: 'Playwright',
    last_name: 'Client',
    email: `playwright-client-${suffix}@example.com`,
    password: defaultClientPassword,
  };

  const response = await request.post('/api/guest/client/create', {
    data: {
      ...client,
      password_confirm: client.password,
    },
  });

  if (!response.ok()) {
    throw new Error(`Client creation failed with HTTP ${response.status()}: ${await response.text()}`);
  }

  const body = await response.json();

  if (body.error !== null || !body.result) {
    throw new Error(`Client creation returned an unexpected response: ${JSON.stringify(body)}`);
  }

  return {
    ...client,
    id: body.result,
  };
}
