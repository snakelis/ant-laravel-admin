#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

fail() {
  printf 'FAIL: %s\n' "$*" >&2
  exit 1
}

pass() {
  printf 'PASS: %s\n' "$*"
}

warn() {
  printf 'WARN: %s\n' "$*" >&2
}

require_file() {
  [[ -f "$1" ]] || fail "缺少必需文件：$1"
  pass "存在 $1"
}

forbid_file() {
  [[ ! -e "$1" ]] || fail "存在禁止文件/目录：$1"
  pass "不存在 $1"
}

require_file api/composer.lock
require_file web/pnpm-lock.yaml
require_file web/config/config.ts
require_file web/config/routes.ts
forbid_file web/vite.config.ts
forbid_file web/index.html
forbid_file web/src/main.tsx
forbid_file package-lock.json
forbid_file yarn.lock
forbid_file bun.lock
forbid_file bun.lockb
forbid_file npm-shrinkwrap.json

node <<'NODE'
const fs = require('node:fs');
const pkg = JSON.parse(fs.readFileSync('web/package.json', 'utf8'));
const deps = { ...(pkg.dependencies || {}), ...(pkg.devDependencies || {}) };
const scripts = pkg.scripts || {};
const mustHave = ['@umijs/max', '@ant-design/pro-components', 'antd', 'react', 'react-dom', 'typescript'];
for (const name of mustHave) {
  if (!deps[name]) throw new Error(`web/package.json 缺少 ${name}`);
}
if (!/^max dev\b/.test(String(scripts.dev || '').replace(/^(?:[A-Z_][A-Z0-9_]*=[^\s]+\s+)+/, ''))) {
  throw new Error('web/package.json 的 dev 脚本必须使用 max dev');
}
if (!/^max build\b/.test(String(scripts.build || ''))) {
  throw new Error('web/package.json 的 build 脚本必须使用 max build');
}
for (const forbidden of ['vite', '@vitejs/plugin-react', 'react-router-dom']) {
  if (deps[forbidden]) throw new Error(`web/package.json 不得直接依赖 ${forbidden}`);
}
NODE
pass 'web/package.json 使用 Umi Max/Ant Design Pro 依赖和 max 脚本，且无 Vite/BrowserRouter 直接依赖'

node <<'NODE'
const fs = require('node:fs');
const composer = JSON.parse(fs.readFileSync('api/composer.json', 'utf8'));
const lock = JSON.parse(fs.readFileSync('api/composer.lock', 'utf8'));
const deps = { ...(composer.require || {}), ...(composer['require-dev'] || {}) };
const required = {
  php: '~8.3.36 || ~8.4.27 || ~8.5.12',
  'laravel/framework': '^13.8',
  'laravel/sanctum': '^4.0',
  'spatie/laravel-permission': '^8.3',
  'zircote/swagger-php': '^6.5',
  'laravel/telescope': '^5.22',
};
for (const [name, version] of Object.entries(required)) {
  if (deps[name] !== version) throw new Error(`api/composer.json ${name} 版本约束应为 ${version}，实际为 ${deps[name] || '(缺失)'}`);
}
if (lock.platform?.php !== composer.require.php) {
  throw new Error('api/composer.lock 的 PHP 平台约束必须与 composer.json 一致');
}
if (composer.config?.platform?.php !== undefined || lock['platform-overrides']?.php !== undefined) {
  throw new Error('项目不得通过 config.platform.php 模拟 PHP 版本');
}
NODE
pass 'Composer 清单与锁文件固定 PHP 各分支安全补丁、Laravel 13、Sanctum、RBAC、OpenAPI 和 Telescope 基线'

composer --working-dir=api validate --strict --no-check-publish --no-interaction
pass 'Composer 清单及锁文件校验通过'

