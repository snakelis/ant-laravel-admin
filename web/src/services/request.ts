import { history } from '@umijs/max';
import type { ApiEnvelope, PaginatedData, ValidationErrors } from '../types';

const apiOrigin = (process.env.UMI_APP_API_ORIGIN ?? '').replace(/\/$/, '');
const apiBaseUrl = `${apiOrigin}/api/v1`;
const csrfUrl = `${apiOrigin}/sanctum/csrf-cookie`;

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly errors?: ValidationErrors;

  constructor(message: string, status: number, code = 'request_error', errors?: ValidationErrors) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.errors = errors;
  }
}

export interface RequestOptions<Body = unknown> extends Omit<RequestInit, 'body'> {
  body?: Body;
  params?: Record<string, string | number | boolean | null | undefined>;
  skipAuthRedirect?: boolean;
}

const readCookie = (name: string): string | undefined => {
  const value = document.cookie
    .split('; ')
    .find((row) => row.startsWith(`${name}=`))
    ?.split('=')[1];

  return value ? decodeURIComponent(value) : undefined;
};

const buildUrl = (path: string, params?: RequestOptions['params']): string => {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  const url = new URL(`${apiBaseUrl}${normalizedPath}`, window.location.origin);

  Object.entries(params ?? {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });

  return url.toString();
};

const parseJson = async (response: Response): Promise<unknown> => {
  const text = await response.text();
  if (text.trim() === '') {
    return null;
  }

  try {
    return JSON.parse(text) as unknown;
  } catch {
    throw new ApiError('服务器返回了无法解析的响应。', response.status, 'invalid_json');
  }
};

const isEnvelope = <T>(value: unknown): value is ApiEnvelope<T> => {
  return typeof value === 'object' && value !== null && 'success' in value && 'message' in value;
};

const redirectToLogin = () => {
  const { pathname, search, hash } = history.location;
  if (pathname !== '/login') {
    history.replace(`/login?redirect=${encodeURIComponent(`${pathname}${search}${hash}`)}`);
  }
};

const fetchWithTimeout = async <T>(
  url: string,
  init: RequestInit,
  read: (response: Response) => Promise<T>,
): Promise<T> => {
  const controller = new AbortController();
  const abort = () => controller.abort();
  const timeout = setTimeout(abort, 10000);
  if (init.signal?.aborted) controller.abort();
  init.signal?.addEventListener('abort', abort, { once: true });
  try {
    return await read(await fetch(url, { ...init, signal: controller.signal }));
  } catch (error) {
    if (error instanceof ApiError) throw error;
    if (controller.signal.aborted) {
      throw new ApiError(init.signal?.aborted ? '请求已取消。' : '请求超时，请稍后重试。', 0,
        init.signal?.aborted ? 'request_cancelled' : 'request_timeout');
    }
    throw new ApiError('无法连接服务器，请检查网络后重试。', 0, 'network_error');
  } finally {
    clearTimeout(timeout);
    init.signal?.removeEventListener('abort', abort);
  }
};

export const ensureCsrfCookie = async (): Promise<void> => {
  await fetchWithTimeout(csrfUrl, {
    method: 'GET',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  }, async (response) => {
    if (!response.ok) {
      throw new ApiError('无法获取 CSRF Cookie，请确认后端服务可访问。', response.status, 'csrf_error');
    }
  });
};

const fetchEnvelope = async <T, Body = unknown>(
  path: string,
  options: RequestOptions<Body> = {},
): Promise<ApiEnvelope<T>> => {
  const { body, params, headers, skipAuthRedirect, ...init } = options;
  const token = readCookie('XSRF-TOKEN');
  return fetchWithTimeout(buildUrl(path, params), {
    ...init,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { 'X-XSRF-TOKEN': token } : {}),
      ...headers,
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  }, async (response) => {
    const json = await parseJson(response);
    if (!isEnvelope<T>(json)) {
      throw new ApiError('服务器响应格式不符合统一 API 协议。', response.status, 'invalid_envelope');
    }

    if (!response.ok || !json.success) {
      if (response.status === 401 && !skipAuthRedirect) {
        redirectToLogin();
      }

      throw new ApiError(json.message || '请求失败。', response.status, json.code, json.errors);
    }

    return json;
  });
};

export const request = async <T, Body = unknown>(path: string, options: RequestOptions<Body> = {}): Promise<T> => {
  const json = await fetchEnvelope<T, Body>(path, options);
  return json.data;
};

export const requestPaginated = async <T, Body = unknown>(
  path: string,
  options: RequestOptions<Body> = {},
): Promise<PaginatedData<T>> => {
  const json = await fetchEnvelope<T[], Body>(path, options);

  return {
    data: json.data,
    pagination: json.meta?.pagination,
  };
};
