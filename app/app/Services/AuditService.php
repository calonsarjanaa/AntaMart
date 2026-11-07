<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function record(
        string $event,
        ?string $orderId,
        ?int $actorId,
        string $actorType,
        array $payload = []
    ): AuditLog {
        return AuditLog::create([
            'order_id'   => $orderId,
            'actor_id'   => $actorId,
            'actor_type' => $actorType,
            'event'      => $event,
            'payload'    => $payload,
        ]);
    }
}