node <<'NODE'
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const constraint = JSON.parse(fs.readFileSync('api/composer.json', 'utf8')).require.php;
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'foundation-php-'));
const accepted = ['8.3.36', '8.3.37', '8.4.27', '8.4.28', '8.5.12', '8.5.13'];
const rejected = ['8.2.35', '8.3.0', '8.3.6', '8.3.35', '8.4.0', '8.4.26', '8.5.0', '8.5.11', '8.6.0', '9.0.0'];
try {
  for (const [versions, expected] of [[accepted, true], [rejected, false]]) {
    for (const version of versions) {
      // 独立临时项目只声明 PHP 要求，不联网、不安装依赖，也不修改应用的配置或 vendor。
      fs.writeFileSync(path.join(directory, 'composer.json'), JSON.stringify({
        name: 'foundation/php-platform-check',
        require: { php: constraint },
        config: { platform: { php: version } },
        repositories: [{ 'packagist.org': false }],
      }));
      const result = spawnSync('composer', [
        'update', '--no-install', '--no-scripts', '--no-plugins', '--no-audit', '--no-interaction', '--no-progress',
      ], {
        cwd: directory,
        encoding: 'utf8',
        timeout: 30_000,
        env: {
          ...process.env,
          COMPOSER: path.join(directory, 'composer.json'),
          COMPOSER_HOME: directory,
          COMPOSER_CACHE_DIR: path.join(directory, 'cache'),
          COMPOSER_AUTH: '{}',
          COMPOSER_DISABLE_NETWORK: '1',
          COMPOSER_IGNORE_PLATFORM_REQ: '',
          COMPOSER_IGNORE_PLATFORM_REQS: '0',
        },
      });
      if (result.error) throw result.error;
      const output = `${result.stdout}${result.stderr}`;
      if (expected ? result.status !== 0 : result.status !== 2 || !output.includes('requires php')) {
        throw new Error(`PHP ${version} 应${expected ? '接受' : '拒绝'}，Composer 退出码 ${result.status}\n${output}`);
      }
    }
  }
} finally {
  fs.rmSync(directory, { recursive: true, force: true });
}
NODE
pass 'Composer 实际解析接受 6 个修复/后续补丁版本，拒绝 10 个旧补丁或未支持分支版本（离线、不安装依赖）'

router_matches="$(git grep -nE '\b(BrowserRouter|react-router-dom)\b' -- 'web/src' 'web/config' || true)"
if [[ -n "$router_matches" ]]; then
  printf '%s\n' "$router_matches" >&2
  fail '前端源码仍包含 BrowserRouter 或 react-router-dom'
fi
pass '前端源码未使用 BrowserRouter/react-router-dom 主路由体系'

storage_matches="$(git grep -nE '\blocalStorage\b' -- 'web/src' 'web/config' || true)"
if [[ -n "$storage_matches" ]]; then
  printf '%s\n' "$storage_matches" >&2
  fail '前端源码不得使用 localStorage 保存认证状态或长期令牌'
fi
pass '前端源码未使用 localStorage'

dynamic_import_matches="$(git grep -nE 'import\s*\(' -- 'web/src' 'web/config' || true)"
if [[ -n "$dynamic_import_matches" ]]; then
  printf '%s\n' "$dynamic_import_matches" >&2
  fail '前端源码不得基于后端返回值执行任意动态 import()'
fi
pass '前端源码未使用动态 import() 解析后端组件路径'

node <<'NODE'
const fs = require('node:fs');
const requiredPermissionFields = [
  'title',
  'module_code',
  'module_title',
  'resource_code',
  'resource_title',
  'action_code',
  'action_title',
  'description',
  'sort',
  'is_system',
  'is_enabled',
];
const resource = fs.readFileSync('api/app/Http/Resources/Api/V1/System/PermissionResource.php', 'utf8');
for (const field of requiredPermissionFields) {
  if (!resource.includes(`'${field}'`)) throw new Error(`PermissionResource 缺少权限元数据字段 ${field}`);
}
const openapi = fs.readFileSync('api/app/OpenApi/OpenApiSpec.php', 'utf8');
for (const text of ['schema: \'PermissionTreeNode\'', 'module_code', 'resource_code', 'action_code', 'is_enabled']) {
  if (!openapi.includes(text)) throw new Error(`OpenAPI 缺少权限树或权限元数据契约证据：${text}`);
}
const builder = fs.readFileSync('api/app/Services/Rbac/PermissionTreeBuilder.php', 'utf8');
for (const text of ["'type' => 'module'", "'type' => 'resource'", "'type' => 'permission'", "'id' => $permission->id", "'name' => $permission->name", "'action_code'", "'is_enabled'"]) {
  if (!builder.includes(text)) throw new Error(`PermissionTreeBuilder 缺少授权树字段或层级证据：${text}`);
}
const types = fs.readFileSync('web/src/types.ts', 'utf8');
for (const text of ['PermissionTreeNodeType', "'module' | 'resource' | 'permission'", 'action_code?: string | null', 'is_enabled?: boolean', 'permission_ids?: number[]']) {
  if (!types.includes(text)) throw new Error(`前端类型缺少权限树/permission_ids 契约证据：${text}`);
}
const api = fs.readFileSync('web/src/services/api.ts', 'utf8');
for (const text of ['tree(params', "request<PermissionTreeNode[]>('/permissions'", 'tree: true', 'enabled_only']) {
  if (!api.includes(text)) throw new Error(`前端权限树服务调用缺少契约证据：${text}`);
}
NODE
pass '权限元数据、授权树 API、前端类型和 role permission_ids 契约存在'

