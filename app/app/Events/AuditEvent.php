<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class AuditEvent
{
    use Dispatchable;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $name,
        public ?string $orderId,
        public ?int $actorId,
        public string $actorType,
        public array $payload = []
    ) {}
}
