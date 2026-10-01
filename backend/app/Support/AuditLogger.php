<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function log(string $action, ?string $resourceType = null, mixed $resourceId = null, ?array $old = null, ?array $new = null, array $metadata = []): void
    {
        try {
            $req = request();
            AuditLog::create([
                'actor_id' => Auth::id(),
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId !== null ? (string) $resourceId : null,
                'ip_address' => $req?->ip(),
                'user_agent' => substr((string) ($req?->userAgent() ?? ''), 0, 500),
                'old_values' => self::scrub($old),
                'new_values' => self::scrub($new),
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('audit log failed: '.$e->getMessage());
        }
    }

    private static function scrub(?array $data): ?array
    {
        if (! $data) {
            return $data;
        }
        foreach (['password', 'password_confirmation', 'token', 'api_key', 'secret', 'current_password'] as $k) {
            unset($data[$k]);
        }

        return $data;
    }
}
