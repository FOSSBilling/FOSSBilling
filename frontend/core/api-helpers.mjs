function injectCSRFToken(params, token) {
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

function buildRequestBody(method, params, url) {
  const methodLower = method.toLowerCase();
  const isFormData = params instanceof FormData;
  let body = null;

  if (methodLower === 'get') {
    if (isFormData) {
      for (const [key, value] of params.entries()) {
        if (key !== 'CSRFToken') {
          url.searchParams.append(key, value);
        }
      }
    } else if (params && typeof params === 'object') {
      Object.keys(params)
        .filter((key) => key !== 'CSRFToken')
        .forEach((key) => url.searchParams.append(key, params[key]));
    } else if (params) {
      url.search = params;
      url.searchParams.delete('CSRFToken');
    }
  } else if (['post', 'put', 'patch', 'delete'].includes(methodLower)) {
    if (isFormData || typeof params === 'string') {
      body = params;
    } else {
      body = JSON.stringify(params);
    }
  }

  return { body, isFormData };
}

function buildHeaders({ url, body, isFormData, csrfToken, origin }) {
  const headers = {
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

async function parseResponseBody(response) {
  if (response.status === 204) {
    return { payload: null, rawText: '' };
  }

  const text = await response.text();
  if (!text) {
    return { payload: null, rawText: '' };
  }

  try {
    return { payload: JSON.parse(text), rawText: text };
  } catch {
    return { payload: null, rawText: text };
  }
}

function validateHttpResponse(response, parsed) {
  if (!response.ok) {
    const error = new Error(parsed.payload?.error?.message || `HTTP error ${response.status}: ${response.statusText}`);
    error.code = parsed.payload?.error?.code || `http_${response.status}`;
    error.status = response.status;
    error.rawBody = parsed.rawText;
    throw error;
  }

  if (parsed.rawText && parsed.payload === null) {
    throw new Error('Invalid or non-JSON response from server');
  }

  return parsed.payload;
}

function interpretResponse(payload) {
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

function normalizeApiError(error, { timeoutMs, timeoutMessage }) {
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
