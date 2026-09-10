import { PlusOutlined } from '@ant-design/icons';
import {
  ActionType,
  ModalForm,
  ProColumns,
  ProFormSelect,
  ProFormText,
  ProTable,
} from '@ant-design/pro-components';
import { App as AntdApp, Button, Tooltip, Popconfirm, Space, Tag } from 'antd';
import { useRef, useState } from 'react';
import { useFormOptions } from '../../hooks/useFormOptions';
import { Can } from '../../components/Can';
import { managementOptionsApi, userApi } from '../../services/api';
import { useManagementForm } from '../../hooks/useManagementForm';
import type { User, UserPayload } from '../../types';
import { passwordPolicyError } from '../../utils/passwordPolicy';
import { formatDateTime, roleNames } from '../../utils/format';

interface UserFormValues extends Record<string, unknown> {
  name: string;
  email: string;
  password?: string;
  role_ids?: number[];
}

const toPayload = (values: UserFormValues, editing: boolean): UserPayload => {
  const payload: UserPayload = {
    name: values.name,
    email: values.email,
    role_ids: values.role_ids ?? [],
  };

  if (!editing || values.password) {
    payload.password = values.password;
  }

  return payload;
};

export const SystemUserPage = () => {
  const { message } = AntdApp.useApp();
  const actionRef = useRef<ActionType>(undefined);

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<User | null>(null);

  const options = useFormOptions(managementOptionsApi.userForm, modalOpen);
  const roles = options.data?.roles ?? [];
  const passwordPolicy = options.data?.passwordPolicy;
  const availableRoleIds = new Set(roles.map((role) => role.id));
  const retainedRoles = (editing?.roles ?? []).filter(
    (role) => !availableRoleIds.has(role.id),
  );

  const { formRef, dirty, handleError, refreshSession, close } =
    useManagementForm<UserFormValues>();

  const columns: ProColumns<User>[] = [
    {
      title: '关键词',
      dataIndex: 'search',
      hideInTable: true,
      fieldProps: { placeholder: '输入名称、标题或编码' },
    },
    { title: 'ID', dataIndex: 'id', width: 80, search: false },
    { title: '姓名', dataIndex: 'name', search: false, ellipsis: true },
    { title: '邮箱', dataIndex: 'email', search: false, ellipsis: true },
    {
      title: '角色',
      dataIndex: 'roles',
      search: false,
      render: (_, record) =>
        record.roles?.length ? (
          roleNames(record.roles).map((name) => <Tag key={name}>{name}</Tag>)
        ) : (
          <span>未分配角色</span>
        ),
    },
    {
      title: '创建时间',
      dataIndex: 'created_at',
      search: false,
      renderText: (value?: string) => formatDateTime(value),
    },
    {
      title: '操作',
      valueType: 'option',
      width: 180,
      render: (_, record) => (
        <Space>
          <Can permission="system.user.update">
            <Tooltip title={record.capabilities?.update_reason}>
              <Button
                disabled={record.capabilities?.update === false}
                type="link"
                onClick={() => {
                  setEditing(record);
                  setModalOpen(true);
                }}
              >
                编辑
              </Button>
            </Tooltip>
          </Can>
          <Can permission="system.user.delete">
            <Tooltip title={record.capabilities?.delete_reason}>
              <Popconfirm
                disabled={record.capabilities?.delete === false}
                title={`确认删除“${record.name}”？`}
                onConfirm={async () => {
                  try {
                    await userApi.remove(record.id);
                    message.success('删除成功。');
                    void refreshSession();
                    void actionRef.current?.reload();
                  } catch (error) {
                    handleError(error);
                  }
                }}
              >
                <Button
                  disabled={record.capabilities?.delete === false}
                  danger
                  type="link"
                >
                  删除
                </Button>
              </Popconfirm>
            </Tooltip>
          </Can>
        </Space>
      ),
    },
  ];

  return (
    <>
      <ProTable<User, { search?: string }>
        rowKey="id"
        scroll={{ x: 1000 }}
        onRequestError={() =>
          message.error('列表加载失败，请点击表格刷新按钮重试。')
        }
        actionRef={actionRef}
        columns={columns}
        request={(params) =>
          userApi.list({
            current: params.current,
            pageSize: params.pageSize,
            search: params.search,
          })
        }
        search={{ labelWidth: 'auto' }}
        pagination={{ defaultPageSize: 15 }}
        toolBarRender={() => [
          <Can key="create" permission="system.user.create">
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => {
                setEditing(null);
                setModalOpen(true);
              }}
            >
              新建用户
            </Button>
          </Can>,
        ]}
      />
      <ModalForm<UserFormValues>
        key={editing?.id ?? 'create'}
        title={editing ? '编辑用户' : '新建用户'}
        open={modalOpen}
        formRef={formRef}
        onValuesChange={() => {
          dirty.current = true;
        }}
        modalProps={{ destroyOnHidden: true, maskClosable: false }}
        initialValues={
          editing
            ? { ...editing, role_ids: editing.roles?.map((role) => role.id) }
            : undefined
        }
        onOpenChange={(open) => {
          if (open) {
            dirty.current = false;
            setModalOpen(true);
          } else
            close(() => {
              setModalOpen(false);
              setEditing(null);
            });
        }}
        submitter={{ submitButtonProps: { disabled: !options.ready } }}
        onFinish={async (values) => {
          if (!options.ready) return false;
          try {
            if (editing) {
              await userApi.update(editing.id, {
                ...toPayload(values, true),
                role_ids: [
                  ...new Set([
                    ...(values.role_ids ?? []),
                    ...retainedRoles.map((role) => role.id),
                  ]),
                ],
              });
            } else {
              await userApi.create(toPayload(values, false));
            }
            dirty.current = false;
            message.success('保存成功。');
            void refreshSession();
            void actionRef.current?.reload();
            return true;
          } catch (error) {
            handleError(error);
            return false;
          }
        }}
      >
        {options.notice}
        <ProFormText
          name="name"
          label="姓名"
          rules={[{ required: true, message: '请输入姓名' }]}
        />
        <ProFormText
          name="email"
          label="邮箱"
          rules={[{ required: true, message: '请输入邮箱' }, { type: 'email' }]}
        />
        <ProFormText.Password
          name="password"
          label="密码"
          tooltip={editing ? '留空则不修改密码' : undefined}
          extra={
            editing
              ? `留空保持原密码；修改时${passwordPolicy?.description ?? '正在加载密码规则'}。`
              : passwordPolicy?.description ?? '正在加载密码规则'
          }
          rules={[
            { required: !editing, message: '请输入密码' },
            { validator: async (_, value?: string) => {
              if (!value) return;
              if (!passwordPolicy || !options.ready) throw new Error('请等待密码规则加载完成');
              const error = passwordPolicyError(value, passwordPolicy);
              if (error) throw new Error(error);
            } },
          ]}
        />
        <ProFormSelect
          name="role_ids"
          label="角色"
          mode="multiple"
          fieldProps={{ loading: options.loading, optionFilterProp: 'label' }}
          options={[
            ...roles.map((role) => ({ label: role.name, value: role.id })),
            ...retainedRoles.map((role) => ({
              label: `${role.name}（保留）`,
              value: role.id,
              disabled: true,
            })),
          ]}
        />
      </ModalForm>
    </>
  );
};

export default SystemUserPage;
