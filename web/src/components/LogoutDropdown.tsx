import { LogoutOutlined } from '@ant-design/icons';
import { history, useModel } from '@umijs/max';
import { App, Dropdown } from 'antd';
import type { ReactNode } from 'react';
import { useRef, useState } from 'react';
import { endSession } from '../services/session';

export const LogoutDropdown = ({ children }: { children: ReactNode }) => {
  const { setInitialState } = useModel('@@initialState');
  const { message } = App.useApp();
  const pending = useRef(false);
  const [loading, setLoading] = useState(false);

  const logout = async () => {
    if (pending.current) return;
    pending.current = true;
    setLoading(true);
    try {
      await endSession();
      await setInitialState((state) => ({
        ...state,
        currentUser: undefined,
        permissions: [],
        menus: [],
        sessionError: undefined,
      }));
      history.replace('/login');
    } catch {
      message.error('未能确认退出登录，请检查网络后重试。');
    } finally {
      pending.current = false;
      setLoading(false);
    }
  };

  return (
    <Dropdown menu={{ items: [{
      key: 'logout',
      icon: <LogoutOutlined />,
      label: loading ? '正在退出…' : '退出登录',
      disabled: loading,
      onClick: () => { void logout(); },
    }] }} placement="bottomRight">
      {children}
    </Dropdown>
  );
};
