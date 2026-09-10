import dayjs from 'dayjs';
import type { MenuItem, Role, ValidationErrors } from '../types';

export const formatDateTime = (value?: string | null): string => {
  if (!value) {
    return '-';
  }

  return dayjs(value).format('YYYY-MM-DD HH:mm:ss');
};

export const roleNames = (roles?: Role[]): string[] => roles?.map((role) => role.name) ?? [];

export const validationErrorsToText = (errors?: ValidationErrors): string => {
  if (!errors) {
    return '';
  }

  return Object.values(errors).flat().join('\n');
};

export interface TreeOption {
  title: string;
  value: number;
  children?: TreeOption[];
}

export const menuToTreeOptions = (menus: MenuItem[], disabledId?: number): TreeOption[] =>
  menus.map((menu) => ({
    title: menu.title,
    value: menu.id,
    disabled: menu.id === disabledId,
    children: menu.children ? menuToTreeOptions(menu.children, disabledId) : undefined,
  }));
