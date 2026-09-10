<?php

namespace Database\Seeders\Business;

use Illuminate\Database\Seeder;

/** 业务项目维护；只补齐业务默认数据，不覆盖用户配置。 */
class BusinessSeeder extends Seeder
{
    public function run(): void
    {
        // 在此调用业务权限、菜单等种子。权限建议使用 business.<resource>.<action>。
        // 使用 firstOrCreate 补齐默认值；变更已存在的数据应编写明确的升级迁移。
    }
}
