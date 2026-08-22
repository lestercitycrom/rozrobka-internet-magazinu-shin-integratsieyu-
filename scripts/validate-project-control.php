<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$requiredFiles = [
    'README.md',
    'project-control/WORKFLOW.md',
    'project-control/STATUS.md',
    'project-control/DECISIONS.md',
    'project-control/CONTEXT.md',
    'project-control/CURRENT_TASK.md',
    'project-control/CURRENT_REVIEW.md',
    '.github/ISSUE_TEMPLATE/task.yml',
    '.github/ISSUE_TEMPLATE/config.yml',
    '.github/pull_request_template.md',
    '.github/workflows/quality.yml',
];

foreach ($requiredFiles as $relativePath) {
    $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (! is_file($path) || filesize($path) === 0) {
        $errors[] = "Missing or empty required file: {$relativePath}";
    }
}

$workflow = file_get_contents($root.'/project-control/WORKFLOW.md') ?: '';
$status = file_get_contents($root.'/project-control/STATUS.md') ?: '';
$task = file_get_contents($root.'/project-control/CURRENT_TASK.md') ?: '';
$review = file_get_contents($root.'/project-control/CURRENT_REVIEW.md') ?: '';
$gitignore = file_get_contents($root.'/.gitignore') ?: '';

$contracts = [
    ['project-control/WORKFLOW.md', $workflow, 'Version: 1.1'],
    ['project-control/WORKFLOW.md', $workflow, 'Three truth domains'],
    ['project-control/WORKFLOW.md', $workflow, 'Merge and runtime gates'],
    ['project-control/STATUS.md', $status, 'Runtime gates'],
    ['project-control/CURRENT_TASK.md', $task, '- Issue:'],
    ['project-control/CURRENT_REVIEW.md', $review, '- PR:'],
    ['.gitignore', $gitignore, '/.pnpm-store/'],
    ['.gitignore', $gitignore, '/application/tmp/'],
    ['.gitignore', $gitignore, '/docs/access.md'],
];

foreach ($contracts as [$file, $contents, $needle]) {
    if (! str_contains($contents, $needle)) {
        $errors[] = "{$file} must contain: {$needle}";
    }
}

$forbiddenTrackedPaths = ['.env', 'application/.env', 'docs/access.md'];

foreach ($forbiddenTrackedPaths as $path) {
    $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $command = sprintf('git -C %s ls-files --error-unmatch -- %s 2>%s', escapeshellarg($root), escapeshellarg($path), $nullDevice);
    exec($command, $output, $exitCode);

    if ($exitCode === 0) {
        $errors[] = "Forbidden sensitive path is tracked: {$path}";
    }

    $output = [];
}

if ($errors !== []) {
    fwrite(STDERR, "Project-control validation failed:\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

fwrite(STDOUT, "Project-control validation passed.\n");
