export class ApiError extends Error {
  constructor(public status: number, message: string, public errors: Record<string, string[]> = {}) { super(message); }
}

function xsrfCookie(): string | undefined {
  const value = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.slice(11);
  return value ? decodeURIComponent(value) : undefined;
}

export async function api<T>(path: string, method = 'GET', body?: unknown, signal?: AbortSignal): Promise<T> {
  if (method !== 'GET') {
    const csrf = await fetch('/sanctum/csrf-cookie', { credentials: 'include', headers: { Accept: 'application/json' }, signal });
    if (!csrf.ok) throw new ApiError(csrf.status, 'Could not initialize your session. Please reload and try again.');
  }
  const token = xsrfCookie();
  const response = await fetch(`/api${path}`, {
    method, credentials: 'include', signal,
    headers: { Accept: 'application/json', ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}), ...(token ? { 'X-XSRF-TOKEN': token } : {}) },
    ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
  });
  if (response.status === 204) return undefined as T;
  const result = await response.json().catch(() => ({ message: 'The server returned an unexpected response. Please try again.' })) as { message?: string; errors?: Record<string, string[]> };
  if (!response.ok) {
    if (response.status === 401) window.dispatchEvent(new Event('session-expired'));
    const message = response.status === 419 ? 'Your security session expired. Please retry; if this continues, sign in again.'
      : response.status === 401 ? 'Your session expired. Please sign in again.'
      : response.status === 429 ? 'Too many attempts. Please wait a minute and try again.'
      : result.message ?? 'Something went wrong. Please try again.';
    throw new ApiError(response.status, message, result.errors);
  }
  return result as T;
}

// Formatting only: never convert API money to floating point.
export function rm(value: string): string {
  const negative = value.startsWith('-');
  const [whole, fraction = '00'] = value.replace(/^-/, '').split('.');
  return `${negative ? '−' : ''}RM${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${fraction.padEnd(2, '0')}`;
}

export function today(): string {
  const date = new Date();
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
