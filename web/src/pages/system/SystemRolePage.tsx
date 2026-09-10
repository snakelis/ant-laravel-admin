import { PlusOutlined } from '@ant-design/icons';
import {
  ActionType,
  ModalForm,
  ProColumns,
  ProFormText,
  ProTable,
} from '@ant-design/pro-components';
import { useModel } from '@umijs/max';
import {
  App as AntdApp,
  Button,
  Drawer,
  Form,
  Tooltip,
  Popconfirm,
  Space,
  Tag,
  Typography,
} from 'antd';
import { useMemo, useRef, useState } from 'react';
import { useFormOptions } from '../../hooks/useFormOptions';
import { Can } from '../../components/Can';
import { PermissionGrantTree } from '../../components/PermissionGrantTree';
import { managementOptionsApi, roleApi } from '../../services/api';
import { useManagementForm } from '../../hooks/useManagementForm';
import type {
  Permission,
  PermissionTreeNode,
  Role,
  RolePayload,
} from '../../types';
import { formatDateTime } from '../../utils/format';

const { Text } = Typography;

interface RoleFormValues extends Record<string, unknown> {
  name: string;
  permission_ids?: number[];
}

const toPayload = (values: RoleFormValues): RolePayload => ({
  name: values.name,
  permission_ids: values.permission_ids ?? [],
});

const permissionLabel = (permission: Permission): string =>
  permission.title || permission.action_title || permission.name;

const mergePermissionIds = (
  selectedIds: number[],
  preservedIds: number[],
): number[] =>
  [...new Set([...selectedIds, ...preservedIds])].sort(
    (left, right) => left - right,
  );

const collectEditablePermissionIds = (
  nodes: PermissionTreeNode[],
  actorPermissionNames: Set<string>,
  canGrantAll: boolean,
): number[] =>
  nodes.flatMap((node) => {
    if (node.type === 'permission' && typeof node.id === 'number') {
      return node.is_enabled !== false &&
        (canGrantAll ||
          Boolean(node.name && actorPermissionNames.has(node.name)))
        ? [node.id]
        : [];
    }

    return collectEditablePermissionIds(
      node.children ?? [],
      actorPermissionNames,
      canGrantAll,
    );
  });

const indexPermissionTree = (
  nodes: PermissionTreeNode[],
): Map<number, PermissionTreeNode> => {
  const map = new Map<number, PermissionTreeNode>();
  const walk = (items: PermissionTreeNode[]) => {
    items.forEach((item) => {
      if (item.type === 'permission' && typeof item.id === 'number') {
        map.set(item.id, item);
      }
      walk(item.children ?? []);
    });
  };

  walk(nodes);
  return map;
};

