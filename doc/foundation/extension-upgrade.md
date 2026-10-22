# 业务扩展与框架升级

## 创建业务项目

```bash
./scripts/create-business.sh ../my_business "我的业务系统" foundation-vX.Y.Z
```

将 `foundation-vX.Y.Z` 替换为包含当前安全修复的实际已发布标签。从 GitHub 已发布标签创建完整的独立仓库，保留共同历史。脚本生成业务定位、文档入口、品牌、数据库示例和测试库设置，`upstream` 仅用于拉取框架更新。需要网络访问及仓库读取权限。旧标签不含新文档目录时，生成器补充当前工具附带的通用文档；代码版本仍由指定标签决定，文档不代表额外功能已实现。

不复制真实环境文件、账号、依赖、缓存或数据库，不自动初始化或推送。创建后提交生成的项目配置，按业务 README 初始化；业务远程另用 `git remote add origin <业务仓库地址>` 配置。

每个业务项目独立配置 APP_KEY、数据库、Session Cookie、Redis/缓存前缀和队列名；Docker 卷和站点需另行设置。共享 Redis 时不要执行 `FLUSHDB` / `FLUSHALL`。

创建新业务系统或重写旧系统时，先按[安装、配置与检查](setup.md#php-运行环境与安全更新)准备满足安全补丁下限的 PHP 环境。发布标签可能早于安全修复，创建时须选择包含所需修复的实际已发布版本，不能把旧标签或本地未发布提交当作已修复版本。生成器在改写业务配置前检查目标清单与锁文件，拒绝 PHP 安全基线与当前生成器不一致或模拟 PHP 版本的标签；若拒绝，目标目录只保留克隆内容，不代表业务项目创建完成。旧系统的数据迁移、接口兼容和切换验收集中记录在业务项目的 `doc/business/requirements.md`，不直接复用旧系统的环境文件、Session 或数据库初始化状态。

## 业务扩展与文档边界

| 内容 | 入口 |
| --- | --- |
| Controller、Request、Resource、Model、Service、Policy | `api/app` 对应的 `Business` 子目录 |
| 服务绑定与业务路由 | `BusinessServiceProvider`、`api/routes/business.php` |
| 默认业务权限和菜单 | `api/database/seeders/Business/BusinessSeeder.php` |
| 页面和请求 | `web/src/pages/business`、`web/src/services/business` |
| 路由、品牌、权限及组件 | `web/config/businessRoutes.ts`、`web/src/business/` |

业务 API 位于 `/api/v1/business`，额外配置 `can` 权限，记录访问使用 Policy。权限建议为 `business.<resource>.<action>`。业务迁移仍放在 `api/database/migrations`，使用新增迁移演进。框架不能引用具体业务模型。

`doc/foundation/` 由框架维护，随版本更新；派生项目的 `doc/business/requirements.md` 维护业务目标，部署说明按需放在 `doc/business/`。根 README 只作入口，AGENTS 保留通用技术规范并采用业务定位。框架“业务无关”约束不应阻止业务仓库实现获准需求；框架更新时保留业务配置并审查通用规则变化，不整份覆盖或忽略文档。

## 框架升级

版本见 `FOUNDATION_VERSION`，标签为 `foundation-vX.Y.Z`。提交业务修改后：

```bash
git fetch upstream --tags
git switch -c upgrade/foundation-vX.Y.Z
git merge foundation-vX.Y.Z
```

替换为实际发布标签。按版本变化在实际运行环境安装锁定依赖、执行增量 `php artisan migrate`、`php artisan foundation:sync`，并重新构建前端。验证后合并回业务 `main`。不要覆盖目录或执行 `migrate:fresh`。

合并框架安全更新时，逐项核对 `api/composer.json`、`api/composer.lock`、架构门禁及通用技术规范。若 PHP 下限变化，先按安装说明升级实际运行环境，再安装锁定依赖并执行平台检查；业务项目不可为迁就旧运行环境而放宽安全约束。

`foundation:sync` 只补齐缺失的框架默认数据，保留已有配置，不执行迁移、业务种子或创建账号。命名卷内依赖需在容器内更新，WSL 的 vendor 不会自动同步进去。

## 文档升级约定

框架不维护 `doc/business/` 的具体内容。业务仓库在合并时保留自己的 README 介绍、AGENTS 定位和业务文档，吸收 `doc/foundation/` 的更新。已有项目迁移根 PROJECT.md 时由业务项目自行处理，生成脚本只用于创建新目录，不用于覆盖现有项目。
