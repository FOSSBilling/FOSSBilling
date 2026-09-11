/**
 * Extracts a human-readable message from rejection reasons and caught errors.
 *
 * Shared by the themes' global error handlers. Falls back to the provided
 * default when the error carries neither a message nor a code.
 */

export function errorMessage(error: unknown, fallback = 'An unexpected error occurred'): string {
  if (typeof error === 'string') {
    return error;
  }

  if (error && typeof error === 'object') {
    const { message, code } = error as { message?: unknown; code?: unknown };
    if (typeof message === 'string' && message) {
      return message;
    }
    if (typeof code === 'string' && code) {
      return code;
    }
    if (typeof code === 'number') {
      return String(code);
    }
  }

  return fallback;
}
