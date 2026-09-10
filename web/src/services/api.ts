import type {
  CurrentUser,
  PasswordPolicy,
  MenuItem,
  MenuPayload,
  PageParams,
  PageResult,
  Permission,
  PermissionPayload,
  PermissionTreeNode,
  Role,
  RolePayload,
  User,
  UserPayload,
} from '../types';
import { ensureCsrfCookie, request, requestPaginated } from './request';

const pageQuery = (params: PageParams): Record<string, string | number | undefined> => ({
  page: params.current ?? 1,
  per_page: params.pageSize ?? 15,
  search: params.search,
});

const toPageResult = <T>(items: T[], total?: number): PageResult<T> => ({
  data: items,
  success: true,
  total: total ?? items.length,
});

export const authApi = {
  async login(payload: { email: string; password: string; remember?: boolean }): Promise<CurrentUser> {
    await ensureCsrfCookie();
    return request<CurrentUser, typeof payload>('/auth/login', {
      method: 'POST',
      body: payload,
      skipAuthRedirect: true,
    });
  },
  me(): Promise<CurrentUser> {
    return request<CurrentUser>('/me', { skipAuthRedirect: true });
  },
  logout(): Promise<null> {
    return request<null>('/auth/logout', { method: 'POST', skipAuthRedirect: true });
  },
};

export const userApi = {
  async list(params: PageParams): Promise<PageResult<User>> {
    const result = await requestPaginated<User>('/users', { params: pageQuery(params) });
    return toPageResult(result.data, result.pagination?.total);
  },
  create(payload: UserPayload): Promise<User> {
    return request<User, UserPayload>('/users', { method: 'POST', body: payload });
  },
  update(id: number, payload: UserPayload): Promise<User> {
    return request<User, UserPayload>(`/users/${id}`, { method: 'PATCH', body: payload });
  },
  remove(id: number): Promise<null> {
    return request<null>(`/users/${id}`, { method: 'DELETE' });
  },
};

export const roleApi = {
  async list(params: PageParams = {}): Promise<PageResult<Role>> {
    const result = await requestPaginated<Role>('/roles', { params: pageQuery(params) });
    return toPageResult(result.data, result.pagination?.total);
  },
  create(payload: RolePayload): Promise<Role> {
    return request<Role, RolePayload>('/roles', { method: 'POST', body: payload });
  },
  update(id: number, payload: RolePayload): Promise<Role> {
    return request<Role, RolePayload>(`/roles/${id}`, { method: 'PATCH', body: payload });
  },
  remove(id: number): Promise<null> {
    return request<null>(`/roles/${id}`, { method: 'DELETE' });
  },
};

export const permissionApi = {
  async list(params: PageParams & { module_code?: string; resource_code?: string; is_enabled?: string; is_system?: string } = {}): Promise<PageResult<Permission>> {
    const result = await requestPaginated<Permission>('/permissions', {
      params: { ...pageQuery({ pageSize: 100, ...params }), per_page: params.pageSize ?? 100, module_code: params.module_code, resource_code: params.resource_code, is_enabled: params.is_enabled, is_system: params.is_system },
    });
    return toPageResult(result.data, result.pagination?.total);
  },
  tree(params: { search?: string; enabledOnly?: boolean } = {}): Promise<PermissionTreeNode[]> {
    return request<PermissionTreeNode[]>('/permissions', {
      params: { tree: true, search: params.search, enabled_only: params.enabledOnly },
    });
  },
  create(payload: PermissionPayload): Promise<Permission> {
    return request<Permission, PermissionPayload>('/permissions', { method: 'POST', body: payload });
  },
  update(id: number, payload: PermissionPayload): Promise<Permission> {
    return request<Permission, PermissionPayload>(`/permissions/${id}`, { method: 'PATCH', body: payload });
  },
  remove(id: number): Promise<null> {
    return request<null>(`/permissions/${id}`, { method: 'DELETE' });
  },
};

export const menuApi = {
  async list(params: PageParams): Promise<PageResult<MenuItem>> {
    const result = await requestPaginated<MenuItem>('/menus', { params: pageQuery(params) });
    return toPageResult(result.data, result.pagination?.total);
  },
  tree(enabledOnly = false): Promise<MenuItem[]> {
    return request<MenuItem[]>('/menus', { params: { tree: true, enabled_only: enabledOnly } });
  },
  create(payload: MenuPayload): Promise<MenuItem> {
    return request<MenuItem, MenuPayload>('/menus', { method: 'POST', body: payload });
  },
  update(id: number, payload: MenuPayload): Promise<MenuItem> {
    return request<MenuItem, MenuPayload>(`/menus/${id}`, { method: 'PATCH', body: payload });
  },
  remove(id: number): Promise<null> {
    return request<null>(`/menus/${id}`, { method: 'DELETE' });
  },
};

export const managementOptionsApi = {
  userForm: async () => {
    const [roles, passwordPolicy] = await Promise.all([
      request<Pick<Role, 'id' | 'name'>[]>('/management-options/user-roles'),
      request<PasswordPolicy>('/management-options/user-password-policy'),
    ]);
    return { roles, passwordPolicy };
  },
  roles: () => request<Pick<Role, 'id' | 'name'>[]>('/management-options/user-roles'),
  permissionTree: () => request<PermissionTreeNode[]>('/management-options/role-permissions'),
  menuPermissions: () => request<Permission[]>('/management-options/menu-permissions'),
};
