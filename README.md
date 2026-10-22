# Ant Laravel Admin

可复用的后台管理基础框架，提供登录、用户、角色、权限、菜单与统一 API，不包含具体业务。业务项目独立运行，通过 Git 合并框架发布版本升级。

后端：Laravel 13、PHP 8.3 / 8.4 / 8.5（安全补丁下限见安装说明）、MySQL 8.0、Redis、Sanctum、Spatie 权限。前端：Ant Design Pro v6.0.2、React、TypeScript、Umi Max，使用 pnpm。

## 文档

- [安装、配置与检查](doc/foundation/setup.md)
- [业务扩展与框架升级](doc/foundation/extension-upgrade.md)
- [开发规则](AGENTS.md)

`api/` 为后端，`web/` 为前端。`doc/foundation/` 由框架维护；派生项目自行维护 `doc/business/`。实际环境地址、凭据和本机部署记录不放入公共框架文档。

版本见 [FOUNDATION_VERSION](FOUNDATION_VERSION)，发布标签使用 `foundation-vX.Y.Z`。

## 许可证

暂未确定许可证。
