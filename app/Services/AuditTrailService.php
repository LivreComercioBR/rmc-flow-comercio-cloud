<?php

namespace App\Services;

use App\Models\CompanyAuditEvent;

final class AuditTrailService
{
    public function record(int $companyId, string $eventKey, string $description, ?int $actorId = null, ?string $subjectType = null, ?int $subjectId = null, array $metadata = [], ?int $approvedBy = null): CompanyAuditEvent
    {
        $request = request();
        return CompanyAuditEvent::create([
            'company_id' => $companyId,
            'actor_id' => $actorId,
            'approved_by' => $approvedBy,
            'event_key' => $eventKey,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
