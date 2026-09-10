# 安装、配置与检查

通用步骤适用于框架和派生项目，实际站点与环境由使用者配置。

## 首次安装

先准备独立 MySQL 开发库及 `_test` 测试库、Redis 连接信息。以下初始化命令只用于新环境；已有 `.env` 不覆盖、已有 APP_KEY 不重新生成。

```bash
cd api
composer install
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
- 根目录架构门禁：`bash scripts/verify-foundation.sh`。

按修改范围运行检查；环境文件、凭据、依赖目录、构建产物和日志不得提交。
