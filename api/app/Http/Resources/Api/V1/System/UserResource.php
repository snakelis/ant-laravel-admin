<?php

namespace App\Http\Resources\Api\V1\System;

use App\Models\User;
use App\Services\Rbac\RecordCapabilities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'capabilities' => $this->when($request->routeIs('api.v1.users.*'), fn () => app(RecordCapabilities::class)->user($request->user(), $this->resource)),
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'permissions' => $this->when($this->relationLoaded('permissions'), fn () => $this->permissions->pluck('name')->sort()->values()),
        ];
    }
}
