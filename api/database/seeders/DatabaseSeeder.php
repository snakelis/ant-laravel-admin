<?php

namespace Database\Seeders;

use Database\Seeders\Business\BusinessSeeder;
use Database\Seeders\Foundation\MenuSeeder;
use Database\Seeders\Foundation\RbacSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            MenuSeeder::class,
            BusinessSeeder::class,
        ]);
    }
}
