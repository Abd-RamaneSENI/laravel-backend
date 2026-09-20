<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(Request $request, string $event, ?Model $subject = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => [...$metadata, 'ip_hash' => hash('sha256', (string) $request->ip())],
        ]);
    }
}
