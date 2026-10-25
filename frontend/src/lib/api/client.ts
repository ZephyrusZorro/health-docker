import axios, { type AxiosInstance } from 'axios';

/**
 * HTTP client for the Laravel API.
 *
 * Auth model: Sanctum personal access tokens stored in localStorage.
 * (In production, prefer httpOnly cookies via `sanctum/csrf-cookie`; for
 * this MVP the token-in-storage approach is simpler and identical from
 * the API's perspective.)
 */
export const API_BASE = process.env.NEXT_PUBLIC_API_URL || '/api/v1';

const TOKEN_KEY = 'nishat_token';

export function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem(TOKEN_KEY);
}
export function setToken(token: string) {
  if (typeof window === 'undefined') return;
  localStorage.setItem(TOKEN_KEY, token);
}
export function clearToken() {
  if (typeof window === 'undefined') return;
  localStorage.removeItem(TOKEN_KEY);
}

export const api: AxiosInstance = axios.create({
  baseURL: API_BASE,
  headers: { Accept: 'application/json' },
  withCredentials: false,
});

api.interceptors.request.use((config) => {
  const token = getToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (res) => res,
  (err) => {
    // Auto-logout on 401 — let React Query's error flow redirect to /login.
    if (err.response?.status === 401 && typeof window !== 'undefined') {
      clearToken();
      // Redirect except when we're already at /login to avoid a loop.
      if (!window.location.pathname.startsWith('/login')) {
        window.location.href = '/login';
      }
    }
    return Promise.reject(err);
  },
);

/** Unwrap the "data" envelope consistently. */
export async function unwrap<T>(promise: Promise<{ data: { data: T } }>): Promise<T> {
  const r = await promise;
  return r.data.data;
}
