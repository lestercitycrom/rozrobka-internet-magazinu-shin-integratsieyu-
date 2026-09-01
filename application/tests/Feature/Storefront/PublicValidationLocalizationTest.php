<?php

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Orders\Models\Order;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Database\Seeders\E2eStorefrontSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function publicValidationCases(): array
{
    return [
        'required' => [['name' => null], ['name' => ['required']]],
        'accepted' => [['consent' => false], ['consent' => ['accepted']]],
        'after_or_equal' => [['date' => now()->subDay()->toDateString()], ['date' => ['after_or_equal:today']]],
        'array' => [['additional_services' => 'air_filter'], ['additional_services' => ['array']]],
        'between.numeric' => [['wheel_count' => 5], ['wheel_count' => ['numeric', 'between:1,4']]],
        'boolean' => [['dekkhotell' => 'perhaps'], ['dekkhotell' => ['boolean']]],
        'date' => [['preferred_date' => 'not-a-date'], ['preferred_date' => ['date']]],
        'date_format' => [['date' => '01/09/2026'], ['date' => ['date_format:Y-m-d']]],
        'distinct' => [['additional_services' => ['air_filter', 'air_filter']], ['additional_services.*' => ['distinct']]],
        'email' => [['email' => 'not-an-email'], ['email' => ['email']]],
        'in' => [['fulfilment' => 'somewhere'], ['fulfilment' => ['in:workshop,mobile']]],
        'integer' => [['quantity' => 1.5], ['quantity' => ['integer']]],
        'max.array' => [['additional_services' => ['a', 'b', 'c', 'd']], ['additional_services' => ['max:3']]],
        'max.numeric' => [['quantity' => 41], ['quantity' => ['numeric', 'max:40']]],
        'max.string' => [['note' => str_repeat('a', 11)], ['note' => ['string', 'max:10']]],
        'min.numeric' => [['quantity' => 0], ['quantity' => ['numeric', 'min:1']]],
        'prohibited' => [['website' => 'bot'], ['website' => ['prohibited']]],
        'regex' => [['sku' => '<script>'], ['sku' => ['regex:/^[A-Za-z0-9._-]{1,80}$/']]],
        'required_if' => [['fulfilment' => 'mobile'], ['address' => ['required_if:fulfilment,mobile']]],
        'string' => [['name' => ['not text']], ['name' => ['string']]],
        'uuid' => [['submission_token' => 'not-a-uuid'], ['submission_token' => ['uuid']]],
    ];
}

function expectNaturalPublicMessages(array $messages, string $locale): void
{
    expect($messages)->not->toBeEmpty();

    foreach ($messages as $message) {
        expect($message)
            ->not->toContain(':attribute')
            ->not->toContain('validation.')
            ->not->toMatch('/^[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+$/i');

        if ($locale === 'ru') {
            expect($message)->toMatch('/[А-Яа-яЁё]/u');
        } else {
            expect($message)->toMatch('/[A-Za-zÆØÅæøå]/u');
        }
    }
}

function renderedPublicValidationMessages(string $html): array
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $nodes = (new DOMXPath($document))->query(
        '//*[contains(concat(" ", normalize-space(@class), " "), " et-form-error ") or @data-testid="request-errors"]'
    );
    $messages = [];
    foreach ($nodes ?: [] as $node) {
        $message = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
        if ($message !== '') {
            $messages[] = $message;
        }
    }

    return array_values(array_unique($messages));
}

test('all public validation rules and attributes have bounded Bokmål and Russian coverage', function (string $locale): void {
    app()->setLocale($locale);

    foreach (publicValidationCases() as $rule => [$data, $rules]) {
        $validator = Validator::make($data, $rules);
        expect($validator->fails(), "The synthetic {$rule} case must fail.")->toBeTrue();
        expectNaturalPublicMessages($validator->errors()->all(), $locale);
    }

    $requiredAttributes = [
        'submission_token', 'idempotency_key', 'service_variant', 'fulfilment', 'preferred_date',
        'preferred_window', 'booking_slot_key', 'car_count', 'vehicle', 'registration_number',
        'tire_size', 'vehicle_type', 'rim_band', 'storage_plan', 'vehicle_2',
        'registration_number_2', 'tire_size_2', 'vehicle_type_2', 'rim_band_2', 'storage_plan_2',
        'wheel_count', 'dekkhotell', 'additional_services', 'additional_services.*', 'name',
        'email', 'phone', 'address', 'note', 'consent', 'terms', 'fitment_confirmation',
        'early_service_start', 'website', 'sku', 'quantity', 'intent', 'code', 'registration',
        'size', 'date', 'transaction_id', 'session_id', 'merchant_reference', 'time',
    ];
    $attributes = trans('validation.attributes');

    expect($attributes)->toBeArray();
    foreach ($requiredAttributes as $attribute) {
        expect($attributes)->toHaveKey($attribute)
            ->and($attributes[$attribute])->toBeString()->not->toBe('');
    }

    $requiredMessage = Validator::make(['name' => null], ['name' => ['required']])->errors()->first('name');
    expect($requiredMessage)->toBe($locale === 'ru' ? 'Заполните поле «имя».' : 'Fyll ut navn.');
})->with(['nb', 'ru']);

