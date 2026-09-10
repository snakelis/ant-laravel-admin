import { businessAccessPermissions } from './business/access';
import { mergeRegistrations } from './utils/extensions';
import type { AppInitialState } from './types';

const hasPermission = (initialState: AppInitialState | undefined, permission: string): boolean => {
  if (!initialState?.currentUser) {
    return true;
  }

  return initialState.permissions.includes(permission);
};

export default function access(initialState: AppInitialState | undefined) {
  return mergeRegistrations({
    canViewSystemUsers: hasPermission(initialState, 'system.user.view'),
    canViewSystemRoles: hasPermission(initialState, 'system.role.view'),
    canViewSystemPermissions: hasPermission(initialState, 'system.permission.view'),
    canViewSystemMenus: hasPermission(initialState, 'system.menu.view'),
  }, Object.fromEntries(Object.entries(businessAccessPermissions).map(([name, permission]) => [name, hasPermission(initialState, permission)])));
}
