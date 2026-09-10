import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';
import ts from 'typescript';

function load(source, imports = {}) {
  const module = { exports: {} };
  runInNewContext(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText, { module, exports: module.exports, require: (name) => {
    if (!(name in imports)) throw new Error(`未预期导入 ${name}`);
    return imports[name];
  } });
  return module.exports;
}
const read = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const extensions = load(read('src/utils/extensions.ts'));

test('业务注册不能覆盖框架或注入原型属性', () => {
  assert.equal(extensions.mergeRegistrations({ system: 1 }, { business: 2 }).business, 2);
  for (const business of [{ system: 2 }, JSON.parse('{"__proto__": {}}'), { constructor: 1 }]) {
    assert.throws(() => extensions.mergeRegistrations({ system: 1 }, business), /冲突/);
  }
});

test('真实组件注册入口支持业务页面，并拒绝继承的对象属性', () => {
  const registry = load(read('src/layouts/componentRegistry.tsx'), {
    '../business/components': { businessComponents: { BusinessProbe: { label: '测试', routePath: '/business/probe', permission: 'business.probe.view' } } },
    '../utils/extensions': extensions,
  });
  assert.equal(registry.isRegisteredComponent('BusinessProbe'), true);
  assert.equal(registry.isRegisteredComponent('SystemUserList'), true);
  assert.equal(registry.isRegisteredComponent('toString'), false);
  assert.equal(registry.isRegisteredComponent('__proto__'), false);
});

test('真实 access 入口按权限控制业务页面且保留系统页面规则', () => {
  const access = load(read('src/access.ts'), {
    './business/access': { businessAccessPermissions: { canViewBusinessProbe: 'business.probe.view' } },
    './utils/extensions': extensions,
  }).default;
  assert.equal(access({ currentUser: {}, permissions: [] }).canViewBusinessProbe, false);
  assert.equal(access({ currentUser: {}, permissions: ['business.probe.view'] }).canViewBusinessProbe, true);
  assert.equal(access({ currentUser: {}, permissions: ['business.probe.view'] }).canViewSystemUsers, false);
});

test('业务路由位于通配兜底路由之前', () => {
  const routes = load(read('config/routes.ts'), { './businessRoutes': { __esModule: true, default: [{ path: '/business/probe', component: './business/Probe', access: 'canViewBusinessProbe' }] } }).default;
  assert(routes.findIndex((route) => route.path === '/business/probe') < routes.findIndex((route) => route.path === '*'));
});
