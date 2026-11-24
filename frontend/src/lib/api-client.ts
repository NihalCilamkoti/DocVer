export interface ApiResponse<T = unknown> {
  success: boolean;
  message: string;
  data: T;
  errors?: Record<string, string[]>;
}

const RAW_API_BASE_URL =
  process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000/api/v1";

const RAW_SANCTUM_COOKIE_URL =
  process.env.NEXT_PUBLIC_BACKEND_URL || "http://127.0.0.1:8000";

function resolveUrl(url: string): string {
  if (typeof window !== "undefined") {
    // If frontend is accessed on localhost but env points to 127.0.0.1 (or vice versa),
    // align hostnames so browser cookies and CORS behave consistently without cross-origin domain drops.
    if (window.location.hostname === "localhost" && url.includes("127.0.0.1")) {
      return url.replace("127.0.0.1", "localhost");
    }
    if (window.location.hostname === "127.0.0.1" && url.includes("localhost")) {
      return url.replace("localhost", "127.0.0.1");
    }
  }
  return url;
}

export function getApiBaseUrl(): string {
  return resolveUrl(RAW_API_BASE_URL);
}

export function getSanctumCookieUrl(): string {
  return resolveUrl(RAW_SANCTUM_COOKIE_URL);
}

let csrfInitialized = false;

export async function initCsrf(): Promise<void> {
  if (csrfInitialized) return;
  try {
    await fetch(`${getSanctumCookieUrl()}/sanctum/csrf-cookie`, {
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

  const baseUrl = getApiBaseUrl();
  const url = endpoint.startsWith("http")
    ? resolveUrl(endpoint)
    : `${baseUrl}${endpoint.startsWith("/") ? endpoint : `/${endpoint}`}`;

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
