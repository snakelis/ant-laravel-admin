<?php

use App\Services\Rbac\SystemPermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table): void {
            $table->string('title', 80)->nullable()->after('guard_name');
            $table->string('module_code', 80)->nullable()->after('title');
            $table->string('module_title', 80)->nullable()->after('module_code');
            $table->string('resource_code', 80)->nullable()->after('module_title');
            $table->string('resource_title', 80)->nullable()->after('resource_code');
            $table->string('action_code', 80)->nullable()->after('resource_title');
            $table->string('action_title', 80)->nullable()->after('action_code');
            $table->string('description', 255)->nullable()->after('action_title');
            $table->unsignedInteger('sort')->default(0)->after('description');
            $table->boolean('is_system')->default(false)->after('sort');
            $table->boolean('is_enabled')->default(true)->after('is_system');

            $table->index(['module_code', 'resource_code', 'sort'], 'permissions_module_resource_sort_index');
            $table->index(['is_enabled', 'is_system'], 'permissions_enabled_system_index');
        });

        foreach (SystemPermissionCatalog::all() as $name => $metadata) {
            DB::table(config('permission.table_names.permissions'))
                ->where('guard_name', 'web')
                ->where('name', $name)
                ->update($metadata);
        }
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table): void {
            $table->dropIndex('permissions_module_resource_sort_index');
            $table->dropIndex('permissions_enabled_system_index');
            $table->dropColumn([
                'title',
                'module_code',
                'module_title',
                'resource_code',
                'resource_title',
                'action_code',
                'action_title',
                'description',
                'sort',
                'is_system',
                'is_enabled',
            ]);
        });
    }
};
