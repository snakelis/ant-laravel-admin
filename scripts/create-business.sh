#!/usr/bin/env bash
set -euo pipefail

# 只复制已发布的 Git 内容；不复制凭据、依赖、缓存或框架开发数据。
if [[ $# -lt 1 || $# -gt 3 ]]; then
  echo '用法：scripts/create-business.sh <新目录> [项目标题] [框架标签]' >&2
  exit 1
fi
foundation_root="$(git -C "$(dirname "$0")" rev-parse --show-toplevel)"
target="$1"
project_title="${2:-$(basename "$target")}"
release="${3:-foundation-v$(cat "$foundation_root/FOUNDATION_VERSION")}"
if [[ -e "$target" ]]; then
  echo '目标目录已存在，拒绝覆盖。' >&2
  exit 1
fi
foundation_url="https://github.com/snakelis/ant-laravel-admin.git"
# 从线上已发布标签创建独立仓库，后续 upstream 也从线上获取更新。
git clone --origin upstream --branch "$release" "$foundation_url" "$target"
git -C "$target" switch -c main
git -C "$target" config remote.upstream.pushurl DISABLED
node - "$target" "$project_title" "$release" "$foundation_root" <<'NODE'
const fs = require('node:fs');
const path = require('node:path');
const [target, title, release, foundationRoot] = process.argv.slice(2);
const readJson = (root, file) => JSON.parse(fs.readFileSync(path.join(root, file), 'utf8'));
const requiredPhp = readJson(foundationRoot, 'api/composer.json').require.php;
const composer = readJson(target, 'api/composer.json');
const lock = readJson(target, 'api/composer.lock');
if (composer.require.php !== requiredPhp || lock.platform?.php !== requiredPhp) {
  throw new Error(`发布标签 ${release} 的 PHP 安全基线与当前生成器不一致，拒绝生成业务配置。请选择包含当前安全修复的已发布标签，并使用对应版本的生成器。`);
}
if (composer.config?.platform?.php !== undefined || lock['platform-overrides']?.php !== undefined) {
  throw new Error('发布标签不得通过 config.platform.php 模拟 PHP 版本，拒绝生成业务配置。');
}
const project = path.basename(path.resolve(target)).replace(/[^a-zA-Z0-9_]/g, '_').toLowerCase();
if (!project) throw new Error('目录名不能转换为有效数据库名');
const app = path.join(target, 'web/src/business/application.ts');
fs.writeFileSync(app, `// 业务项目配置，由本项目维护。\nexport default {\n  title: ${JSON.stringify(title)},\n  logo: false as string | false,\n  colorPrimary: '#1677ff',\n};\n`);
// 新应用独立配置示例和测试库名；不包含源项目的真实连接信息。
const example = path.join(target, 'api/.env.example');
fs.writeFileSync(example, fs.readFileSync(example, 'utf8')
  .replace(/^APP_NAME=.*$/m, `APP_NAME=${JSON.stringify(title)}`)
  .replace(/^DB_DATABASE=.*$/m, `DB_DATABASE=${project}`)
  .replace(/^CACHE_PREFIX=.*$/m, `CACHE_PREFIX=${project}_cache`)
  .replace(/^REDIS_QUEUE=.*$/m, `REDIS_QUEUE=${project}`) + `\nSESSION_COOKIE=${project}_session\nREDIS_PREFIX=${project}_database_\n`);
const agents = path.join(target, 'AGENTS.md');
const agentSource = fs.readFileSync(agents, 'utf8');
if (!/^## 项目定位\n[\s\S]*?(?=^## )/m.test(agentSource)) {
  throw new Error('发布版本缺少项目定位章节，需先适配生成脚本');
}
fs.writeFileSync(agents, agentSource
  .replace(/^## 项目定位\n[\s\S]*?(?=^## )/m, () => `## 项目定位\n\n本仓库是从 Ant Laravel Admin 派生的独立业务系统，可以实现用户明确授权的业务功能。业务目标和范围见 doc/business/requirements.md，安装与升级见 README.md。基础框架的“业务无关”定位仅适用于上游仓库。\n\n保留下方通用技术、安全与提交规范；业务代码通过 Business 扩展入口实现。合并框架更新时保留本项目定位、数据库和文档，审查并吸收上游通用规则。未获明确实施授权时不自行开发业务。\n\n`)
  .replaceAll('ant_laravel_admin_test', `${project}_test`).replaceAll('ant_laravel_admin', project));
const phpunit = path.join(target, 'api/phpunit.xml');
fs.writeFileSync(phpunit, fs.readFileSync(phpunit, 'utf8').replace('value="ant_laravel_admin_test"', `value="${project}_test"`));
// 新业务文档与框架文档分开；旧版本没有目录时补充生成器附带的通用说明。
fs.mkdirSync(path.join(target, 'doc/business'), { recursive: true });
fs.mkdirSync(path.join(target, 'doc/foundation'), { recursive: true });
for (const name of ['setup.md', 'extension-upgrade.md']) {
  const destination = path.join(target, 'doc/foundation', name);
  if (!fs.existsSync(destination)) {
    fs.copyFileSync(path.join(foundationRoot, 'doc/foundation', name), destination);
  }
}
fs.writeFileSync(path.join(target, 'doc/business/requirements.md'), `# ${title}：业务需求

从基础框架 ${release} 派生，尚未初始化运行环境或开发业务。

- 业务目标、阶段范围、接入准备与验收标准：待用户确认。
- 开发库：${project}；独立测试库：${project}_test。
- 站点、端口与部署环境：待配置，不继承框架开发实例。
- 本目录由业务项目维护，部署说明有需要时再创建。未明确授权前不自动开发。
`);
fs.writeFileSync(path.join(target, 'README.md'), `# ${title}

基于 Ant Laravel Admin 的独立业务系统，初始版本 ${release}，尚未初始化。

- [业务需求](doc/business/requirements.md)
- [安装、配置与检查](doc/foundation/setup.md)
- [业务扩展与框架升级](doc/foundation/extension-upgrade.md)
- [开发规则](AGENTS.md)

开发库：${project}；测试库：${project}_test。环境配置和凭据独立维护，没有默认账号或预设 Docker 挂载。

main 维护业务代码，upstream 从 https://github.com/snakelis/ant-laravel-admin.git 获取更新，禁止推送；业务 origin 尚未配置。

框架说明位于 doc/foundation，业务说明位于 doc/business。按需补充自己的运行和部署文档，不重复复制通用说明。
`);
fs.rmSync(path.join(target, 'doc/environment-deployment.md'), { force: true });
for (const file of ['api/README.md', 'web/README.md']) {
  fs.writeFileSync(path.join(target, file), '# 使用说明\n\n见[安装与检查](../doc/foundation/setup.md)、[业务扩展](../doc/foundation/extension-upgrade.md)及[业务需求](../doc/business/requirements.md)。\n');
}
NODE
echo '业务仓库已创建；请提交项目配置，并按 README 安装运行环境。'
