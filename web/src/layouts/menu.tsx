import {
  AppstoreOutlined,
  LockOutlined,
  MenuOutlined,
  SafetyCertificateOutlined,
  SettingOutlined,
  TeamOutlined,
  UserOutlined,
} from '@ant-design/icons';
import type { MenuDataItem } from '@ant-design/pro-components';
import type { ReactNode } from 'react';
import type { MenuItem } from '../types';
import { isExecutableMenu, isRegisteredComponent } from './componentRegistry';

const iconRegistry: Record<string, ReactNode> = {
  AppstoreOutlined: <AppstoreOutlined />,
  LockOutlined: <LockOutlined />,
  MenuOutlined: <MenuOutlined />,
  SafetyCertificateOutlined: <SafetyCertificateOutlined />,
  SettingOutlined: <SettingOutlined />,
  TeamOutlined: <TeamOutlined />,
  UserOutlined: <UserOutlined />,
};

export const resolveIcon = (name?: string | null): ReactNode => (name ? iconRegistry[name] : undefined);

export const flattenMenus = (menus: MenuItem[]): MenuItem[] => menus.flatMap((menu) => [menu, ...flattenMenus(menu.children ?? [])]);

const isVisibleMenu = (menu: MenuItem, permissions?: ReadonlySet<string>): boolean => {
  const hasPermission = !menu.permission_name || !permissions || permissions.has(menu.permission_name);
  return menu.is_enabled && menu.is_visible && hasPermission && isExecutableMenu(menu);
};

export const toProMenu = (menus: MenuItem[], permissions?: ReadonlySet<string>): MenuDataItem[] =>
  menus
    .filter((menu) => isVisibleMenu(menu, permissions))
    .map((menu) => {
      const children = menu.children ? toProMenu(menu.children, permissions) : undefined;
      const isLeafPage = isRegisteredComponent(menu.component);

      return {
        key: menu.name ?? menu.path ?? String(menu.id),
        path: isLeafPage ? (menu.path ?? undefined) : undefined,
        name: menu.title,
        icon: resolveIcon(menu.icon),
        children: children && children.length > 0 ? children : undefined,
      };
    })
    .filter((item) => item.path || item.children?.length);

export const findMenuByPath = (menus: MenuItem[], pathname: string): MenuItem | undefined =>
  flattenMenus(menus).find((menu) => menu.path === pathname && isExecutableMenu(menu));

export const firstNavigablePath = (menus: MenuItem[], permissions?: ReadonlySet<string>): string => {
  const flattened = flattenMenus(menus);
  return (
    flattened.find((menu) => {
      const hasPermission = !menu.permission_name || !permissions || permissions.has(menu.permission_name);
      return menu.path && hasPermission && menu.is_enabled && menu.is_visible && isRegisteredComponent(menu.component);
    })?.path ?? '/dashboard'
  );
};

export const menuToTreeOptions = (menus: MenuItem[], disabledId?: number): TreeOption[] =>
  menus.map((menu) => ({
    title: menu.title,
    value: menu.id,
    disabled: menu.id === disabledId,
    children: menu.children ? menuToTreeOptions(menu.children, disabledId) : undefined,
  }));

interface TreeOption {
  title: string;
  value: number;
  children?: TreeOption[];
  disabled?: boolean;
}
