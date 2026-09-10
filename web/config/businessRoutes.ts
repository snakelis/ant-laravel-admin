import type { defineConfig } from '@umijs/max';

// 业务项目维护。页面统一使用 /business 前缀，component 指向 ./business/ 下的页面。
// access 名称需在 src/business/access.ts 中声明，并与后端权限码保持一致。
const businessRoutes: Extract<Parameters<typeof defineConfig>[0]['routes'], unknown[]> = [];

export default businessRoutes;
