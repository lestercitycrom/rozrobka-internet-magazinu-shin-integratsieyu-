<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Identity\Enums\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedE2eAdmin extends Command
{
    protected $signature = 'e2e:seed-admin {--email= : E2E-only email address} {--password= : E2E-only password}';

    protected $description = 'Create the isolated administrator used by local and testing browser checks.';

    public function handle(): int
    {
        if (! $this->laravel->environment(['local', 'testing'])) {
            $this->components->error('The E2E admin can only be seeded in local or testing environments.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) ($this->option('email') ?? config('e2e.admin_email'))));
        $password = (string) ($this->option('password') ?? config('e2e.admin_password'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->components->error('Provide an E2E-only --email and --password.');

            return self::INVALID;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => 'E2E Administrator',
            'password' => Hash::make($password),
            'role' => UserRole::Owner,
            'locale' => 'nb',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->components->info('E2E administrator is ready.');

        return self::SUCCESS;
    }
}
