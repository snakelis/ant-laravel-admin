<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        // 在 RefreshDatabase 等 trait 执行迁移前检查实际解析后的连接（包含 DB_URL）。
        $connection = $app->make('db')->connection();
        if ($connection->getDriverName() !== 'mysql' || ! str_ends_with($connection->getDatabaseName(), '_test')) {
            throw new RuntimeException('测试必须连接独立的 MySQL _test 数据库，已阻止执行。');
        }

        return $app;
    }
}