test('service validation renders natural localized errors and produces no persistence or outbox work', function (string $locale, string $path, array $expected): void {
    config()->set('storefront.ru_preview_enabled', true);
    Mail::fake();
    ServicePage::query()->create([
        'slug' => 'oljeskift',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'title_nb' => 'Oljeskift',
        'title_ru' => 'Замена масла',
    ]);

    $response = $this->from($path)->followingRedirects()->post($path, [
        'fulfilment' => 'workshop',
        'name' => '',
        'email' => 'invalid',
        'phone' => '123',
    ])->assertOk();

    foreach ($expected as $message) {
        $response->assertSee($message);
    }
    expectNaturalPublicMessages(renderedPublicValidationMessages($response->getContent()), $locale);
    expect($response->getContent())->not->toContain('validation.');
    expect(ServiceRequest::query()->count())->toBe(0)
        ->and(DB::table('service_request_submissions')->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0)
        ->and(DB::table('payment_attempts')->count())->toBe(0)
        ->and(DB::table('payment_operations')->count())->toBe(0);
    Mail::assertNothingQueued();
})->with([
    'Bokmål' => ['nb', '/tjenester/oljeskift/foresporsel', [
        'Fyll ut navn.',
        'Oppgi en gyldig e-postadresse.',
        'Oppgi et gyldig telefonnummer',
        'Du må godta behandlingen av personopplysninger.',
    ]],
    'Russian QA' => ['ru', '/ru/tjenester/oljeskift/foresporsel', [
        'Заполните поле «имя».',
        'Укажите корректный адрес электронной почты.',
        'Укажите корректный номер телефона',
        'Необходимо согласиться на обработку персональных данных.',
    ]],
]);

test('checkout validation renders natural localized errors and creates no order or outbox work', function (string $locale, string $path, array $expected): void {
    config()->set('storefront.ru_preview_enabled', true);
    Mail::fake();
    $this->seed(E2eStorefrontSeeder::class);
    $key = '11111111-1111-4111-8111-111111111111';
    $orderCount = Order::query()->count();
    $outboxCount = DB::table('outbox_messages')->count();
    $paymentAttemptCount = DB::table('payment_attempts')->count();
    $paymentOperationCount = DB::table('payment_operations')->count();

    $response = $this->withSession([
        'storefront.cart.lines' => ['000205550016' => 1],
        'checkout.key' => $key,
    ])->from($path)->followingRedirects()->post($path, [
        'idempotency_key' => $key,
        'name' => '',
        'email' => 'invalid',
        'phone' => '123',
    ])->assertOk();

    foreach ($expected as $message) {
        $response->assertSee($message);
    }
    expectNaturalPublicMessages(renderedPublicValidationMessages($response->getContent()), $locale);
    expect($response->getContent())
        ->not->toContain('validation.')
        ->toContain('data-testid="request-errors"')
        ->toContain('aria-describedby="checkout-name-error"');
    expect(Order::query()->count())->toBe($orderCount)
        ->and(DB::table('outbox_messages')->count())->toBe($outboxCount)
        ->and(DB::table('payment_attempts')->count())->toBe($paymentAttemptCount)
        ->and(DB::table('payment_operations')->count())->toBe($paymentOperationCount);
    Mail::assertNothingQueued();
})->with([
    'Bokmål' => ['nb', '/kasse', [
        'Fyll ut navn.',
        'Oppgi en gyldig e-postadresse.',
        'Oppgi et gyldig telefonnummer',
        'Du må godta behandlingen av personopplysninger.',
        'Du må godta kjøpsvilkårene.',
    ]],
    'Russian QA' => ['ru', '/ru/kasse', [
        'Заполните поле «имя».',
        'Укажите корректный адрес электронной почты.',
        'Укажите корректный номер телефона',
        'Необходимо согласиться на обработку персональных данных.',
        'Необходимо принять условия покупки.',
    ]],
]);
