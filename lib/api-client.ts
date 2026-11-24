export interface ApiResponse<T = unknown> {
  success: boolean;
  message: string;
  data: T;
  errors?: Record<string, string[]>;
}

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";

const SANCTUM_COOKIE_URL =
  process.env.NEXT_PUBLIC_BACKEND_URL || "http://localhost:8000";

let csrfInitialized = false;

export async function initCsrf(): Promise<void> {
  if (csrfInitialized) return;
  try {
    await fetch(`${SANCTUM_COOKIE_URL}/sanctum/csrf-cookie`, {
      method: "GET",
      credentials: "include",
    });
    csrfInitialized = true;
  } catch {
    // If backend isn't yet started or in mock mode, gracefully proceed
  }
}

function getCsrfTokenFromCookie(): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}

export async function apiRequest<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<ApiResponse<T>> {
  await initCsrf();

  const csrfToken = getCsrfTokenFromCookie();
  const headers = new Headers(options.headers || {});

  headers.set("Accept", "application/json");

  if (!(options.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }

  if (csrfToken) {
    headers.set("X-XSRF-TOKEN", csrfToken);
  }

  const url = endpoint.startsWith("http")
    ? endpoint
    : `${API_BASE_URL}${endpoint.startsWith("/") ? endpoint : `/${endpoint}`}`;

  const response = await fetch(url, {
    ...options,
    headers,
    credentials: "include", // Uses HttpOnly cookies for session security
  });

  const data: ApiResponse<T> = await response.json().catch(() => ({
    success: false,
    message: response.statusText || "Network request failed",
    data: {} as T,
  }));

  if (!response.ok) {
    const error: Error & { errors?: Record<string, string[]>; status?: number } =
      new Error(data.message || "An error occurred");
    error.errors = data.errors;
    error.status = response.status;
    throw error;
  }

  return data;
}

export const apiClient = {
  get: <T>(endpoint: string, options?: RequestInit) =>
    apiRequest<T>(endpoint, { ...options, method: "GET" }),

  post: <T>(endpoint: string, body?: unknown, options?: RequestInit) =>
    apiRequest<T>(endpoint, {
      ...options,
      method: "POST",
      body: body instanceof FormData ? body : JSON.stringify(body),
    }),

  put: <T>(endpoint: string, body?: unknown, options?: RequestInit) =>
    apiRequest<T>(endpoint, {
      ...options,
      method: "PUT",
      body: JSON.stringify(body),
    }),

  delete: <T>(endpoint: string, options?: RequestInit) =>
    apiRequest<T>(endpoint, { ...options, method: "DELETE" }),
};
