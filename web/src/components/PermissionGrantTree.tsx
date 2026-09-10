import {
  Button,
  Input,
  Spin,
  Card,
  Checkbox,
  Empty,
  Flex,
  Space,
  Tag,
  Tree,
  Typography,
} from 'antd';
import type { CheckboxChangeEvent } from 'antd/es/checkbox';
import type { TreeDataNode } from 'antd';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import {
  filterPermissionTree,
  mergeVisibleSelection,
} from '../utils/management';
import type { PermissionTreeNode } from '../types';

const { Text } = Typography;

interface PermissionGrantTreeProps {
  tree: PermissionTreeNode[];
  value?: number[];
  onChange?: (value: number[]) => void;
  editablePermissionIds?: number[];
  loading?: boolean;
  originalIds?: number[];
}

interface PermissionNodeData extends TreeDataNode {
  key: string;
  title: ReactNode;
  children?: PermissionNodeData[];
  selectable?: boolean;
  disabled?: boolean;
  permissionId?: number;
}

const collectPermissionIds = (nodes: PermissionTreeNode[]): number[] =>
  nodes.flatMap((node) => {
    if (node.type === 'permission' && typeof node.id === 'number') {
      return [node.id];
    }

    return collectPermissionIds(node.children ?? []);
  });

const hasEditablePermissionChildren = (
  node: PermissionTreeNode,
  editablePermissionIds: Set<number>,
): boolean =>
  collectPermissionIds(node.children ?? []).some((id) =>
    editablePermissionIds.has(id),
  );

const permissionTitle = (node: PermissionTreeNode, editable: boolean) => (
  <Space size={6} wrap>
    <span>{node.action_title ?? node.title}</span>
    <Text type="secondary">{node.name}</Text>
    {node.is_system ? <Tag color="blue">系统</Tag> : null}
    {node.is_enabled === false ? <Tag color="red">停用</Tag> : null}
    {!editable ? <Tag>不可编辑</Tag> : null}
  </Space>
);

const groupTitle = (node: PermissionTreeNode) => (
  <Space size={6} wrap>
    <span>{node.title}</span>
    <Text type="secondary">{node.key}</Text>
  </Space>
);

const toTreeData = (
  nodes: PermissionTreeNode[],
  editablePermissionIds: Set<number>,
): PermissionNodeData[] =>
  nodes.map((node) => {
    if (node.type === 'permission') {
      const editable =
        typeof node.id === 'number' && editablePermissionIds.has(node.id);

      return {
        key: node.key,
        title: permissionTitle(node, editable),
        selectable: false,
        disabled: !editable,
        permissionId: node.id,
      };
    }

    return {
      key: node.key,
      title: groupTitle(node),
      selectable: false,
      disableCheckbox: !hasEditablePermissionChildren(
        node,
        editablePermissionIds,
      ),
      children: toTreeData(node.children ?? [], editablePermissionIds),
    };
  });