node <<'NODE'
const fs = require('node:fs');
const routes = fs.readFileSync('api/routes/api.php', 'utf8');
const requiredMiddleware = [
  'can:system.user.view',
  'can:system.user.create',
  'can:system.user.update',
  'can:system.user.delete',
  'can:system.role.view',
  'can:system.role.create',
  'can:system.role.update',
  'can:system.role.delete',
  'can:system.permission.view',
  'can:system.permission.create',
  'can:system.permission.update',
  'can:system.permission.delete',
  'can:system.menu.view',
  'can:system.menu.create',
  'can:system.menu.update',
  'can:system.menu.delete',
  'can:view,user',
  'can:update,user',
  'can:delete,user',
  'can:view,role',
  'can:update,role',
  'can:delete,role',
  'can:view,permission',
  'can:update,permission',
  'can:delete,permission',
  'can:view,menu',
  'can:update,menu',
  'can:delete,menu',
];
for (const text of requiredMiddleware) {
  if (!routes.includes(text)) throw new Error(`后端路由缺少 can 中间件授权证据：${text}`);
}
if (routes.includes('permission_name')) throw new Error('api/routes/api.php 不得使用菜单 permission_name 作为接口授权依据');
const grant = fs.readFileSync('api/app/Services/Rbac/GrantAuthorization.php', 'utf8');
for (const text of ['system.permission.grant-all', 'Cannot grant permissions that the current user does not have', 'Cannot assign roles containing permissions']) {
  if (!grant.includes(text)) throw new Error(`缺少越权授予约束证据：${text}`);
}
NODE
pass '后端接口授权使用 can/Policy 链路，且包含越权授予约束'

node <<'NODE'
const fs = require('node:fs');
const expected = [
  ['SystemUserList', '/system/users', 'canViewSystemUsers', 'system.user.view'],
  ['SystemRoleList', '/system/roles', 'canViewSystemRoles', 'system.role.view'],
  ['SystemPermissionList', '/system/permissions', 'canViewSystemPermissions', 'system.permission.view'],
  ['SystemMenuList', '/system/menus', 'canViewSystemMenus', 'system.menu.view'],
];
const registry = fs.readFileSync('web/src/layouts/componentRegistry.tsx', 'utf8');
const access = fs.readFileSync('web/src/access.ts', 'utf8');
const routes = fs.readFileSync('web/config/routes.ts', 'utf8');
const menuSeeder = fs.readFileSync('api/database/seeders/Foundation/MenuSeeder.php', 'utf8');
for (const [component, path, accessName, permission] of expected) {
  if (!registry.includes(component) || !registry.includes(`routePath: '${path}'`) || !registry.includes(`permission: '${permission}'`)) {
    throw new Error(`组件注册表缺少 ${component} / ${path} / ${permission} 一致性证据`);
  }
  if (!routes.includes(`path: '${path}'`) || !routes.includes(`access: '${accessName}'`)) {
    throw new Error(`Umi route 缺少 ${path} / ${accessName} 一致性证据`);
  }
  if (!access.includes(`${accessName}: hasPermission(initialState, '${permission}')`)) {
    throw new Error(`web/src/access.ts 缺少 ${accessName} -> ${permission} 权限映射`);
  }
  if (!menuSeeder.includes(`'path' => '${path}'`) || !menuSeeder.includes(`'component' => '${component}'`) || !menuSeeder.includes(`'permission_name' => '${permission}'`)) {
    throw new Error(`菜单种子缺少 ${path} / ${component} / ${permission} 一致性证据`);
  }
}
const menuPage = fs.readFileSync('web/src/pages/system/SystemMenuPage.tsx', 'utf8');
for (const text of ['页面查看权限', 'viewPermissionActionCodes', "new Set(['view', 'list', 'read'])", '菜单仅绑定页面查看权限用于导航展示']) {
  if (!menuPage.includes(text)) throw new Error(`菜单管理页缺少查看权限约束说明或过滤证据：${text}`);
}
for (const [file, permission] of [
  ['web/src/pages/system/SystemUserPage.tsx', 'system.user.create'],
  ['web/src/pages/system/SystemUserPage.tsx', 'system.user.update'],
  ['web/src/pages/system/SystemUserPage.tsx', 'system.user.delete'],
  ['web/src/pages/system/SystemRolePage.tsx', 'system.role.create'],
  ['web/src/pages/system/SystemRolePage.tsx', 'system.role.update'],
  ['web/src/pages/system/SystemRolePage.tsx', 'system.role.delete'],
  ['web/src/pages/system/SystemPermissionPage.tsx', 'system.permission.create'],
  ['web/src/pages/system/SystemPermissionPage.tsx', 'system.permission.update'],
  ['web/src/pages/system/SystemPermissionPage.tsx', 'system.permission.delete'],
  ['web/src/pages/system/SystemMenuPage.tsx', 'system.menu.create'],
  ['web/src/pages/system/SystemMenuPage.tsx', 'system.menu.update'],
  ['web/src/pages/system/SystemMenuPage.tsx', 'system.menu.delete'],
]) {
  const source = fs.readFileSync(file, 'utf8');
  if (!source.includes(`permission="${permission}"`)) throw new Error(`${file} 缺少按钮权限控制 ${permission}`);
}
NODE
pass 'view 权限与菜单/路由/组件注册表一致，操作权限控制按钮显示'

