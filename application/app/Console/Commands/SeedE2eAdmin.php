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

        $operationsEmail = mb_strtolower(trim((string) config('e2e.operations_email')));
        $operationsPassword = (string) config('e2e.operations_password');
        if (! filter_var($operationsEmail, FILTER_VALIDATE_EMAIL) || $operationsPassword === '') {
            $this->components->error('Provide E2E_OPERATIONS_EMAIL and E2E_OPERATIONS_PASSWORD.');

            return self::INVALID;
        }

        $operations = User::query()->firstOrNew(['email' => $operationsEmail]);
        $operations->forceFill([
            'name' => 'E2E Operations',
            'password' => Hash::make($operationsPassword),
            'role' => UserRole::Operations,
            'locale' => 'nb',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->components->info('E2E owner and operations accounts are ready.');

        return self::SUCCESS;
    }
}
