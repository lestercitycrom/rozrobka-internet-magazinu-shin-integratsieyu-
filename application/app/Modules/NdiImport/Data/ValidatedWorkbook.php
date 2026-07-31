<?php

namespace App\Modules\NdiImport\Data;

use Generator;

final class ValidatedWorkbook
{
    /** @param list<WorkbookRejection> $rejections */
    public function __construct(
        public readonly WorkbookSchema $schema,
        public readonly int $acceptedCount,
        public readonly array $rejections,
        private readonly string $stagingPath,
    ) {}

    public function isValid(): bool
    {
        return $this->rejections === [];
    }

    /** @return Generator<int, array<string, mixed>> */
    public function rows(): Generator
    {
        $handle = fopen($this->stagingPath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to read the validated NDI staging stream.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                /** @var array<string, mixed> $row */
                $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    public function cleanup(): void
    {
        if (is_file($this->stagingPath)) {
            unlink($this->stagingPath);
        }
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
