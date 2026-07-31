<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Identity\Enums\UserRole;
use Illuminate\Console\Command;

final class BootstrapAdmin extends Command
{
    protected $signature = 'easytire:bootstrap-admin';

    protected $description = 'Interactively create the first active owner account.';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Name'));
        $email = mb_strtolower(trim((string) $this->ask('Email')));

        if ($name === '' || mb_strlen($name) > 255 || mb_strlen($email) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Name and a valid email are required.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('An account with this email already exists.');

            return self::FAILURE;
        }

        if (User::query()->whereIn('role', UserRole::adminValues())->exists()) {
            $this->error('An administrator already exists. Create additional accounts through an approved administration workflow.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        if ($password !== $confirmation || strlen($password) < 12) {
            $this->error('Passwords must match and contain at least 12 characters.');

            return self::FAILURE;
        }

        if (! $this->confirm('Create active owner account for '.$email.'?', true)) {
            return self::FAILURE;
        }

        User::forceCreate([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => $password,
            'role' => UserRole::Owner,
            'locale' => 'nb',
            'is_active' => true,
        ]);

        $this->info('Owner account created.');

        return self::SUCCESS;
    }
}
