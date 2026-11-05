<?php

use App\Enums\CancellationStatus;

test('cancellation status correctly reports whether it is decided', function () {
    expect(CancellationStatus::Pending->isDecided())->toBeFalse();
    expect(CancellationStatus::Withdrawn->isDecided())->toBeFalse();

    expect(CancellationStatus::Approved->isDecided())->toBeTrue();
    expect(CancellationStatus::Rejected->isDecided())->toBeTrue();
});

