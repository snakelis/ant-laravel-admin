<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable {
        hasPermissionTo as private hasAssignedPermissionTo;
    }

    /**
     * 分配记录与有效授权分离；实时读取状态，避免缓存或已加载关系继续授权。
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        $permission = $this->filterPermission($permission, $guardName);

        return Permission::query()->whereKey($permission->getKey())->where('is_enabled', true)->exists()
            && $this->hasAssignedPermissionTo($permission, $guardName);
    }

    /** @return Collection<int, Permission> */
    public function getEnabledPermissions(): Collection
    {
        return Permission::query()
            ->whereKey($this->getAllPermissions()->modelKeys())
            ->where('is_enabled', true)
            ->get();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
