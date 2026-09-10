export interface RecordCapabilities {
  update: boolean;
  delete: boolean;
  update_reason: string | null;
  delete_reason: string | null;
  rename?: boolean;
  change_status?: boolean;
  protected?: boolean;
}

import type { Settings as LayoutSettings } from '@ant-design/pro-components';
export interface ApiEnvelope<T> {
  success: boolean;
  code: string;
  message: string;
  data: T;
  meta?: ApiMeta;
  errors?: ValidationErrors;
}

export interface ApiMeta {
  pagination?: PaginationMeta;
  [key: string]: unknown;
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  count: number;
  has_more_pages: boolean;
  total?: number;
  last_page?: number;
  from?: number | null;
  to?: number | null;
}

export type ValidationErrors = Record<string, string[]>;

export interface PaginatedData<T> {
  data: T[];
  pagination?: PaginationMeta;
}

export interface User {
  capabilities?: RecordCapabilities;
  id: number;
  name: string;
  email: string;
  email_verified_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  roles?: Role[];
  permissions?: string[];
}

export interface CurrentUser {
  user: User;
  roles: string[];
  permissions: string[];
  menus: MenuItem[];
}

export interface Role {
  capabilities?: RecordCapabilities;
  id: number;
  name: string;
  guard_name: string;
  created_at?: string | null;
  updated_at?: string | null;
  permissions?: Permission[];
}

export interface Permission {
  capabilities?: RecordCapabilities;
  id: number;
  name: string;
  guard_name: string;
  title?: string | null;
  module_code?: string | null;
  module_title?: string | null;
  resource_code?: string | null;
  resource_title?: string | null;
  action_code?: string | null;
  action_title?: string | null;
  description?: string | null;
  sort?: number;
  is_system?: boolean;
  is_enabled?: boolean;
  created_at?: string | null;
  updated_at?: string | null;
}

export type PermissionTreeNodeType = 'module' | 'resource' | 'permission';

export interface PermissionTreeNode {
  key: string;
  type: PermissionTreeNodeType;
  title: string;
  children?: PermissionTreeNode[];
  id?: number;
  name?: string;
  action_code?: string | null;
  action_title?: string | null;
  is_system?: boolean;
  is_enabled?: boolean;
}

export interface MenuItem {
  id: number;
  parent_id: number | null;
  title: string;
  name: string | null;
  path: string | null;
  component: string | null;
  icon: string | null;
  permission_name: string | null;
  sort: number;
  is_visible: boolean;
  is_enabled: boolean;
  created_at?: string | null;
  updated_at?: string | null;
  children?: MenuItem[];
}

export interface PageParams {
  current?: number;
  pageSize?: number;
  search?: string;
}

export interface PageResult<T> {
  data: T[];
  success: boolean;
  total: number;
}

export interface UserPayload {
  name: string;
  email: string;
  password?: string;
  role_ids?: number[];
}

export interface RolePayload {
  name: string;
  permission_ids?: number[];
}

export interface PermissionPayload {
  name: string;
  title?: string | null;
  module_code?: string | null;
  module_title?: string | null;
  resource_code?: string | null;
  resource_title?: string | null;
  action_code?: string | null;
  action_title?: string | null;
  description?: string | null;
  sort?: number;
  is_enabled?: boolean;
}

export interface MenuPayload {
  parent_id?: number | null;
  title: string;
  name?: string | null;
  path?: string | null;
  component?: string | null;
  icon?: string | null;
  permission_name?: string | null;
  sort?: number;
  is_visible?: boolean;
  is_enabled?: boolean;
}

export interface AppInitialState {
  sessionError?: string;
  settings?: LayoutSettings;
  currentUser?: CurrentUser;
  permissions: string[];
  menus: MenuItem[];
  fetchUserInfo?: () => Promise<CurrentUser | undefined>;
}

export interface PasswordPolicy {
  min_length: number;
  max_length: number;
  mixed_case: boolean;
  numbers: boolean;
  symbols: boolean;
  description: string;
}
