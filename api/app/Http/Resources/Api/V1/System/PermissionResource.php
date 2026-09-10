<?php

namespace App\Http\Resources\Api\V1\System;

use App\Models\Permission;
use App\Services\Rbac\RecordCapabilities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Permission
 */
class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'capabilities' => $this->when($request->routeIs('api.v1.permissions.*'), fn () => app(RecordCapabilities::class)->permission($request->user(), $this->resource)),
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'title' => $this->title,
            'module_code' => $this->module_code,
            'module_title' => $this->module_title,
            'resource_code' => $this->resource_code,
            'resource_title' => $this->resource_title,
            'action_code' => $this->action_code,
            'action_title' => $this->action_title,
            'description' => $this->description,
            'sort' => $this->sort,
            'is_system' => $this->is_system,
            'is_enabled' => $this->is_enabled,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