node <<'NODE'
const fs = require('node:fs');
for (const file of ['api/routes/business.php', 'api/app/Providers/BusinessServiceProvider.php', 'api/database/seeders/Business/BusinessSeeder.php', 'web/config/businessRoutes.ts', 'web/src/business/components.ts', 'web/src/business/access.ts', 'web/src/business/application.ts', 'FOUNDATION_VERSION']) {
  if (!fs.existsSync(file)) throw new Error(`缺少业务扩展入口：${file}`);
}
const routes = fs.readFileSync('web/config/routes.ts', 'utf8');
if (routes.indexOf('...businessRoutes') < 0 || routes.indexOf('...businessRoutes') > routes.indexOf("path: '*'")) throw new Error('业务路由必须在兜底路由前组合');
const api = fs.readFileSync('api/routes/api.php', 'utf8');
if (!api.includes("Route::prefix('business')->name('business.')->group(base_path('routes/business.php'))")) throw new Error('业务 API 入口未挂载');
for (const file of ['api/database/seeders/Foundation/MenuSeeder.php', 'api/database/seeders/Foundation/RbacSeeder.php']) {
  const source = fs.readFileSync(file, 'utf8');
  if (!source.includes('firstOrCreate') || source.includes('syncPermissions') || source.includes('updateOrCreate')) throw new Error('框架默认数据不得覆盖已有配置或移除额外授权');
}
NODE
pass '业务扩展入口与非覆盖式升级规则存在'


role_auth_matches="$(git grep -nE 'hasRole|roles\.includes|roles\.some|role\.name\s*===|role_name' -- 'web/src' 'web/config' || true)"
if [[ -n "$role_auth_matches" ]]; then
  printf '%s\n' "$role_auth_matches" >&2
  fail '前端不得按角色名称控制页面或按钮权限'
fi
pass '前端页面和按钮未按角色名称授权'

if ! git grep -n 'componentRegistry' -- 'web/src/layouts/componentRegistry.tsx' 'web/src/layouts/menu.tsx' >/dev/null; then
  fail '缺少受控菜单组件注册表证据'
fi
pass '后端菜单组件标识通过受控注册表解析'

tracked_forbidden='(^|/)(node_modules/|dist/|vendor/|storage/app/openapi\.yaml$|storage/logs/|\.phpunit\.result\.cache$)'
tracked_artifacts="$(git ls-files | grep -E "$tracked_forbidden" | grep -vE '(^|/)storage/(app|logs)/\.gitignore$' || true)"
if [[ -n "$tracked_artifacts" ]]; then
  printf '%s\n' "$tracked_artifacts" >&2
  fail 'Git 跟踪了依赖、构建产物、日志、测试缓存或本地 OpenAPI 产物'
fi
pass 'Git 未跟踪 node_modules、dist、vendor、日志、测试缓存或本地 OpenAPI 产物'

tracked_env="$(git ls-files | grep -E '(^|/)\.env($|\.)' | grep -vE '(^|/)\.env\.example$' || true)"
if [[ -n "$tracked_env" ]]; then
  printf '%s\n' "$tracked_env" >&2
  fail 'Git 跟踪了非示例 .env 文件'
fi
pass 'Git 未跟踪非示例 .env 文件'

tracked_other_locks="$(git ls-files | grep -E '(^|/)(package-lock\.json|yarn\.lock|bun\.lockb?|npm-shrinkwrap\.json)$' || true)"
if [[ -n "$tracked_other_locks" ]]; then
  printf '%s\n' "$tracked_other_locks" >&2
  fail 'Git 跟踪了 npm/Yarn/Bun 锁文件'
fi
pass 'Git 未跟踪 npm/Yarn/Bun 锁文件'

pass '仓库基础架构门禁通过'
