import { businessComponents } from '../business/components';
import { mergeRegistrations } from '../utils/extensions';
import type { RegisteredComponent } from './registeredComponent';
import type { MenuItem } from '../types';

const foundationComponents = {
  SystemOverview: { label: '后台概览', routePath: '/dashboard' },
  SystemUserList: { label: '用户管理', routePath: '/system/users', permission: 'system.user.view' },
  SystemRoleList: { label: '角色管理', routePath: '/system/roles', permission: 'system.role.view' },
  SystemPermissionList: { label: '权限管理', routePath: '/system/permissions', permission: 'system.permission.view' },
  SystemMenuList: { label: '菜单管理', routePath: '/system/menus', permission: 'system.menu.view' },
} satisfies Record<string, RegisteredComponent>;

export const componentRegistry = mergeRegistrations(foundationComponents, businessComponents);

export type ComponentRegistryKey = keyof typeof componentRegistry;

export const registeredComponentNames = Object.keys(componentRegistry) as ComponentRegistryKey[];

export const isRegisteredComponent = (component?: string | null): component is ComponentRegistryKey => {
  return Boolean(component && Object.prototype.hasOwnProperty.call(componentRegistry, component));
};

export const isRouteView = (component?: string | null): boolean => component === 'RouteView';

export const isExecutableMenu = (menu: MenuItem): boolean => isRouteView(menu.component) || isRegisteredComponent(menu.component);

export interface RoutedPageProps {
  menu?: MenuItem;
}
