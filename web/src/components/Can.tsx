import { useModel } from '@umijs/max';
import type { ReactNode } from 'react';

interface CanProps {
  permission?: string | null;
  children: ReactNode;
  fallback?: ReactNode;
}

export const Can = ({ permission, children, fallback = null }: CanProps) => {
  const { initialState } = useModel('@@initialState');
  const allowed = !permission || Boolean(initialState?.permissions.includes(permission));

  return allowed ? <>{children}</> : <>{fallback}</>;
};
