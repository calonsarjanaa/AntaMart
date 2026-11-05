<?php

use App\Enums\RefundStatus;

test('refund status correctly identifies pending and refunded states', function () {
    expect(RefundStatus::NotRequested->isPending())->toBeFalse()
        ->and(RefundStatus::NotRequested->isRefunded())->toBeFalse();

    expect(RefundStatus::ManualRefundPending->isPending())->toBeTrue()
        ->and(RefundStatus::ManualRefundPending->isRefunded())->toBeFalse();

    expect(RefundStatus::Refunded->isPending())->toBeFalse()
        ->and(RefundStatus::Refunded->isRefunded())->toBeTrue();
});

