<?php

namespace App\Console\Commands;

use Database\Seeders\Foundation\MenuSeeder;
use Database\Seeders\Foundation\RbacSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class SyncFoundation extends Command
{
    use ConfirmableTrait;

    protected $signature = 'foundation:sync {--force : 允许在生产环境执行}';

    protected $description = '补齐框架权限和菜单，保留已有展示配置及额外授权；不执行迁移或业务种子';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        try {
            DB::transaction(function (): void {
                app(RbacSeeder::class)->run();
                app(MenuSeeder::class)->run();
            });
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
        $this->info('框架默认数据已补齐，已有配置和额外授权已保留。');

        return self::SUCCESS;
    }
}
