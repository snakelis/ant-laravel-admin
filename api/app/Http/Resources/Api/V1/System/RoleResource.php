<?php

namespace App\Http\Resources\Api\V1\System;

use App\Models\Role;
use App\Services\Rbac\RecordCapabilities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'capabilities' => $this->when($request->routeIs('api.v1.roles.*'), fn () => app(RecordCapabilities::class)->role($request->user(), $this->resource)),
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
        ];
    }
}
