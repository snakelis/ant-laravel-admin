# 安装、配置与检查

通用步骤适用于框架和派生项目，实际站点与环境由使用者配置。

## PHP 运行环境与安全更新

框架支持以下 PHP 分支，必须达到对应安全补丁下限，并持续安装该分支后续的安全更新：

| PHP 分支 | 最低版本 |
| --- | --- |
| 8.3 | 8.3.36 |
| 8.4 | 8.4.27 |
| 8.5 | 8.5.12 |

上述下限来自 [PHP 官方 2026 年 10 月 21 日安全公告](https://www.php.net/archive/2026.php#2026-10-21-5)，对应 CVE-2026-109618、CVE-2026-109619。新部署优先评估 PHP 8.5 的最新安全补丁版本，确认依赖、扩展与项目测试通过后采用。

`api/composer.json` 使用 `~8.3.36 || ~8.4.27 || ~8.5.12`，分别限制各分支；`^8.3.36` 仍会放行未修复的旧版 8.4、8.5，不能作为替代。PHP 8.6、9.x 等新增分支须经兼容性与安全评估后再加入。Composer 锁文件与门禁必须同步更新，框架和派生项目均遵守这一要求。

安装前检查 `php -v`；安装后在 `api/` 执行 `composer check-platform-reqs`，生产环境安装使用 `composer install --no-dev --prefer-dist --optimize-autoloader`，随后执行 `composer check-platform-reqs --no-dev`。不得使用 `config.platform.php` 或 `--ignore-platform-req*` 冒充运行环境已升级。根目录架构门禁中的临时平台模拟仅用于离线验证版本边界，不安装依赖，也不代替真实环境检查。

Composer 检查的是执行命令的 CLI 环境。部署时还须核实 PHP-FPM、Apache 或容器中实际处理请求的 PHP 版本及扩展，升级后重启或重建所有 Web worker、队列 worker 和其他常驻 PHP 进程；CLI 升级不代表其他进程已升级。修改依赖约束、升级 Laravel 或增加应用鉴权不能代替 PHP 运行环境修复。

## 首次安装

先准备独立 MySQL 开发库及 `_test` 测试库、Redis 连接信息。以下初始化命令只用于新环境；已有 `.env` 不覆盖、已有 APP_KEY 不重新生成。

```bash
cd api
composer install
composer check-platform-reqs
cp .env.example .env
php artisan key:generate
# 填写 .env 的数据库、Redis、站点与 Cookie 配置后执行
php artisan migrate --seed
php artisan foundation:create-admin
php artisan serve --host=127.0.0.1 --port=8000
```

没有默认账号，管理员通过交互命令创建。数据库地址、端口和 Redis 认证按实际环境设置，确认使用 MySQL 8.0。开发库与测试库必须独立；Redis 客户端按可用扩展配置，受控环境优先使用 PhpRedis。

另开终端：

```bash
cd web
pnpm install --frozen-lockfile
pnpm dev
```

默认前端端口 5173，可使用 `PORT=5174 pnpm dev`；`web/.env` 中 `UMI_APP_DEV_API_TARGET` 指向后端，默认 `http://127.0.0.1:8000`。生产构建使用 `pnpm build`，由 Web 服务器提供 `web/dist` 并转发 API 与 Sanctum 请求。

## 认证、权限与密码

SPA 先访问 `/sanctum/csrf-cookie` 再登录，使用 HttpOnly Session Cookie，不在 localStorage 保存认证状态。跨域时 CORS 精确允许前端 Origin，Sanctum 配置对应 host/port；生产不公开 Telescope 和 API 文档。

菜单只控制导航，接口通过 `auth:sanctum`、`can` 和 Policy 授权。响应采用统一 `success/code/message/data/meta` 信封，分页位于 `meta.pagination`；组件只能使用受控注册表。

管理员命令、用户 API 和表单统一密码策略：默认至少 12 位，含大小写、数字、符号，上限 255 位。业务可在私有 `.env` 配置 `FOUNDATION_PASSWORD_MIN_LENGTH`、`FOUNDATION_PASSWORD_MIXED_CASE`、`FOUNDATION_PASSWORD_NUMBERS`、`FOUNDATION_PASSWORD_SYMBOLS`。规则影响新设密码，不否定已有登录密码。

## 检查

- 后端（`api/`）：`composer format`、`php artisan test`、`composer openapi`；测试仅连接独立 `_test` MySQL 数据库。
- 前端（`web/`）：`pnpm lint`、`pnpm typecheck`、`pnpm test:auth`、`pnpm build`。
- 根目录架构门禁：`bash scripts/verify-foundation.sh`，需要 Node.js、PHP 和 Composer；包含 Composer 离线解析 PHP 正反版本边界及锁文件校验，不连接数据库。

按修改范围运行检查；环境文件、凭据、依赖目录、构建产物和日志不得提交。
