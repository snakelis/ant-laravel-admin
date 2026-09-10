import { PlusOutlined } from '@ant-design/icons';
import {
  ActionType,
  ModalForm,
  ProColumns,
  ProFormDigit,
  ProFormGroup,
  ProFormSwitch,
  ProFormText,
  ProTable,
} from '@ant-design/pro-components';
import {
  App as AntdApp,
  Button,
  Tooltip,
  Popconfirm,
  Space,
  Tag,
  Typography,
} from 'antd';
import { useRef, useState } from 'react';
import { Can } from '../../components/Can';
import { permissionApi } from '../../services/api';
import { useManagementForm } from '../../hooks/useManagementForm';
import type { Permission, PermissionPayload } from '../../types';
import { formatDateTime } from '../../utils/format';

const { Text } = Typography;

interface PermissionFormValues extends Record<string, unknown> {
  name: string;
  title?: string;
  module_code?: string;
  module_title?: string;
  resource_code?: string;
  resource_title?: string;
  action_code?: string;
  action_title?: string;
  description?: string;
  sort?: number;
  is_enabled?: boolean;
}

const nullable = (value?: string): string | null =>
  value && value.trim() !== '' ? value.trim() : null;

const toPayload = (values: PermissionFormValues): PermissionPayload => ({
  name: values.name,
  title: nullable(values.title),
  module_code: nullable(values.module_code) ?? undefined,
  module_title: nullable(values.module_title),
  resource_code: nullable(values.resource_code) ?? undefined,
  resource_title: nullable(values.resource_title),
  action_code: nullable(values.action_code) ?? undefined,
  action_title: nullable(values.action_title),
  description: nullable(values.description),
  sort: values.sort ?? 0,
  is_enabled: values.is_enabled ?? true,
});

const permissionDisplayTitle = (permission: Permission): string =>
  permission.title || permission.action_title || permission.name;

