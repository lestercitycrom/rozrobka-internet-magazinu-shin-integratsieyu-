<?php

test('protected staging hook applies only the cumulative customer release seeder', function (): void {
    $hook = file_get_contents(dirname(__DIR__, 3).'/deploy/post-receive');

    expect($hook)
        ->toContain("db:seed --class='Database\\Seeders\\CustomerHomepageReleaseSeeder' --force --no-interaction")
        ->not->toMatch('/php artisan db:seed --force/');
});

test('protected staging hook retains the current snapshot set through bounded recovery attempts', function (): void {
    $hook = file_get_contents(dirname(__DIR__, 3).'/deploy/post-receive');

    expect($hook)
        ->toContain('easytire:backup:prune --keep=20 --no-interaction')
        ->toContain("awk 'NR > 5 {print \$2}'")
        ->toContain(': > "$database_marker"')
        ->toContain('Failed release was retained for scoped content recovery.');
});

test('staging guard installer is preconditioned and restores both files on nginx failure', function (): void {
    $installer = file_get_contents(dirname(__DIR__, 3).'/deploy/install-accepted-staging-guards.sh');

    expect($installer)
        ->toContain('expected-hook-sha256')
        ->toContain('expected-nginx-sha256')
        ->toContain('Expected exactly one application location block.')
        ->toContain('location = /robots.txt')
        ->toContain('nginx -t')
        ->toContain('systemctl reload nginx')
        ->toContain('both files were restored.');
});
