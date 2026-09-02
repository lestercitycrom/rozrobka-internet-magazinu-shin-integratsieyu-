<?php

namespace App\Modules\Content\ReadModel;

use Illuminate\Support\Collection;

final class ServiceMediaCatalog
{
    /** @return array<string, array{kind: string, url: ?string, alt: string, crop: string, source: string, feature: bool}> */
    public function inventory(Collection $services): array
    {
        return $services
            ->mapWithKeys(fn (ServicePageReadItem $service): array => [$service->slug => $this->for($service)])
            ->all();
    }

    /** @return array{kind: string, url: ?string, alt: string, crop: string, source: string, feature: bool} */
    public function for(ServicePageReadItem $service): array
    {
        $managedUrl = data_get($service->hero, 'url');
        if (is_string($managedUrl) && $managedUrl !== '') {
            return [
                'kind' => 'managed',
                'url' => $managedUrl,
                'alt' => (string) (data_get($service->hero, 'alt') ?: $service->title),
                'crop' => 'managed',
                'source' => 'managed-approved-service-hero',
                'feature' => false,
            ];
        }

        /** @var array<string, mixed> $presentation */
        $presentation = config('service_media.services.'.$service->slug, config('service_media.fallback'));
        $assetKey = data_get($presentation, 'asset');

        if (! is_string($assetKey) || $assetKey === '') {
            return [
                'kind' => 'fallback',
                'url' => null,
                'alt' => '',
                'crop' => (string) data_get($presentation, 'fallback', 'local-service'),
                'source' => 'honest-approved-media-unavailable',
                'feature' => false,
            ];
        }

        /** @var array<string, string>|null $asset */
        $asset = config('service_media.assets.'.$assetKey);
        if (! is_array($asset) || ! isset($asset['file'], $asset['source'])) {
            return [
                'kind' => 'fallback',
                'url' => null,
                'alt' => '',
                'crop' => 'local-service',
                'source' => 'honest-approved-media-unavailable',
                'feature' => false,
            ];
        }

        return [
            'kind' => 'repository',
            'url' => asset('/brand/'.$asset['file']),
            'alt' => $service->title,
            'crop' => (string) data_get($presentation, 'crop', 'center'),
            'source' => $asset['source'],
            'feature' => (bool) data_get($presentation, 'feature', false),
        ];
    }
}
