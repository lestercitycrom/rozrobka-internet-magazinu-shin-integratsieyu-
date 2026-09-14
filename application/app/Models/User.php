<?php

namespace App\Models;

use App\Modules\Identity\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role->canAccessAdmin();
    }

    public function hasAdminRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canOperateServiceRequests(): bool
    {
        return $this->hasAdminRole(UserRole::Owner, UserRole::Operations);
    }

    public function canManageContent(): bool
    {
        return $this->hasAdminRole(UserRole::Owner, UserRole::Editor);
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }
}
