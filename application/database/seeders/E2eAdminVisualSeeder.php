<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use LogicException;

class E2eAdminVisualSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['testing', 'local'])) {
            throw new LogicException('Admin visual fixtures are restricted to testing/local.');
        }

        $statuses = [
            ServiceRequestStatus::New,
            ServiceRequestStatus::Contacted,
            ServiceRequestStatus::Closed,
            ServiceRequestStatus::Cancelled,
        ];

        foreach (range(1, 37) as $index) {
            $status = $statuses[($index - 1) % count($statuses)];

            ServiceRequest::query()->updateOrCreate(
                ['reference' => sprintf('SR-VISUAL-%04d', $index)],
                [
                    'locale' => $index % 3 === 0 ? 'ru' : 'nb',
                    'service_slug' => $index % 2 === 0 ? 'oljeskift' : 'omlegging-balansering',
                    'service_variant' => $index % 2 === 0 ? null : 'mounting_balancing',
                    'fulfilment' => $index % 2 === 0 ? 'workshop' : 'mobile',
                    'preferred_date' => now()->addDays($index)->toDateString(),
                    'preferred_window' => $index % 2 === 0 ? '08:00–12:00' : '12:00–16:00',
                    'vehicle' => $index === 1
                        ? 'Volkswagen Transporter Lang Modellbetegnelse for visuell testing'
                        : 'Volvo XC60',
                    'registration_number' => sprintf('QA%05d', $index),
                    'tire_size' => '235/55 R19',
                    'wheel_count' => 4,
                    'dekkhotell' => $index % 2 === 0,
                    'additional_services' => $index % 2 === 0 ? ['air_filter'] : null,
                    'name' => $index === 1
                        ? 'Visuell Testkunde med et bevisst langt navn'
                        : sprintf('Visuell Testkunde %02d', $index),
                    'email' => $index === 1
                        ? 'langt-administrativt-testnavn@synthetic.example.test'
                        : sprintf('admin-visual-%02d@example.test', $index),
                    'phone' => sprintf('+47 900 00 %03d', $index),
                    'address' => 'Syntetisk testadresse 1, 9000 Tromsø',
                    'note' => $index === 1
                        ? 'Syntetisk lang tekst som brukes for å kontrollere wrapping, lesbarhet og at handlinger ikke blir skjult i administrasjonen.'
                        : null,
                    'status' => $status,
                    'admin_note' => $status === ServiceRequestStatus::Contacted ? 'Syntetisk oppfølging registrert.' : null,
                    'consent_at' => now()->subMinutes($index),
                ],
            );
        }

        $admin = User::query()->where('email', 'issue-41-admin@example.test')->firstOrFail();
        $path = 'media/issue-41-removable-fixture.png';
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        if ($bytes === false) {
            throw new LogicException('Unable to decode the admin visual media fixture.');
        }

        Storage::disk('public')->put($path, $bytes);
        MediaAsset::query()->updateOrCreate(
            ['path' => $path],
            [
                'disk' => 'public',
                'original_name' => 'issue-41-removable-fixture.png',
                'mime' => 'image/png',
                'byte_size' => strlen($bytes),
                'width' => 1,
                'height' => 1,
                'sha256' => hash('sha256', $bytes),
                'alt_nb' => 'Syntetisk slettbar mediefil',
                'alt_ru' => 'Синтетический удаляемый медиафайл',
                'uploader_id' => $admin->id,
                'is_active' => true,
            ],
        );
    }
}