export const PermissionGrantTree = ({
  tree,
  value = [],
  onChange,
  editablePermissionIds,
  loading,
  originalIds = [],
}: PermissionGrantTreeProps) => {
  const [keyword, setKeyword] = useState('');
  const [selectedOnly, setSelectedOnly] = useState(false);
  const [expandedKeys, setExpandedKeys] = useState<React.Key[] | undefined>();
  const visibleTree = filterPermissionTree(
    tree,
    keyword,
    selectedOnly ? new Set(value) : undefined,
  );
  const visibleEditableIds = collectPermissionIds(visibleTree).filter(
    (id) => !editablePermissionIds || editablePermissionIds.includes(id),
  );
  const allPermissionIds = useMemo(() => collectPermissionIds(tree), [tree]);
  const editablePermissionIdSet = useMemo(
    () => new Set(editablePermissionIds ?? allPermissionIds),
    [allPermissionIds, editablePermissionIds],
  );
  const treeData = toTreeData(visibleTree, editablePermissionIdSet);
  const editableTreePermissionIds = useMemo(
    () => allPermissionIds.filter((id) => editablePermissionIdSet.has(id)),
    [allPermissionIds, editablePermissionIdSet],
  );
  const keyToPermissionId = useMemo(() => {
    const pairs: Array<[string, number]> = [];
    const walk = (nodes: PermissionTreeNode[]) => {
      nodes.forEach((node) => {
        if (node.type === 'permission' && typeof node.id === 'number') {
          pairs.push([node.key, node.id]);
        }
        walk(node.children ?? []);
      });
    };

    walk(tree);
    return new Map(pairs);
  }, [tree]);
  const idToKey = useMemo(
    () =>
      new Map([...keyToPermissionId.entries()].map(([key, id]) => [id, key])),
    [keyToPermissionId],
  );
  const selectedEditableIds = value.filter((id) =>
    editablePermissionIdSet.has(id),
  );
  const selectedTreeKeys = value
    .map((id) => idToKey.get(id))
    .filter((key): key is string => Boolean(key));

  const updateEditableSelection = (nextEditableIds: number[]) => {
    onChange?.(
      mergeVisibleSelection(value, visibleEditableIds, nextEditableIds),
    );
  };

  const handleCheck = (keys: unknown) => {
    const rawKeys = Array.isArray(keys)
      ? keys
      : typeof keys === 'object' && keys !== null && 'checked' in keys
        ? keys.checked
        : [];
    const nextEditableIds = (Array.isArray(rawKeys) ? rawKeys : [])
      .map((key) => keyToPermissionId.get(String(key)))
      .filter(
        (id): id is number =>
          typeof id === 'number' && editablePermissionIdSet.has(id),
      );
    updateEditableSelection(nextEditableIds);
  };

  const handleCheckAll = (event: CheckboxChangeEvent) => {
    updateEditableSelection(event.target.checked ? visibleEditableIds : []);
  };

  if (!loading && tree.length === 0) {
    return <Empty description="暂无可授权权限" />;
  }

  return (
    <Spin spinning={Boolean(loading)}>
      <Card
        size="small"
        styles={{ body: { maxHeight: 420, overflow: 'auto' } }}
      >
        <Space wrap style={{ marginBottom: 12 }}>
          <Input.Search
            placeholder="搜索模块、资源或权限"
            allowClear
            value={keyword}
            onChange={(event) => {
              setKeyword(event.target.value);
              setExpandedKeys(undefined);
            }}
          />
          <Checkbox
            checked={selectedOnly}
            onChange={(event) => {
              setSelectedOnly(event.target.checked);
              setExpandedKeys(undefined);
            }}
          >
            仅看已选
          </Checkbox>
          <Button size="small" onClick={() => setExpandedKeys(undefined)}>
            展开
          </Button>
          <Button size="small" onClick={() => setExpandedKeys([])}>
            收起
          </Button>
        </Space>
        <Flex
          justify="space-between"
          align="center"
          style={{ marginBottom: 12 }}
        >
          <Checkbox
            checked={
              visibleEditableIds.length > 0 &&
              visibleEditableIds.every((id) => value.includes(id))
            }
            indeterminate={
              visibleEditableIds.some((id) => value.includes(id)) &&
              !visibleEditableIds.every((id) => value.includes(id))
            }
            onChange={handleCheckAll}
          >
            全选当前可编辑权限
          </Checkbox>
          <Text type="secondary">
            已选 {selectedEditableIds.length} /{' '}
            {editableTreePermissionIds.length}
          </Text>
        </Flex>
        <Text type="secondary">
          新增 {value.filter((id) => !originalIds.includes(id)).length} 项 ·
          移除 {originalIds.filter((id) => !value.includes(id)).length} 项
        </Text>
        <Tree
          key={`${keyword}:${selectedOnly}:${expandedKeys === undefined}`}
          expandedKeys={expandedKeys}
          onExpand={(keys) => setExpandedKeys(keys)}
          checkable
          blockNode
          defaultExpandAll
          checkedKeys={selectedTreeKeys}
          treeData={treeData}
          onCheck={handleCheck}
        />
      </Card>
    </Spin>
  );
};
