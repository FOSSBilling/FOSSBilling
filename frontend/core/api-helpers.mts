type ApiParams = FormData | Record<string, unknown> | string | null | undefined;
type RequestBody = FormData | string | null;

interface ParsedResponse {
  payload: unknown;
  rawText: string;
}

interface ApiErrorPayload {
  error?: {
    code?: string;
    message?: string;
  };
  result?: unknown;
}

function isApiResponsePayload(payload: unknown): payload is ApiErrorPayload | null {
  return payload === null || (typeof payload === 'object' && !Array.isArray(payload));
}

interface MinimalResponse {
  ok?: boolean;
  status?: number;
  statusText?: string;
  text?: () => Promise<string>;
}

interface ApiErrorLike {
  code?: string;
  message?: string;
  name?: string;
}

interface ApiTimeoutOptions {
  timeoutMs?: number;
  timeoutMessage?: string | null;
}

function injectCSRFToken<T extends ApiParams>(params: T, token: string): T {
  if (params instanceof FormData) {
    if (!params.has('CSRFToken')) {
      params.append('CSRFToken', token);
    }
  } else if (params && typeof params === 'object') {
    if (!params.CSRFToken) {
      params.CSRFToken = token;
    }
  }
  return params;
}

function buildRequestBody(method: string, params: ApiParams, url: URL): { body: RequestBody; isFormData: boolean } {
  const methodLower = method.toLowerCase();
  const isFormData = params instanceof FormData;
  let body: RequestBody = null;

  if (methodLower === 'get') {
    if (isFormData) {
      for (const [key, value] of params.entries()) {
        if (key !== 'CSRFToken') {
          url.searchParams.append(key, String(value));
        }
      }
    } else if (params && typeof params === 'object') {
      Object.keys(params)
        .filter((key) => key !== 'CSRFToken')
        .forEach((key) => url.searchParams.append(key, String(params[key])));
    } else if (params) {
      url.search = String(params);
      url.searchParams.delete('CSRFToken');
    }
  } else if (['post', 'put', 'patch', 'delete'].includes(methodLower)) {
    if (isFormData || typeof params === 'string') {
      body = params as RequestBody;
    } else {
      body = JSON.stringify(params);
    }
  }

  return { body, isFormData };
}

function buildHeaders(
  { url, body, isFormData, csrfToken, origin }: {
    url: URL;
    body: RequestBody;
    isFormData: boolean;
    csrfToken?: string | null;
    origin: string;
  },
): Record<string, string> {
  const headers: Record<string, string> = {
    'Accept': 'application/json',
  };
  if (url.origin === origin) {
    headers['X-Requested-With'] = 'XMLHttpRequest';
    headers['X-CSRF-Token'] = csrfToken || '';
  }
  if (body && !isFormData) {
    headers['Content-Type'] = 'application/json';
  }
  return headers;
}

async function parseResponseBody(response: MinimalResponse): Promise<ParsedResponse> {
  if (response.status === 204) {
    return { payload: null, rawText: '' };
  }

  const text = await response.text?.() ?? '';
  if (!text) {
    return { payload: null, rawText: '' };
  }

  try {
    return { payload: JSON.parse(text), rawText: text };
  } catch {
    return { payload: null, rawText: text };
  }
}

function validateHttpResponse(response: MinimalResponse, parsed: ParsedResponse): ApiErrorPayload | null {
  if (!response.ok) {
    const payload = isApiResponsePayload(parsed.payload) ? parsed.payload : null;
    const error = new Error(payload?.error?.message || `HTTP error ${response.status}: ${response.statusText}`);
    error.code = payload?.error?.code || `http_${response.status}`;
    error.status = response.status;
    error.rawBody = parsed.rawText;
    throw error;
  }

  if (parsed.rawText && parsed.payload === null) {
    throw new Error('Invalid or non-JSON response from server');
  }

  return isApiResponsePayload(parsed.payload) ? parsed.payload : null;
}

function interpretResponse(payload: ApiErrorPayload | null) {
  if (!payload) {
    return null;
  }

  if (payload.error) {
    const error = new Error(payload.error.message || 'Unknown API error');
    error.code = payload.error.code;
    throw error;
  }

  return payload.result;
}

function normalizeApiError(error: ApiErrorLike, { timeoutMs = 30000, timeoutMessage = null }: ApiTimeoutOptions = {}) {
  if (error.name === 'AbortError') {
    return {
      message: timeoutMessage || `Request timed out after ${timeoutMs / 1000} seconds`,
      code: 'timeout_error',
    };
  }

  if (error.name === 'TypeError' && /NetworkError|Failed to fetch|Load failed/i.test(error.message)) {
    return {
      message: 'Network connection error',
      code: 'network_error',
    };
  }

  return {
    message: error.message || 'Unknown error occurred',
    code: error.code || 'unknown_error',
  };
}

export {
  injectCSRFToken,
  buildRequestBody,
  buildHeaders,
  parseResponseBody,
  validateHttpResponse,
  interpretResponse,
  normalizeApiError,
};
