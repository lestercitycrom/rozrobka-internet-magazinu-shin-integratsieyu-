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
    /** @return array{downloaded: int, failed: int, assigned: int} */
    public function sync(int $limit = 25): array
    {
        $limit = max(1, $limit);
        $sourceQuery = ProductMediaSource::query()
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('id');

        $targetUrls = [];

        // Stream candidates so a large retry backlog does not have to be
        // loaded into memory merely to select this run's bounded URL batch.
        foreach ($sourceQuery->cursor() as $source) {
            if (! $this->retryDue($source) || isset($targetUrls[$source->source_url])) {
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
            ->filter(fn (ProductMediaSource $source): bool => $this->retryDue($source))
            ->groupBy('source_url');

        $summary = ['downloaded' => 0, 'failed' => 0, 'assigned' => 0];

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

    private function retryDue(ProductMediaSource $source): bool
    {
        if ($source->status === 'pending') {
            return true;
        }

        $maxAttempts = max(1, (int) config('ndi.media_max_attempts', 5));

        if ($source->attempt_count >= $maxAttempts || $source->last_attempted_at === null) {
            return false;
        }

        $baseMinutes = max(1, (int) config('ndi.media_retry_after_minutes', 60));
        $minutes = min(24 * 60, $baseMinutes * (2 ** max(0, $source->attempt_count - 1)));

        return $source->last_attempted_at->addMinutes($minutes)->isPast();
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
