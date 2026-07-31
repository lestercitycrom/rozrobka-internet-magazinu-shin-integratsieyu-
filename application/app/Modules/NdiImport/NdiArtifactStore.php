<?php

namespace App\Modules\NdiImport;

use App\Modules\NdiImport\Data\StoredArtifact;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class NdiArtifactStore
{
    public function store(int $supplierId, string $supplierCode, string $sourcePath, bool $isRedacted = false): StoredArtifact
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException('The NDI source artifact is not a readable file.');
        }

        $sha256 = hash_file('sha256', $sourcePath);
        $byteSize = filesize($sourcePath);

        if ($sha256 === false || $byteSize === false) {
            throw new RuntimeException('Unable to fingerprint the NDI source artifact.');
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $extension = preg_match('/^[a-z0-9]+$/D', $extension) === 1 ? $extension : 'bin';
        $disk = Storage::disk((string) config('ndi.raw_disk'));
        $record = DB::table('source_artifacts')
            ->where('supplier_id', $supplierId)
            ->where('sha256', $sha256)
            ->first();
        $storageKey = $record?->storage_key
            ?? trim((string) config('ndi.raw_prefix'), '/')."/{$supplierCode}/".substr($sha256, 0, 2)."/{$sha256}.{$extension}";

        $this->storeBytesOnce($disk, $sourcePath, $storageKey, (int) $byteSize, $sha256);

        if ($record === null) {
            $id = (string) Str::ulid();

            try {
                DB::table('source_artifacts')->insert([
                    'id' => $id,
                    'supplier_id' => $supplierId,
                    'kind' => $extension === 'xlsx' ? 'xlsx' : 'binary',
                    'original_filename' => basename($sourcePath),
                    'storage_key' => $storageKey,
                    'byte_size' => $byteSize,
                    'sha256' => $sha256,
                    'received_at' => now(),
                    'content_type' => $extension === 'xlsx'
                        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        : 'application/octet-stream',
                    'is_redacted' => $isRedacted,
                ]);
            } catch (QueryException $exception) {
                $record = DB::table('source_artifacts')
                    ->where('supplier_id', $supplierId)
                    ->where('sha256', $sha256)
                    ->first();

                if ($record === null) {
                    throw $exception;
                }
            }

            $record ??= DB::table('source_artifacts')->where('id', $id)->first();
        }

        if ($record === null) {
            throw new RuntimeException('Unable to register the NDI source artifact.');
        }

        return new StoredArtifact(
            $record->id,
            (int) $record->supplier_id,
            $record->original_filename,
            $record->storage_key,
            (int) $record->byte_size,
            $record->sha256,
            $record->content_type ?? 'application/octet-stream',
        );
    }

    private function storeBytesOnce(
        FilesystemAdapter $disk,
        string $sourcePath,
        string $storageKey,
        int $byteSize,
        string $sha256,
    ): void {
        if (! $disk->exists($storageKey)) {
            $stream = fopen($sourcePath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Unable to open the NDI source artifact for immutable storage.');
            }

            try {
                if (! $disk->writeStream($storageKey, $stream)) {
                    throw new RuntimeException('Unable to store the immutable NDI source artifact.');
                }
            } finally {
                fclose($stream);
            }
        }

        if (! $disk->exists($storageKey) || $disk->size($storageKey) !== $byteSize) {
            throw new RuntimeException('The immutable NDI source artifact failed its stored-size check.');
        }

        $storedStream = $disk->readStream($storageKey);

        if ($storedStream === false) {
            throw new RuntimeException('Unable to verify the immutable NDI source artifact.');
        }

        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $storedStream);

            if (! hash_equals($sha256, hash_final($hash))) {
                throw new RuntimeException('The immutable NDI source artifact failed its SHA-256 check.');
            }
        } finally {
            fclose($storedStream);
        }
    }
}
