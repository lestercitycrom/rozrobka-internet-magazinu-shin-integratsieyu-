<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

class AuthenticateAdmin
{
    public function handle(string $email, string $password): ?User
    {
        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->whereIn('role', UserRole::adminValues())
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }
}
