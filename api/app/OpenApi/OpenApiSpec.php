<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '0.2.0',
    title: 'Ant Laravel Admin API',
    description: '业务无关后台管理基础框架的版本化 JSON API。'
)]
#[OA\Server(
    url: '/api/v1',
    description: 'API v1'
)]
#[OA\Tag(name: 'Foundation', description: '基础设施与健康检查接口')]
#[OA\Tag(name: 'Auth', description: 'Sanctum SPA Cookie 认证接口')]
#[OA\Tag(name: 'Users', description: '系统用户管理接口')]
#[OA\Tag(name: 'Roles', description: '系统角色管理接口')]
#[OA\Tag(name: 'Permissions', description: '系统权限管理接口')]
#[OA\Tag(name: 'Menus', description: '系统菜单管理接口')]
#[OA\SecurityScheme(
    securityScheme: 'sanctumSession',
    type: 'apiKey',
    in: 'cookie',
    name: 'laravel_session',
    description: 'Laravel Sanctum SPA Session Cookie，配合 XSRF-TOKEN Cookie 和 X-XSRF-TOKEN Header 使用。登录前先请求 /sanctum/csrf-cookie。'
)]
#[OA\Schema(
    schema: 'ApiSuccessEnvelope',
    type: 'object',
    required: ['success', 'code', 'message', 'data'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'string', example: 'ok'),
        new OA\Property(property: 'message', type: 'string', example: 'OK'),
        new OA\Property(property: 'data'),
        new OA\Property(property: 'meta', type: 'object', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'ApiErrorEnvelope',
    type: 'object',
    required: ['success', 'code', 'message'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'code', type: 'string', example: 'validation_error'),
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(property: 'errors', type: 'object', nullable: true),
        new OA\Property(property: 'meta', type: 'object', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    type: 'object',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'count', type: 'integer', example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 100),
        new OA\Property(property: 'last_page', type: 'integer', example: 7),
        new OA\Property(property: 'has_more_pages', type: 'boolean', example: true),
        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
    ]
)]
#[OA\Schema(
    schema: 'RecordCapabilities',
    description: '管理列表/详情中的只读操作提示；写接口仍执行实时授权。',
    type: 'object',
    properties: [
        new OA\Property(property: 'update', type: 'boolean'),
        new OA\Property(property: 'delete', type: 'boolean'),
        new OA\Property(property: 'update_reason', type: 'string', nullable: true),
        new OA\Property(property: 'delete_reason', type: 'string', nullable: true),
        new OA\Property(property: 'rename', type: 'boolean', description: '仅权限记录返回'),
        new OA\Property(property: 'change_status', type: 'boolean', description: '仅权限记录返回'),
        new OA\Property(property: 'protected', type: 'boolean', description: '仅权限记录返回'),
    ]
)]
#[OA\Schema(
    schema: 'User',
    type: 'object',
    properties: [
        new OA\Property(property: 'capabilities', ref: '#/components/schemas/RecordCapabilities'),
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Administrator'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(ref: '#/components/schemas/Role')),
    ]
)]
#[OA\Schema(
    schema: 'Role',
    type: 'object',
    properties: [
        new OA\Property(property: 'capabilities', ref: '#/components/schemas/RecordCapabilities'),
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'super-admin'),
        new OA\Property(property: 'guard_name', type: 'string', example: 'web'),
        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(ref: '#/components/schemas/Permission')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Permission',
    type: 'object',
    properties: [
        new OA\Property(property: 'capabilities', ref: '#/components/schemas/RecordCapabilities'),
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'system.user.view'),
        new OA\Property(property: 'guard_name', type: 'string', example: 'web'),
        new OA\Property(property: 'title', type: 'string', nullable: true, example: '查看用户'),
        new OA\Property(property: 'module_code', type: 'string', nullable: true, example: 'system'),
        new OA\Property(property: 'module_title', type: 'string', nullable: true, example: '系统管理'),
        new OA\Property(property: 'resource_code', type: 'string', nullable: true, example: 'user'),
        new OA\Property(property: 'resource_title', type: 'string', nullable: true, example: '用户管理'),
        new OA\Property(property: 'action_code', type: 'string', nullable: true, example: 'view'),
        new OA\Property(property: 'action_title', type: 'string', nullable: true, example: '查看用户'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: '查看用户列表和详情。'),
        new OA\Property(property: 'sort', type: 'integer', example: 10),
        new OA\Property(property: 'is_system', type: 'boolean', example: true),
        new OA\Property(property: 'is_enabled', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'PermissionListResponse',
    type: 'object',
    required: ['success', 'code', 'message', 'data', 'meta'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'string', example: 'ok'),
        new OA\Property(property: 'message', type: 'string', example: 'OK'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Permission')),
        new OA\Property(property: 'meta', type: 'object', required: ['pagination'], properties: [
            new OA\Property(property: 'pagination', ref: '#/components/schemas/PaginationMeta'),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'PermissionTreeNode',
    type: 'object',
    required: ['key', 'type', 'title'],
    properties: [
        new OA\Property(property: 'key', type: 'string', example: 'system.menu.view'),
        new OA\Property(property: 'type', type: 'string', enum: ['module', 'resource', 'permission'], example: 'permission'),
        new OA\Property(property: 'title', type: 'string', example: '查看菜单'),
        new OA\Property(property: 'id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'name', type: 'string', nullable: true, example: 'system.menu.view'),
        new OA\Property(property: 'action_code', type: 'string', nullable: true, example: 'view'),
        new OA\Property(property: 'action_title', type: 'string', nullable: true, example: '查看菜单'),
        new OA\Property(property: 'is_system', type: 'boolean', nullable: true, example: true),
        new OA\Property(property: 'is_enabled', type: 'boolean', nullable: true, example: true),
        new OA\Property(property: 'children', type: 'array', items: new OA\Items(ref: '#/components/schemas/PermissionTreeNode')),
    ]
)]
#[OA\Schema(
    schema: 'PermissionTreeResponse',
    type: 'object',
    required: ['success', 'code', 'message', 'data'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'string', example: 'ok'),
        new OA\Property(property: 'message', type: 'string', example: 'OK'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PermissionTreeNode')),
    ]
)]
#[OA\Schema(
    schema: 'Menu',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'parent_id', type: 'integer', nullable: true, example: null),
        new OA\Property(property: 'title', type: 'string', example: '系统管理'),
        new OA\Property(property: 'name', type: 'string', nullable: true, example: 'system.users'),
        new OA\Property(property: 'path', type: 'string', nullable: true, example: '/system/users'),
        new OA\Property(property: 'component', type: 'string', nullable: true, example: 'SystemUserList'),
        new OA\Property(property: 'icon', type: 'string', nullable: true, example: 'SettingOutlined'),
        new OA\Property(property: 'permission_name', type: 'string', nullable: true, example: 'system.user.view'),
        new OA\Property(property: 'sort', type: 'integer', example: 10),
        new OA\Property(property: 'is_visible', type: 'boolean', example: true),
        new OA\Property(property: 'is_enabled', type: 'boolean', example: true),
        new OA\Property(property: 'children', type: 'array', items: new OA\Items(ref: '#/components/schemas/Menu')),
    ]
)]
#[OA\Schema(
    schema: 'CurrentUserProfile',
    type: 'object',
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['super-admin']),
        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), example: ['system.user.view']),
        new OA\Property(property: 'menus', type: 'array', items: new OA\Items(ref: '#/components/schemas/Menu')),
    ]
)]
class OpenApiSpec
{
    // This class intentionally only carries OpenAPI attributes.
}
