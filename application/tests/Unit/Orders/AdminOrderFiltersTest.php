<?php

use App\Modules\Orders\AdminOrderFilters;
use App\Modules\Orders\Enums\OrderStatus;

test('admin order filters normalize declared enum values', function () {
    $f = AdminOrderFilters::from(['status' => 'pending_review', 'q' => '  ET-1  ', 'from' => '2026-01-01']);
    expect($f->status)->toBe(OrderStatus::PendingReview)->and($f->query)->toBe('ET-1');
});
test('admin order filters reject arbitrary enum and date values', function () {
    expect(fn () => AdminOrderFilters::from(['status' => 'bad']))->toThrow(InvalidArgumentException::class);
    expect(fn () => AdminOrderFilters::from(['from' => 'yesterday']))->toThrow(InvalidArgumentException::class);
});
