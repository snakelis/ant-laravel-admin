import { PlusOutlined } from '@ant-design/icons';
import {
  ActionType,
  ModalForm,
  ProColumns,
  ProFormDigit,
  ProFormSelect,
  ProFormTreeSelect,
  ProFormSwitch,
  ProFormText,
  ProTable,
} from '@ant-design/pro-components';
import { App as AntdApp, Button, Popconfirm, Space, Tag } from 'antd';
import { useMemo, useRef, useState } from 'react';
import { useFormOptions } from '../../hooks/useFormOptions';
import { filterMenus, parentMenuOptions } from '../../utils/management';
import { Can } from '../../components/Can';
import {
  componentRegistry,
  isRegisteredComponent,
  registeredComponentNames,
} from '../../layouts/componentRegistry';
import { flattenMenus, resolveIcon } from '../../layouts/menu';
import { menuApi, managementOptionsApi } from '../../services/api';
import { useManagementForm } from '../../hooks/useManagementForm';
import type { MenuItem, MenuPayload } from '../../types';
import { formatDateTime } from '../../utils/format';

interface MenuFormValues extends Record<string, unknown> {
  parent_id?: number;
  title: string;
  name?: string;
  path?: string;
  component?: string;
  icon?: string;
  permission_name?: string;
  sort?: number;
  is_visible?: boolean;
  is_enabled?: boolean;
}

const iconNames: Record<string, string> = {
  SettingOutlined: '设置',
  UserOutlined: '用户',
  TeamOutlined: '团队',
  SafetyCertificateOutlined: '权限',
  MenuOutlined: '菜单',
  AppstoreOutlined: '应用',
  LockOutlined: '安全',
};
const viewPermissionActionCodes = new Set(['view', 'list', 'read']);

const nullable = (value?: string): string | null =>
  value && value.trim() !== '' ? value.trim() : null;

const toPayload = (values: MenuFormValues): MenuPayload => ({
  parent_id: values.parent_id ?? null,
  title: values.title,
  name: nullable(values.name),
  path: nullable(values.path),
  component: nullable(values.component),
  icon: nullable(values.icon),
  permission_name: nullable(values.permission_name),
  sort: values.sort ?? 0,
  is_visible: values.is_visible ?? true,
  is_enabled: values.is_enabled ?? true,
});

