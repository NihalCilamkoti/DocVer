<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use Illuminate\Http\Request;

class AuditService
{
    public function log(string $eventType, ?int $workspaceId = null, ?int $userId = null, array $payload = [], ?Request $request = null): AuditEvent
    {
        $ip = $request ? $request->ip() : request()->ip();
        $userAgent = $request ? $request->userAgent() : request()->userAgent();

        return AuditEvent::create([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
