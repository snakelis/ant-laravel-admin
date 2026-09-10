import application from '../business/application';
import { LockOutlined, MailOutlined } from '@ant-design/icons';
import { LoginForm, ProFormCheckbox, ProFormText } from '@ant-design/pro-components';
import { history, useLocation, useModel } from '@umijs/max';
import { Alert, App as AntdApp } from 'antd';
import { useState } from 'react';
import { authApi } from '../services/api';
import { ApiError } from '../services/request';

interface LoginValues {
  email: string;
  password: string;
  remember?: boolean;
}

const safeRedirect = (value: string | null): string => {
  if (!value || !value.startsWith('/') || value.startsWith('//')) {
    return '/';
  }

  return value;
};

export const LoginPage = () => {
  const { initialState, setInitialState } = useModel('@@initialState');
  const { message } = AntdApp.useApp();
  const location = useLocation();
  const [errorText, setErrorText] = useState<string>();
  const redirect = safeRedirect(new URLSearchParams(location.search).get('redirect'));

  if (initialState?.currentUser) {
    history.replace(redirect);
    return null;
  }

  return (
    <div className="login-page">
      <LoginForm<LoginValues>
        title={application.title}
        subTitle="业务无关后台管理基础框架"
        initialValues={{ remember: false }}
        onFinish={async (values) => {
          setErrorText(undefined);
          try {
            const profile = await authApi.login(values);
            setInitialState({
              ...initialState,
              currentUser: profile,
              permissions: profile.permissions,
              menus: profile.menus,
              sessionError: undefined,
            });
            message.success('登录成功。');
            history.replace(redirect);
            return true;
          } catch (error) {
            const text = error instanceof ApiError ? error.message : '登录失败，请稍后重试。';
            setErrorText(text);
            message.error(text);
            return false;
          }
        }}
      >
        {errorText ? <Alert showIcon type="error" message={errorText} /> : null}
        <ProFormText
          name="email"
          fieldProps={{ size: 'large', prefix: <MailOutlined /> }}
          placeholder="邮箱"
          rules={[{ required: true, message: '请输入邮箱' }, { type: 'email', message: '请输入有效邮箱' }]}
        />
        <ProFormText.Password
          name="password"
          fieldProps={{ size: 'large', prefix: <LockOutlined /> }}
          placeholder="密码"
          rules={[{ required: true, message: '请输入密码' }]}
        />
        <ProFormCheckbox name="remember">保持登录</ProFormCheckbox>
      </LoginForm>
    </div>
  );
};

export default LoginPage;