export const SystemMenuPage = () => {
  const { message } = AntdApp.useApp();
  const actionRef = useRef<ActionType>(undefined);
  const [expandedRowKeys, setExpandedRowKeys] = useState<React.Key[]>([]);
  const [menuTree, setMenuTree] = useState<MenuItem[]>([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<MenuItem | null>(null);

  const options = useFormOptions(
    managementOptionsApi.menuPermissions,
    modalOpen,
  );
  const permissions = useMemo(() => options.data ?? [], [options.data]);
  const parentOptions = useMemo(
    () => parentMenuOptions(menuTree, editing?.id),
    [menuTree, editing?.id],
  );
  const pageViewPermissionOptions = useMemo(
    () =>
      permissions
        .filter((permission) =>
          viewPermissionActionCodes.has(
            permission.action_code ?? permission.name.split('.').at(-1) ?? '',
          ),
        )
        .map((permission) => ({
          label: `${permission.resource_title ?? permission.title ?? permission.name} / ${permission.action_title ?? permission.title ?? permission.name}（${permission.name}）`,
          value: permission.name,
        })),
    [permissions],
  );

  const { formRef, dirty, handleError, refreshSession, close } =
    useManagementForm<MenuFormValues>();

  const columns: ProColumns<MenuItem>[] = [
    {
      title: '关键词',
      dataIndex: 'search',
      hideInTable: true,
      fieldProps: { placeholder: '输入名称、标题或编码' },
    },
    { title: 'ID', dataIndex: 'id', width: 80, search: false },
    { title: '标题', dataIndex: 'title', search: false, ellipsis: true },
    { title: '名称', dataIndex: 'name', search: false },
    { title: '路径', dataIndex: 'path', search: false, ellipsis: true },
    { title: '组件标识', dataIndex: 'component', search: false },
    { title: '页面查看权限', dataIndex: 'permission_name', search: false },
    { title: '排序', dataIndex: 'sort', search: false, width: 80 },
    {
      title: '状态',
      dataIndex: 'is_enabled',
      search: false,
      width: 140,
      render: (_, record) => (
        <Space>
          <Tag color={record.is_enabled ? 'green' : 'red'}>
            {record.is_enabled ? '启用' : '停用'}
          </Tag>
          <Tag color={record.is_visible ? 'blue' : 'default'}>
            {record.is_visible ? '可见' : '隐藏'}
          </Tag>
        </Space>
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
          <Can permission="system.menu.update">
            <Button
              type="link"
              onClick={() => {
                setEditing(record);
                setModalOpen(true);
              }}
            >
              编辑
            </Button>
          </Can>
          <Can permission="system.menu.delete">
            <Popconfirm
              title={`确认删除“${record.title}”？`}
              description={
                record.children?.length
                  ? `删除后，${record.children.length} 个直接子菜单将成为顶级菜单。`
                  : '删除后无法恢复。'
              }
              onConfirm={async () => {
                try {
                  await menuApi.remove(record.id);
                  message.success('删除成功。');
                  void refreshSession();
                  void actionRef.current?.reload();
                } catch (error) {
                  handleError(error);
                }
              }}
            >
              <Button danger type="link">
                删除
              </Button>
            </Popconfirm>
          </Can>
        </Space>
      ),
    },
  ];

  return (
    <>
      <ProTable<MenuItem, { search?: string }>
        rowKey="id"
        scroll={{ x: 1000 }}
        onRequestError={() =>
          message.error('列表加载失败，请点击表格刷新按钮重试。')
        }
        actionRef={actionRef}
        columns={columns}
        request={async (params) => {
          const tree = await menuApi.tree(false);
          setMenuTree(tree);
          if (params.search)
            setExpandedRowKeys(flattenMenus(tree).map((menu) => menu.id));
          return {
            data: filterMenus(tree, params.search ?? ''),
            success: true,
          };
        }}
        expandable={{
          expandedRowKeys,
          onExpandedRowsChange: (keys) => setExpandedRowKeys([...keys]),
        }}
        search={{ labelWidth: 'auto' }}
        pagination={false}
        toolBarRender={() => [
          <Button
            key="expand"
            onClick={() =>
              setExpandedRowKeys(flattenMenus(menuTree).map((menu) => menu.id))
            }
          >
            展开全部
          </Button>,
          <Button key="collapse" onClick={() => setExpandedRowKeys([])}>
            收起全部
          </Button>,
          <Can key="create" permission="system.menu.create">
            <Button
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => {
                setEditing(null);
                setModalOpen(true);
              }}
            >
              新建菜单
            </Button>
          </Can>,
        ]}
      />
      <ModalForm<MenuFormValues>
        key={editing?.id ?? 'create'}
        title={editing ? '编辑菜单' : '新建菜单'}
        open={modalOpen}
        formRef={formRef}
        onValuesChange={() => {
          dirty.current = true;
        }}
        modalProps={{ destroyOnHidden: true, maskClosable: false }}
        initialValues={
          editing
            ? {
                parent_id: editing.parent_id ?? undefined,
                title: editing.title,
                name: editing.name ?? undefined,
                path: editing.path ?? undefined,
                component: editing.component ?? undefined,
                icon: editing.icon ?? undefined,
                permission_name: editing.permission_name ?? undefined,
                sort: editing.sort,
                is_visible: editing.is_visible,
                is_enabled: editing.is_enabled,
              }
            : { sort: 0, is_visible: true, is_enabled: true }
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
              await menuApi.update(editing.id, toPayload(values));
            } else {
              await menuApi.create(toPayload(values));
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
        <ProFormTreeSelect
          name="parent_id"
          label="上级菜单"
          placeholder="留空表示顶级菜单"
          allowClear
          fieldProps={{
            treeData: parentOptions,
            treeDefaultExpandAll: true,
            showSearch: true,
            treeNodeFilterProp: 'title',
          }}
        />
        <ProFormText
          name="title"
          label="标题"
          rules={[{ required: true, message: '请输入标题' }]}
        />
        <ProFormText
          name="name"
          label="名称"
          tooltip="稳定菜单名称，例如 system.users"
        />
        <ProFormText name="path" label="路径" tooltip="例如 /system/users" />
        <ProFormSelect
          name="component"
          label="菜单类型 / 页面"
          rules={[{ required: true, message: '请选择目录或页面' }]}
          fieldProps={{
            onChange: (value) => {
              if (typeof value === 'string' && isRegisteredComponent(value)) {
                const page = componentRegistry[value];
                formRef.current?.setFieldsValue({
                  path: page.routePath,
                  permission_name:
                    'permission' in page ? page.permission : undefined,
                });
              } else {
                formRef.current?.setFieldsValue({
                  path: undefined,
                  permission_name: undefined,
                });
              }
            },
          }}
          options={[
            { label: '目录（包含子菜单）', value: 'RouteView' },
            ...registeredComponentNames.map((name) => ({
              label: `页面 · ${componentRegistry[name].label}`,
              value: name,
            })),
          ]}
        />
        <ProFormSelect
          name="icon"
          label="图标标识"
          allowClear
          options={Object.entries(iconNames).map(([name, label]) => ({
            label: (
              <Space>
                {resolveIcon(name)}
                {label}
              </Space>
            ),
            value: name,
          }))}
        />
        <ProFormSelect
          name="permission_name"
          label="页面查看权限"
          tooltip="菜单仅绑定页面查看权限用于导航展示；后端授权仍以接口中间件和 Policy 为准。"
          allowClear
          showSearch
          fieldProps={{ loading: options.loading, optionFilterProp: 'label' }}
          options={pageViewPermissionOptions}
        />
        <ProFormDigit
          name="sort"
          label="排序"
          min={0}
          fieldProps={{ precision: 0 }}
        />
        <ProFormSwitch
          name="is_visible"
          label="导航中显示"
          extra="隐藏仅影响导航展示，不撤销页面和接口访问权限。"
        />
        <ProFormSwitch
          name="is_enabled"
          label="启用菜单"
          extra="停用后，该菜单及其下级不在导航中显示；授权请在角色权限中调整。"
        />
      </ModalForm>
    </>
  );
};

export default SystemMenuPage;
