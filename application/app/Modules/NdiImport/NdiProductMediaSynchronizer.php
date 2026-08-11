<?php

namespace App\Modules\NdiImport;

use App\Modules\Catalog\Models\ProductMediaSource;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\MediaAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class NdiProductMediaSynchronizer
{
    public function __construct(private readonly NdiMediaInheritanceReconciler $mediaInheritance) {}

    /** @return array{downloaded: int, failed: int, assigned: int} */
    public function sync(int $limit = 25, bool $force = false): array
    {
        $limit = max(1, $limit);
        $this->mediaInheritance->reconcile();
        $summary = ['downloaded' => 0, 'failed' => 0, 'assigned' => $this->reconcileDownloadedSources()];
        $sourceQuery = ProductMediaSource::query()
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('id');

        $targetUrls = [];

        // Stream candidates so a large retry backlog does not have to be
        // loaded into memory merely to select this run's bounded URL batch.
        foreach ($sourceQuery->cursor() as $source) {
            if (! $this->retryDue($source, $force) || isset($targetUrls[$source->source_url])) {
                continue;
            }

            $targetUrls[$source->source_url] = true;

            if (count($targetUrls) >= $limit) {
                break;
            }
        }

        $sources = ProductMediaSource::query()
            ->whereIn('source_url', array_keys($targetUrls))
            ->whereIn('status', ['pending', 'failed'])
            ->get()
            ->filter(fn (ProductMediaSource $source): bool => $this->retryDue($source, $force))
            ->groupBy('source_url');

        foreach ($sources as $url => $group) {
            try {
                $asset = $this->download((string) $url);
                $summary['downloaded']++;
                $summary['assigned'] += $this->markDownloaded($group, $asset);
            } catch (\Throwable $exception) {
                $this->markFailed($group, $exception);
                $summary['failed']++;
            }
        }

        return $summary;
    }

    private function retryDue(ProductMediaSource $source, bool $force = false): bool
    {
        if ($source->status === 'pending' || $force || $source->last_attempted_at === null) {
            return true;
        }

        $baseMinutes = max(1, (int) config('ndi.media_retry_after_minutes', 60));
        $maxMinutes = max($baseMinutes, (int) config('ndi.media_retry_max_minutes', 24 * 60));
        $exponent = min(20, max(0, $source->attempt_count - 1));
        $minutes = min($maxMinutes, $baseMinutes * (2 ** $exponent));

        return $source->last_attempted_at->addMinutes($minutes)->isPast();
    }

    /**
     * Reconcile already-downloaded sources before selecting remote work.
     * Missing or corrupt supplier files are put back into the normal download
     * pipeline, while a missing assignment is repaired without touching an
     * administrator's explicit override or suppression.
     */
    private function reconcileDownloadedSources(): int
    {
        $assigned = 0;
        $assetValidity = [];

        foreach (ProductMediaSource::query()->where('status', 'downloaded')->with(['mediaAsset', 'product'])->lazyById(500) as $source) {
            $asset = $source->mediaAsset;
            $assetId = $asset?->getKey();

            if ($asset !== null && ! $asset->is_active) {
                // Supplier assets are protected from deletion in the admin.
                // If one is nevertheless deactivated operationally, treat that
                // as an explicit display suppression rather than retrying the
                // same immutable bytes forever.
                $source->update(['is_suppressed' => true]);

                continue;
            }

            $valid = $assetId !== null
                ? ($assetValidity[$assetId] ??= $this->localAssetIsValid($asset))
                : false;

            if (! $valid) {
                DB::transaction(function () use ($source, $assetId): void {
                    if ($assetId !== null) {
                        MediaAssignment::query()
                            ->where('assignable_type', $source->product::class)
                            ->where('assignable_id', $source->product_id)
                            ->where('role', 'primary')
                            ->where('media_asset_id', $assetId)
                            ->delete();
                    }

                    $source->update([
                        'status' => 'pending',
                        'attempt_count' => 0,
                        'last_error' => 'Local supplier media is missing or corrupt.',
                        'last_attempted_at' => null,
                        'downloaded_at' => null,
                    ]);
                });

                continue;
            }

            $refreshDays = max(1, (int) config('ndi.media_refresh_days', 7));

            if ($source->downloaded_at === null || $source->downloaded_at->addDays($refreshDays)->isPast()) {
                // Keep the currently valid assignment visible while the URL is
                // refreshed. A failed refresh is observable and retryable, but
                // it must not turn a good local image into a storefront gap.
                $source->update([
                    'status' => 'pending',
                    'attempt_count' => 0,
                    'last_error' => null,
                    'last_attempted_at' => null,
                ]);

                continue;
            }

            if ($source->is_suppressed) {
                continue;
            }

            $primary = MediaAssignment::query()
                ->where('assignable_type', $source->product::class)
                ->where('assignable_id', $source->product_id)
                ->where('role', 'primary')
                ->first();

            if ($primary === null) {
                MediaAssignment::query()->create([
                    'assignable_type' => $source->product::class,
                    'assignable_id' => $source->product_id,
                    'role' => 'primary',
                    'media_asset_id' => $assetId,
                ]);
                $assigned++;
            }
        }

        return $assigned;
    }

    private function localAssetIsValid(MediaAsset $asset): bool
    {
        if ($asset->disk !== 'public') {
            return false;
        }

        try {
            $disk = Storage::disk($asset->disk);

            return $disk->exists($asset->path)
                && is_file($disk->path($asset->path))
                && hash_file('sha256', $disk->path($asset->path)) === $asset->sha256;
        } catch (\Throwable) {
            return false;
        }
    }

    private function download(string $sourceUrl): MediaAsset
    {
        $url = NdiMediaUrl::canonicalize($sourceUrl);

        if ($url === null || $url !== $sourceUrl) {
            throw new RuntimeException('Rejected NDI media source URL.');
        }

        $disk = Storage::disk('public');
        $temporaryFile = tempnam(sys_get_temp_dir(), 'ndi-media-');

        if ($temporaryFile === false) {
            throw new RuntimeException('Unable to create an NDI image temporary file.');
        }

        try {
            $limit = (int) config('ndi.media_max_bytes', 5 * 1024 * 1024);
            $response = Http::accept('image/png,image/jpeg,image/webp')
                ->withoutRedirecting()
                ->connectTimeout(5)
                ->timeout(20)
                ->withOptions($this->downloadOptions($limit))
                ->sink($temporaryFile)
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException('NDI image returned HTTP '.$response->status().'.');
            }

            $size = is_file($temporaryFile) ? filesize($temporaryFile) : false;

            if ($size === false || $size < 1 || $size > $limit) {
                throw new RuntimeException('NDI image has an invalid byte size.');
            }

            $expectedMime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

            if (! isset($allowed[$expectedMime])) {
                throw new RuntimeException('NDI image has an unsupported content type.');
            }

            $dimensions = @getimagesize($temporaryFile);

            if ($dimensions === false || ! isset($dimensions['mime']) || $dimensions['mime'] !== $expectedMime) {
                throw new RuntimeException('NDI image bytes do not match their declared content type.');
            }

            if ((int) $dimensions[0] < 1 || (int) $dimensions[1] < 1
                || (float) $dimensions[0] * (float) $dimensions[1] > (int) config('ndi.media_max_pixels', 20_000_000)) {
                throw new RuntimeException('NDI image dimensions exceed the allowed pixel limit.');
            }

            $sha256 = hash_file('sha256', $temporaryFile);
            $existing = MediaAsset::query()->where('sha256', $sha256)->whereNull('uploader_id')->first();
            $extension = $allowed[$expectedMime];
            $path = 'media/ndi/'.$sha256.'.'.$extension;

            if ($existing !== null) {
                if (! $existing->is_active) {
                    throw new RuntimeException('Matching supplier media is inactive.');
                }

                if ($existing->disk === 'public' && $existing->path === $path && $disk->exists($path)
                    && hash_file('sha256', $disk->path($path)) === $sha256) {
                    return $existing;
                }
            }

            $disk->makeDirectory('media/ndi');

            if (! $disk->exists($path) || hash_file('sha256', $disk->path($path)) !== $sha256) {
                $publicTemporary = $disk->path('media/ndi/.'.$sha256.'.'.Str::uuid().'.tmp');

                if (! copy($temporaryFile, $publicTemporary) || ! rename($publicTemporary, $disk->path($path))) {
                    @unlink($publicTemporary);
                    throw new RuntimeException('Unable to atomically store the validated NDI image.');
                }
            }

            if ($existing !== null) {
                $existing->update([
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => 'ndi-'.$sha256.'.'.$extension,
                    'mime' => $expectedMime,
                    'byte_size' => $size,
                    'width' => (int) $dimensions[0],
                    'height' => (int) $dimensions[1],
                ]);

                return $existing;
            }

            return MediaAsset::query()->firstOrCreate(
                ['sha256' => $sha256, 'uploader_id' => null],
                [
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => 'ndi-'.$sha256.'.'.$extension,
                    'mime' => $expectedMime,
                    'byte_size' => $size,
                    'width' => (int) $dimensions[0],
                    'height' => (int) $dimensions[1],
                    'alt_nb' => 'Produktbilde',
                    'alt_ru' => 'Изображение товара',
                    'is_active' => true,
                ],
            );
        } finally {
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    /** @return array{on_headers: callable, progress: callable} */
    private function downloadOptions(int $limit): array
    {
        return [
            'on_headers' => static function (ResponseInterface $response) use ($limit): void {
                $contentLength = $response->getHeaderLine('Content-Length');

                if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > $limit) {
                    throw new RuntimeException('NDI image exceeds the allowed download size.');
                }
            },
            'progress' => static function (int $downloadTotal, int $downloadedBytes) use ($limit): void {
                if ($downloadTotal > $limit || $downloadedBytes > $limit) {
                    throw new RuntimeException('NDI image exceeded the allowed download size.');
                }
            },
        ];
    }

    /** @param Collection<int, ProductMediaSource> $sources */
    private function markDownloaded($sources, MediaAsset $asset): int
    {
        return DB::transaction(function () use ($sources, $asset): int {
            $assigned = 0;

            foreach ($sources as $source) {
                $previousSupplierAssetId = $source->media_asset_id;
                $source->update([
                    'media_asset_id' => $asset->id,
                    'status' => 'downloaded',
                    'attempt_count' => $source->attempt_count + 1,
                    'last_error' => null,
                    'last_attempted_at' => now(),
                    'downloaded_at' => now(),
                ]);

                if ($source->is_suppressed) {
                    continue;
                }

                $primary = MediaAssignment::query()
                    ->where('assignable_type', $source->product::class)
                    ->where('assignable_id', $source->product_id)
                    ->where('role', 'primary')
                    ->with('asset')
                    ->first();

                // Any different primary assignment is an intentional override,
                // regardless of its uploader. Only replace this source's own
                // previously assigned supplier media.
                if ($primary !== null && $primary->media_asset_id !== $previousSupplierAssetId) {
                    continue;
                }

                MediaAssignment::query()->updateOrCreate(
                    ['assignable_type' => $source->product::class, 'assignable_id' => $source->product_id, 'role' => 'primary'],
                    ['media_asset_id' => $asset->id],
                );
                $assigned++;
            }

            return $assigned;
        });
    }

    /** @param Collection<int, ProductMediaSource> $sources */
    private function markFailed($sources, \Throwable $exception): void
    {
        foreach ($sources as $source) {
            $source->update([
                'status' => 'failed',
                'attempt_count' => $source->attempt_count + 1,
                'last_error' => Str::limit($exception->getMessage(), 255, ''),
                'last_attempted_at' => now(),
            ]);
        }
    }
}
