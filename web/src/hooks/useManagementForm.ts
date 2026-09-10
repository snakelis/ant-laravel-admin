import type { ProFormInstance } from '@ant-design/pro-components';
import { useModel } from '@umijs/max';
import { App } from 'antd';
import { useRef } from 'react';
import { authApi } from '../services/api';
import { ApiError } from '../services/request';

export function useManagementForm<T extends Record<string, unknown>>() {
  const { message, modal } = App.useApp();
  const { setInitialState } = useModel('@@initialState');
  const formRef = useRef<ProFormInstance<T>>(undefined);
  const dirty = useRef(false);
  const handleError = (error: unknown) => {
    if (error instanceof ApiError && error.status === 422 && error.errors) {
      const fields = Object.entries(error.errors).map(([name, errors]) => ({
        name: name.split('.'),
        errors,
      }));
      // Laravel 使用点分路径返回字段名，运行时映射到 Ant Form 的 NamePath。
      formRef.current?.setFields(
        fields as Parameters<ProFormInstance<T>['setFields']>[0],
      );
      if (fields[0])
        formRef.current?.scrollToField(fields[0].name, { block: 'center' });
      message.error('请检查表单中标出的内容。');
      return;
    }
    message.error(
      error instanceof Error ? error.message : '操作失败，请重试。',
    );
  };
  const refreshSession = async () => {
    try {
      const currentUser = await authApi.me();
      await setInitialState((previous) => ({
        ...previous,
        currentUser,
        permissions: currentUser.permissions,
        menus: currentUser.menus,
      }));
    } catch {
      message.warning('操作已成功，但菜单和权限刷新失败，请刷新页面。');
    }
  };
  const close = (done: () => void) => {
    if (!dirty.current) {
      done();
      return;
    }
    modal.confirm({
      title: '放弃尚未保存的修改？',
      okText: '放弃修改',
      cancelText: '继续编辑',
      onOk: () => {
        dirty.current = false;
        done();
      },
    });
  };
  return { formRef, dirty, handleError, refreshSession, close };
}
