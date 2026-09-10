<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('title', 80);
            $table->string('name', 120)->nullable()->unique();
            $table->string('path', 255)->nullable();
            $table->string('component', 120)->nullable();
            $table->string('icon', 80)->nullable();
            $table->string('permission_name')->nullable()->index();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('permission_name')
                ->references('name')
                ->on(config('permission.table_names.permissions'))
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
