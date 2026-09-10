<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Collection;

class MenuTreeBuilder
{
    /**
     * @param  Collection<int, Menu>  $menus
     * @return Collection<int, Menu>
     */
    public static function build(Collection $menus): Collection
    {
        $menus = $menus->values();
        $byParent = $menus->groupBy(fn (Menu $menu) => $menu->parent_id ?: 0);

        $attachChildren = function (Menu $menu) use (&$attachChildren, $byParent): Menu {
            $children = ($byParent->get($menu->id, collect()))
                ->sortBy([['sort', 'asc'], ['id', 'asc']])
                ->values()
                ->map(fn (Menu $child) => $attachChildren($child));

            $menu->setRelation('children', $children);

            return $menu;
        };

        return $byParent->get(0, collect())
            ->sortBy([['sort', 'asc'], ['id', 'asc']])
            ->values()
            ->map(fn (Menu $menu) => $attachChildren($menu));
    }

    public static function isDescendantOf(Menu $candidate, Menu $ancestor): bool
    {
        $current = $candidate;

        while ($current->parent_id !== null) {
            if ((int) $current->parent_id === (int) $ancestor->id) {
                return true;
            }

            $current = $current->parent;

            if (! $current instanceof Menu) {
                return false;
            }
        }

        return false;
    }
}
