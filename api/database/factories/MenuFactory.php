<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'title' => fake()->words(2, true),
            'name' => fake()->unique()->slug(2),
            'path' => '/'.fake()->slug(),
            'component' => 'BlankPage',
            'icon' => null,
            'permission_name' => null,
            'sort' => fake()->numberBetween(0, 100),
            'is_visible' => true,
            'is_enabled' => true,
        ];
    }
}
