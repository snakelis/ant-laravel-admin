<?php

namespace Database\Seeders\Foundation;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $system = Menu::query()->firstOrCreate(
            ['name' => 'system'],
            [
                'parent_id' => null,
                'title' => '系统管理',
                'path' => '/system',
                'component' => 'RouteView',
                'icon' => 'SettingOutlined',
                'permission_name' => null,
                'sort' => 10,
                'is_visible' => true,
                'is_enabled' => true,
            ]
        );

        $menus = [
            [
                'name' => 'system.users',
                'title' => '用户管理',
                'path' => '/system/users',
                'component' => 'SystemUserList',
                'permission_name' => 'system.user.view',
                'sort' => 10,
            ],
            [
                'name' => 'system.roles',
                'title' => '角色管理',
                'path' => '/system/roles',
                'component' => 'SystemRoleList',
                'permission_name' => 'system.role.view',
                'sort' => 20,
            ],
            [
                'name' => 'system.permissions',
                'title' => '权限管理',
                'path' => '/system/permissions',
                'component' => 'SystemPermissionList',
                'permission_name' => 'system.permission.view',
                'sort' => 30,
            ],
            [
                'name' => 'system.menus',
                'title' => '菜单管理',
                'path' => '/system/menus',
                'component' => 'SystemMenuList',
                'permission_name' => 'system.menu.view',
                'sort' => 40,
            ],
        ];

        foreach ($menus as $menu) {
            Menu::query()->firstOrCreate(
                ['name' => $menu['name']],
                $menu + [
                    'parent_id' => $system->id,
                    'icon' => null,
                    'is_visible' => true,
                    'is_enabled' => true,
                ]
            );
        }
    }
}
