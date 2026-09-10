import type { RegisteredComponent } from '../layouts/registeredComponent';

// 业务项目维护。静态声明页面标识、路由和查看权限；禁止动态加载后端返回的路径。
// 示例：BusinessCustomerList: { label: '客户', routePath: '/business/customers', permission: 'business.customer.view' }
export const businessComponents = {} satisfies Record<string, RegisteredComponent>;
