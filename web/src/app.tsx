import { UserOutlined } from '@ant-design/icons';
import type { Settings as LayoutSettings } from '@ant-design/pro-components';
import type { RequestConfig, RunTimeLayoutConfig } from '@umijs/max';
import { history, Link } from '@umijs/max';
import { App as AntdApp, Button, Result } from 'antd';
import dayjs from 'dayjs';
import 'dayjs/locale/zh-cn';
import type { ReactNode } from 'react';
import defaultSettings from '../config/defaultSettings';
import { Forbidden } from './components/Forbidden';
import { LogoutDropdown } from './components/LogoutDropdown';
import { toProMenu } from './layouts/menu';
import { fetchCurrentUser } from './services/session';
import type { AppInitialState } from './types';

dayjs.locale('zh-cn');

const loginPath = '/login';

const buildRedirectLoginPath = () => {
  const { pathname, search, hash } = history.location;
  const redirect = encodeURIComponent(`${pathname}${search}${hash}`);
  return `${loginPath}?redirect=${redirect}`;
};

export async function getInitialState(): Promise<AppInitialState> {
  const initialState: AppInitialState = {
    settings: defaultSettings as Partial<LayoutSettings>,
    currentUser: undefined,
    permissions: [],
    menus: [],
    fetchUserInfo: fetchCurrentUser,
  };

  if (history.location.pathname === loginPath) {
    return initialState;
  }

  try {
    const currentUser = await fetchCurrentUser();
    return {
      ...initialState,
      currentUser,
      permissions: currentUser?.permissions ?? [],
      menus: currentUser?.menus ?? [],
    };
  } catch {
    return { ...initialState, sessionError: '暂时无法确认登录状态，请检查网络或稍后重试。' };
  }
}

export const rootContainer = (container: ReactNode) => <AntdApp>{container}</AntdApp>;

export const layout: RunTimeLayoutConfig = ({ initialState }) => ({
  title: defaultSettings.title,
  logo: defaultSettings.logo,
  layout: 'mix',
  menu: { locale: false },
  menuDataRender: () => toProMenu(initialState?.menus ?? [], new Set(initialState?.permissions ?? [])),
  menuItemRender: (item, dom) => (item.path ? <Link to={item.path}>{dom}</Link> : dom),
  avatarProps: {
    icon: <UserOutlined />,
    title: initialState?.currentUser?.user.name ?? '未登录',
    render: (_, dom) => (
      <LogoutDropdown>{dom}</LogoutDropdown>
    ),
  },
  onPageChange: () => {
    if (!initialState?.sessionError && !initialState?.currentUser && history.location.pathname !== loginPath) {
      history.replace(buildRedirectLoginPath());
    }
  },
  unAccessible: <Forbidden />,
  childrenRender: (children) => initialState?.sessionError ? (
    <Result status="warning" title="登录状态加载失败" subTitle={initialState.sessionError}
      extra={<Button type="primary" onClick={() => window.location.reload()}>重新加载</Button>} />
  ) : children,
  ...initialState?.settings,
});

export const request: RequestConfig = {
  timeout: 10000,
  withCredentials: true,
  errorConfig: {
    errorThrower: (response) => {
      const data = response as { success?: boolean; message?: string };
      if (data.success === false) {
        throw new Error(data.message || '请求失败。');
      }
    },
    errorHandler: (error) => {
      throw error;
    },
  },
};
