<?php

use App\Enums\OrderStatus;

test('pending payment allows transition to processing or expired', function () {
    $status = OrderStatus::PendingPayment;

    expect($status->canTransitionTo(OrderStatus::Processing))->toBeTrue();
    expect($status->canTransitionTo(OrderStatus::Expired))->toBeTrue();
    expect($status->canTransitionTo(OrderStatus::Cancelled))->toBeFalse();
    expect($status->canTransitionTo(OrderStatus::Shipped))->toBeFalse();
    expect($status->canTransitionTo(OrderStatus::Completed))->toBeFalse();
});

test('processing allows transition to cancelled or shipped', function () {
    $status = OrderStatus::Processing;

    expect($status->canTransitionTo(OrderStatus::Cancelled))->toBeTrue();
    expect($status->canTransitionTo(OrderStatus::Shipped))->toBeTrue();
    expect($status->canTransitionTo(OrderStatus::PendingPayment))->toBeFalse();
    expect($status->canTransitionTo(OrderStatus::Expired))->toBeFalse();
    expect($status->canTransitionTo(OrderStatus::Completed))->toBeFalse();
});

test('shipped allows transition only to completed', function () {
    $status = OrderStatus::Shipped;

    expect($status->canTransitionTo(OrderStatus::Completed))->toBeTrue();
    expect($status->canTransitionTo(OrderStatus::Cancelled))->toBeFalse();
    expect($status->canTransitionTo(OrderStatus::Processing))->toBeFalse();
});

test('terminal states allow no further transitions', function (OrderStatus $terminalStatus) {
    foreach (OrderStatus::cases() as $target) {
        expect($terminalStatus->canTransitionTo($target))->toBeFalse();
    }
})->with([
    OrderStatus::Cancelled,
    OrderStatus::Completed,
    OrderStatus::Expired,
]);

