import type { CurrentUser } from '../types';
import { authApi } from './api';
import { ApiError } from './request';

export async function fetchCurrentUser(): Promise<CurrentUser | undefined> {
  try {
    return await authApi.me();
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      return undefined;
    }
    throw error;
  }
}

export async function endSession(): Promise<void> {
  try {
    await authApi.logout();
  } catch (error) {
    // 已过期的会话无需再次退出；其他错误不能当作退出成功。
    if (!(error instanceof ApiError && error.status === 401)) {
      throw error;
    }
  }
}