export const SystemRolePage = () => {
  const { message } = AntdApp.useApp();
  const { initialState } = useModel('@@initialState');
  const actionRef = useRef<ActionType>(undefined);
  const [details, setDetails] = useState<Role | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Role | null>(null);

  const options = useFormOptions(
    managementOptionsApi.permissionTree,
    modalOpen,
  );
  const permissionTree = useMemo(() => options.data ?? [], [options.data]);
  const permissionsLoading = options.loading;
  const permissionNodeById = useMemo(
    () => indexPermissionTree(permissionTree),
    [permissionTree],
  );
  const canGrantAll = Boolean(
    initialState?.permissions.includes('system.permission.grant-all'),
  );
  const actorPermissionNames = useMemo(
    () => new Set(initialState?.permissions ?? []),
    [initialState?.permissions],
  );
  const editablePermissionIds = useMemo(
    () =>
      collectEditablePermissionIds(
        permissionTree,
        actorPermissionNames,
        canGrantAll,
      ),
    [actorPermissionNames, canGrantAll, permissionTree],
  );
  const editablePermissionIdSet = useMemo(
    () => new Set(editablePermissionIds),
    [editablePermissionIds],
  );
  const preservedPermissions = useMemo(
    () =>
      (editing?.permissions ?? []).filter(
        (permission) => !editablePermissionIdSet.has(permission.id),
      ),
    [editablePermissionIdSet, editing],
  );
  const preservedPermissionIds = useMemo(
    () => preservedPermissions.map((permission) => permission.id),
    [preservedPermissions],
  );
  const preservedTreePermissionCount = preservedPermissions.filter(
    (permission) => permissionNodeById.has(permission.id),
  ).length;
  const preservedOutsideTreePermissionCount =
    preservedPermissions.length - preservedTreePermissionCount;

  const { formRef, dirty, handleError, refreshSession, close } =
    useManagementForm<RoleFormValues>();

  const columns: ProColumns<Role>[] = [
    {
      title: '关键词',
      dataIndex: 'search',
      hideInTable: true,
      fieldProps: { placeholder: '输入名称、标题或编码' },
    },
    { title: 'ID', dataIndex: 'id', width: 80, search: false },
    { title: '角色名称', dataIndex: 'name', search: false, ellipsis: true },
    {
      title: '权限',
      dataIndex: 'permissions',
      search: false,
      render: (_, record) => {
        const granted = record.permissions ?? [];
        if (granted.length === 0) {
          return <Text type="secondary">未授权</Text>;
        }

        return (
          <Button type="link" onClick={() => setDetails(record)}>
            {granted.length} 项权限 · 查看全部
          </Button>
        );
      },
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
          <Can permission="system.role.update">
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
          <Can permission="system.role.delete">
            <Tooltip title={record.capabilities?.delete_reason}>
              <Popconfirm
                disabled={record.capabilities?.delete === false}
                title={`确认删除“${record.name}”？`}
                onConfirm={async () => {
                  try {
                    await roleApi.remove(record.id);
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
      <ProTable<Role, { search?: string }>
        rowKey="id"
        scroll={{ x: 1000 }}
        onRequestError={() =>
          message.error('列表加载失败，请点击表格刷新按钮重试。')
        }
        actionRef={actionRef}
        columns={columns}
        request={(params) =>
          roleApi.list({
            current: params.current,
            pageSize: params.pageSize,
            search: params.search,
          })
        }
        search={{ labelWidth: 'auto' }}
        pagination={{ defaultPageSize: 15 }}
        toolBarRender={() => [
          <Can key="create" permission="system.role.create">
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => {
                setEditing(null);
                setModalOpen(true);
              }}
            >
              新建角色
            </Button>
          </Can>,
        ]}
      />
      <Drawer
        title={`${details?.name ?? ''} · 授权详情`}
        open={Boolean(details)}
        onClose={() => setDetails(null)}
        size="large"
      >
        <Space wrap>
          {details?.permissions?.map((permission) => (
            <Tag key={permission.id}>
              {permissionLabel(permission)} · {permission.name}
              {permission.is_enabled === false ? '（停用）' : ''}
            </Tag>
          ))}
        </Space>
      </Drawer>
      <ModalForm<RoleFormValues>
        key={editing?.id ?? 'create'}
        title={editing ? '编辑角色' : '新建角色'}
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
                permission_ids: editing.permissions?.map(
                  (permission) => permission.id,
                ),
              }
            : { permission_ids: [] }
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
              await roleApi.update(editing.id, {
                ...toPayload(values),
                permission_ids: mergePermissionIds(
                  values.permission_ids ?? [],
                  preservedPermissionIds,
                ),
              });
            } else {
              await roleApi.create(toPayload(values));
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
          label="角色名称"
          rules={[{ required: true, message: '请输入角色名称' }]}
        />
        <Form.Item
          name="permission_ids"
          label="授权权限"
          tooltip="权限独立于菜单，按模块 / 资源 / 操作分组；提交时仍保存 permission_ids。"
        >
          <PermissionGrantTree
            tree={permissionTree}
            originalIds={
              editing?.permissions?.map((permission) => permission.id) ?? []
            }
            editablePermissionIds={editablePermissionIds}
            loading={permissionsLoading}
          />
        </Form.Item>
        {editing && preservedPermissions.length > 0 ? (
          <Text type="secondary">
            当前角色有 {preservedPermissions.length} 项权限为只读保留
            {preservedTreePermissionCount > 0
              ? `（树中不可编辑 ${preservedTreePermissionCount} 项`
              : '（'}
            {preservedTreePermissionCount > 0 &&
            preservedOutsideTreePermissionCount > 0
              ? '，'
              : ''}
            {preservedOutsideTreePermissionCount > 0
              ? `树外 ${preservedOutsideTreePermissionCount} 项`
              : ''}
            ）。修改其他权限并保存时，这些既有权限不会被删除；后端授权校验仍是最终安全边界。
          </Text>
        ) : null}
      </ModalForm>
    </>
  );
};

export default SystemRolePage;
