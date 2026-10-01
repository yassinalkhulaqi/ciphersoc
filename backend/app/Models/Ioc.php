<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ioc extends Model
{
    protected $fillable = ['type', 'value', 'normalized_value', 'confidence', 'severity', 'source', 'status', 'first_seen_at', 'last_seen_at', 'expires_at', 'tags', 'notes', 'threat_score', 'reputation', 'created_by'];

    protected $casts = ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'expires_at' => 'datetime', 'tags' => 'array'];

    public function enrichments()
    {
        return $this->hasMany(IocEnrichment::class);
    }

    public static function normalize(string $type, string $value): string
    {
        $v = trim($value);
        if (in_array($type, ['domain', 'hostname'])) {
            return strtolower(rtrim($v, '.'));
        }
        if (in_array($type, ['hash', 'file_hash'])) {
            return strtolower($v);
        }
        if ($type === 'url') {
            $p = parse_url($v);
            if (! $p) {
                return $v;
            } $s = (isset($p['scheme']) ? strtolower($p['scheme']).'://' : '').strtolower($p['host'] ?? '').($p['path'] ?? '');

            return rtrim($s, '/');
        }
        if (in_array($type, ['ipv4', 'ipv6', 'ip'])) {
            $ip = trim($v);
            // strip leading zeros per octet so filter_var accepts e.g. 010.000.000.001
            if (preg_match('/^[0-9.]+$/', $ip)) {
                $parts = explode('.', $ip);
                if (count($parts) === 4) {
                    $ip = implode('.', array_map(fn ($p) => (string) max(0, (int) $p), $parts));
                }
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $parts = explode('.', $ip);

                return implode('.', array_map('intval', $parts));
            }
            $packed = @inet_pton($ip);
            if ($packed !== false) {
                return strtolower(@inet_ntop($packed) ?: $ip);
            }

return $ip;
        }
        if ($type === 'email') {
            return strtolower($v);
        }

        return $v;
    }
}
