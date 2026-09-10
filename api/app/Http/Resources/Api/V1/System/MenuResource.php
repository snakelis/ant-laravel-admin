<?php

namespace App\Http\Resources\Api\V1\System;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Menu
 */
class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'name' => $this->name,
            'path' => $this->path,
            'component' => $this->component,
            'icon' => $this->icon,
            'permission_name' => $this->permission_name,
            'sort' => $this->sort,
            'is_visible' => $this->is_visible,
            'is_enabled' => $this->is_enabled,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            'children' => MenuResource::collection($this->whenLoaded('children')),
        ];
    }
}
