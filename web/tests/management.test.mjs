import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';
import ts from 'typescript';

const module = { exports: {} };
runInNewContext(ts.transpileModule(readFileSync(new URL('../src/utils/management.ts', import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText, { module, exports: module.exports });
const { filterMenus, parentMenuOptions, filterPermissionTree, mergeVisibleSelection } = module.exports;
const plain = (value) => JSON.parse(JSON.stringify(value));

test('菜单搜索保留上级，匹配目录时保留下级，不修改原树', () => {
  const menus = [{ id: 1, title: '系统', children: [{ id: 2, title: '用户' }, { id: 3, title: '菜单' }] }];
  assert.deepEqual(plain(filterMenus(menus, '用户')), [{ id: 1, title: '系统', children: [{ id: 2, title: '用户' }] }]);
  assert.equal(filterMenus(menus, '系统')[0].children.length, 2);
  assert.equal(menus[0].children.length, 2);
  assert.equal(filterMenus(menus, '不存在').length, 0);
});

test('父菜单排除目标及其全部后代，保留兄弟节点', () => {
  const menus = [{ id: 1, title: '根', children: [{ id: 2, title: '目标', children: [{ id: 3, title: '后代' }] }, { id: 4, title: '同级' }] }];
  assert.deepEqual(plain(parentMenuOptions(menus, 2)), [{ value: 1, title: '根', children: [{ value: 4, title: '同级', children: [] }] }]);
});

test('筛选授权树保留分组，过滤操作不会删除隐藏或只读授权', () => {
  const tree = [{ key: 'system', type: 'module', title: '系统', children: [{ key: 'view', type: 'permission', id: 1, title: '查看' }, { key: 'edit', type: 'permission', id: 2, title: '编辑' }] }];
  assert.equal(filterPermissionTree(tree, '查看')[0].children[0].id, 1);
  assert.equal(filterPermissionTree(tree, '系统', new Set([2]))[0].children[0].id, 2);
  assert.deepEqual(plain(mergeVisibleSelection([1, 2, 99], [1], [])), [2, 99]);
  assert.deepEqual(plain(mergeVisibleSelection([2, 99], [1], [1])), [2, 99, 1]);
});
