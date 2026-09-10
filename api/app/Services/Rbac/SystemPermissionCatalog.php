<?php

namespace App\Services\Rbac;

class SystemPermissionCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'system.user.view' => self::metadata('用户管理', 'user', 'view', '查看用户', 10, '查看用户列表和详情。'),
            'system.user.create' => self::metadata('用户管理', 'user', 'create', '创建用户', 11, '创建后台用户。'),
            'system.user.update' => self::metadata('用户管理', 'user', 'update', '更新用户', 12, '更新后台用户资料和角色。'),
            'system.user.delete' => self::metadata('用户管理', 'user', 'delete', '删除用户', 13, '删除后台用户。'),
            'system.role.view' => self::metadata('角色管理', 'role', 'view', '查看角色', 20, '查看角色列表和详情。'),
            'system.role.create' => self::metadata('角色管理', 'role', 'create', '创建角色', 21, '创建角色并配置权限。'),
            'system.role.update' => self::metadata('角色管理', 'role', 'update', '更新角色', 22, '更新角色名称和权限。'),
            'system.role.delete' => self::metadata('角色管理', 'role', 'delete', '删除角色', 23, '删除角色。'),
            'system.permission.view' => self::metadata('权限管理', 'permission', 'view', '查看权限', 30, '查看权限列表和授权树。'),
            'system.permission.create' => self::metadata('权限管理', 'permission', 'create', '创建权限', 31, '创建自定义权限。'),
            'system.permission.update' => self::metadata('权限管理', 'permission', 'update', '更新权限', 32, '更新自定义权限元数据。'),
            'system.permission.delete' => self::metadata('权限管理', 'permission', 'delete', '删除权限', 33, '删除未被引用的自定义权限。'),
            'system.permission.grant-all' => self::metadata('权限管理', 'permission', 'grant-all', '授予全部权限', 34, '允许授予操作者自身未持有的权限和受保护角色。'),
            'system.menu.view' => self::metadata('菜单管理', 'menu', 'view', '查看菜单', 40, '查看菜单列表和树。'),
            'system.menu.create' => self::metadata('菜单管理', 'menu', 'create', '创建菜单', 41, '创建菜单。'),
            'system.menu.update' => self::metadata('菜单管理', 'menu', 'update', '更新菜单', 42, '更新菜单。'),
            'system.menu.delete' => self::metadata('菜单管理', 'menu', 'delete', '删除菜单', 43, '删除菜单。'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $name): ?array
    {
        return self::all()[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function metadata(string $resourceTitle, string $resourceCode, string $actionCode, string $actionTitle, int $sort, string $description): array
    {
        return [
            'title' => $actionTitle,
            'module_code' => 'system',
            'module_title' => '系统管理',
            'resource_code' => $resourceCode,
            'resource_title' => $resourceTitle,
            'action_code' => $actionCode,
            'action_title' => $actionTitle,
            'description' => $description,
            'sort' => $sort,
            'is_system' => true,
            'is_enabled' => true,
        ];
    }
}
