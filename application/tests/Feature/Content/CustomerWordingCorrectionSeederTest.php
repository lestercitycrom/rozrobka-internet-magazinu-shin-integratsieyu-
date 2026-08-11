<?php

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ContentPage;
use App\Modules\Content\Models\ServicePage;
use Database\Seeders\CustomerWordingCorrectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer wording corrections update only known phrases and are idempotent', function (): void {
    $service = ServicePage::query()->create([
        'slug' => 'mobilt-dekkskift',
        'sort_order' => 10,
        'publication_status' => ServicePagePublicationStatus::Published,
        'title_nb' => 'Mobilt dekkskift',
        'title_ru' => 'Замена колёс',
        'excerpt_nb' => 'Én trygg vei til mobilt dekkskift.',
        'body_nb' => 'Bevar denne redaksjonelle setningen. Mobil dekkservice avtales først.',
    ]);
    $content = ContentPage::query()->create([
        'slug' => 'kjopsvilkar',
        'sort_order' => 40,
        'publication_status' => ContentPageStatus::Published,
        'title_nb' => 'Kjøpsvilkår',
        'title_ru' => 'Условия покупки',
        'body_nb' => 'Ved varekjøp vises en ordresammendrag. Gi beskjed minst én dag før.',
    ]);

    $this->seed(CustomerWordingCorrectionSeeder::class);
    $this->seed(CustomerWordingCorrectionSeeder::class);

    expect($service->fresh())
        ->title_nb->toBe('Dekkskift hjemme hos deg')
        ->excerpt_nb->toBe('En trygg vei til dekkskift hjemme hos deg.')
        ->body_nb->toBe('Bevar denne redaksjonelle setningen. Dekkskift hjemme hos deg avtales først.')
        ->and($content->fresh()->body_nb)
        ->toBe('Ved varekjøp vises et ordresammendrag. Gi beskjed minst en dag før.');
});