export const SystemPermissionPage = () => {
  const { message } = AntdApp.useApp();
  const actionRef = useRef<ActionType>(undefined);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Permission | null>(null);

  const { formRef, dirty, handleError, refreshSession, close } =
    useManagementForm<PermissionFormValues>();

  const columns: ProColumns<Permission>[] = [
    {
      title: '关键词',
      dataIndex: 'search',
      hideInTable: true,
      fieldProps: { placeholder: '输入名称、标题或编码' },
    },
    { title: '模块编码', dataIndex: 'module_code', hideInTable: true },
    { title: '资源编码', dataIndex: 'resource_code', hideInTable: true },
    {
      title: '启用状态',
      dataIndex: 'is_enabled',
      hideInTable: true,
      valueType: 'select',
      valueEnum: { '1': '启用', '0': '停用' },
    },
    {
      title: '权限来源',
      dataIndex: 'is_system',
      hideInTable: true,
      valueType: 'select',
      valueEnum: { '1': '系统内置', '0': '自定义' },
    },
    { title: 'ID', dataIndex: 'id', width: 80, search: false },
    {
      title: '权限码',
      dataIndex: 'name',
      search: false,
      copyable: true,
      ellipsis: true,
    },
    {
      title: '标题',
      dataIndex: 'title',
      search: false,
      render: (_, record) => permissionDisplayTitle(record),
    },
    {
      title: '模块',
      dataIndex: 'module_title',
      search: false,
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <span>{record.module_title ?? '-'}</span>
          <Text type="secondary">{record.module_code ?? '-'}</Text>
        </Space>
      ),
    },
    {
      title: '资源',
      dataIndex: 'resource_title',
      search: false,
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <span>{record.resource_title ?? '-'}</span>
          <Text type="secondary">{record.resource_code ?? '-'}</Text>
        </Space>
      ),
    },
    {
      title: '操作',
      dataIndex: 'action_title',
      search: false,
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <span>{record.action_title ?? '-'}</span>
          <Text type="secondary">{record.action_code ?? '-'}</Text>
        </Space>
      ),
    },
    {
      title: '属性',
      dataIndex: 'is_system',
      search: false,
      width: 150,
      render: (_, record) => (
        <Space>
          <Tag color={record.is_system ? 'blue' : 'default'}>
            {record.is_system ? '系统内置' : '自定义'}
          </Tag>
          <Tag color={record.is_enabled === false ? 'red' : 'green'}>
            {record.is_enabled === false ? '停用' : '启用'}
          </Tag>
        </Space>
      ),
    },
    { title: '排序', dataIndex: 'sort', search: false, width: 80 },
    {
      title: '创建时间',
      dataIndex: 'created_at',
      search: false,
      renderText: (value?: string) => formatDateTime(value),
    },
    {
      title: '管理',
      valueType: 'option',
      width: 180,
      render: (_, record) => (
        <Space>
          <Can permission="system.permission.update">
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
          <Can permission="system.permission.delete">
            <Tooltip title={record.capabilities?.delete_reason}>
              <Popconfirm
                disabled={record.capabilities?.delete === false}
                title={`确认删除“${record.name}”？`}
                onConfirm={async () => {
                  try {
                    await permissionApi.remove(record.id);
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
      <ProTable<
        Permission,
        {
          search?: string;
          module_code?: string;
          resource_code?: string;
          is_enabled?: string;
          is_system?: string;
        }
      >
        rowKey="id"
        scroll={{ x: 1000 }}
        onRequestError={() =>
          message.error('列表加载失败，请点击表格刷新按钮重试。')
        }
        actionRef={actionRef}
        columns={columns}
        request={(params) =>
          permissionApi.list({
            current: params.current,
            pageSize: params.pageSize,
            search: params.search,
            module_code: params.module_code,
            resource_code: params.resource_code,
            is_enabled: params.is_enabled,
            is_system: params.is_system,
          })
        }
        search={{ labelWidth: 'auto' }}
        pagination={{ defaultPageSize: 15 }}
        toolBarRender={() => [
          <Can key="create" permission="system.permission.create">
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => {
                setEditing(null);
                setModalOpen(true);
              }}
            >
              新建权限
            </Button>
          </Can>,
        ]}
      />
      <ModalForm<PermissionFormValues>
        key={editing?.id ?? 'create'}
        title={editing ? '编辑权限' : '新建权限'}
        open={modalOpen}
        formRef={formRef}
        onValuesChange={() => {
          dirty.current = true;
        }}
        modalProps={{ destroyOnHidden: true, maskClosable: false, width: 720 }}
        initialValues={
          editing
            ? {
                name: editing.name,
                title: editing.title ?? undefined,
                module_code: editing.module_code ?? undefined,
                module_title: editing.module_title ?? undefined,
                resource_code: editing.resource_code ?? undefined,
                resource_title: editing.resource_title ?? undefined,
                action_code: editing.action_code ?? undefined,
                action_title: editing.action_title ?? undefined,
                description: editing.description ?? undefined,
                sort: editing.sort ?? 0,
                is_enabled: editing.is_enabled ?? true,
              }
            : { sort: 0, is_enabled: true }
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
        onFinish={async (values) => {
          try {
            if (editing) {
              await permissionApi.update(editing.id, toPayload(values));
            } else {
              await permissionApi.create(toPayload(values));
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
        <ProFormText
          name="name"
          label="权限码"
          readonly={Boolean(editing && !editing.capabilities?.rename)}
          extra={
            editing && !editing.capabilities?.rename
              ? '系统内置、已分配或已引用的权限不能改名。'
              : '使用稳定的点分编码，例如 extension.report.view。'
          }
          rules={[
            { required: true, message: '请输入权限码' },
            {
              pattern: /^[a-z][a-z0-9]*(\.[a-z][a-z0-9-]*)+$/,
              message: '例如 extension.report.view',
            },
          ]}
        />
        <ProFormGroup>
          <ProFormText
            name="title"
            label="标题"
            tooltip="面向授权页和列表展示的友好名称，例如 查看菜单"
          />
          <ProFormText
            readonly={Boolean(editing?.capabilities?.protected)}
            name="module_code"
            label="模块编码"
            tooltip="例如 system；为空时后端按权限码分段推断"
          />
          <ProFormText
            name="module_title"
            label="模块标题"
            tooltip="例如 系统管理"
          />
          <ProFormText
            readonly={Boolean(editing?.capabilities?.protected)}
            name="resource_code"
            label="资源编码"
            tooltip="例如 menu；为空时后端按权限码分段推断"
          />
          <ProFormText
            name="resource_title"
            label="资源标题"
            tooltip="例如 菜单管理"
          />
          <ProFormText
            readonly={Boolean(editing?.capabilities?.protected)}
            name="action_code"
            label="操作编码"
            tooltip="例如 view / create / update / delete"
          />
          <ProFormText
            name="action_title"
            label="操作标题"
            tooltip="例如 查看菜单"
          />
        </ProFormGroup>
        <ProFormText name="description" label="描述" />
        <ProFormDigit
          name="sort"
          label="排序"
          min={0}
          fieldProps={{ precision: 0 }}
        />
        <ProFormSwitch
          disabled={Boolean(editing && !editing.capabilities?.change_status)}
          name="is_enabled"
          label="是否启用"
          tooltip="停用后停止授权，保留已有分配；重新启用后恢复。系统权限不能停用。"
        />
      </ModalForm>
    </>
  );
};

export default SystemPermissionPage;
