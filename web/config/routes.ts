import businessRoutes from './businessRoutes';

export default [
  {
    path: '/login',
    layout: false,
    component: './LoginPage',
  },
  {
    path: '/',
    component: './RootRedirect',
  },
  {
    path: '/dashboard',
    name: '后台概览',
    component: './SystemOverviewPage',
  },
  {
    path: '/system',
    redirect: '/system/users',
  },
  {
    path: '/system/users',
    name: '用户管理',
    component: './system/SystemUserPage',
    access: 'canViewSystemUsers',
  },
  {
    path: '/system/roles',
    name: '角色管理',
    component: './system/SystemRolePage',
    access: 'canViewSystemRoles',
  },
  {
    path: '/system/permissions',
    name: '权限管理',
    component: './system/SystemPermissionPage',
    access: 'canViewSystemPermissions',
  },
  {
    path: '/system/menus',
    name: '菜单管理',
    component: './system/SystemMenuPage',
    access: 'canViewSystemMenus',
  },
  ...businessRoutes,
  {
    path: '/403',
    layout: false,
    component: './ForbiddenPage',
  },
  {
    path: '*',
    layout: false,
    component: './NotFoundPage',
  },
];
