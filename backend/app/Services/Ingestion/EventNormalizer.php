<?php

namespace App\Services\Ingestion;

use App\Services\Ingestion\Normalizers\CefParser;
use App\Services\Ingestion\Normalizers\GenericJsonParser;
use App\Services\Ingestion\Normalizers\SyslogParser;
use App\Services\Ingestion\Normalizers\WindowsEventParser;

class EventNormalizer
{
    public function normalize(mixed $input, string $hint = 'auto'): array
    {
        $generic = new GenericJsonParser;
        if (is_string($input)) {
            $trim = trim($input);
            if (str_starts_with($trim, 'CEF:')) {
                return array_merge($generic->parse([]), (new CefParser)->parse($trim), ['parser' => 'cef', 'raw_log' => $input]);
            }
            $decoded = json_decode($trim, true);
            if (is_array($decoded)) {
                $input = $decoded;
            } else {
                return array_merge($generic->parse([]), (new SyslogParser)->parse($trim), ['parser' => 'syslog', 'raw_log' => $input]);
            }
        }
        $raw = is_array($input) ? $input : ['message' => (string) $input];
        if ($hint === 'windows' || isset($raw['EventID']) || isset($raw['EventId'])) {
            return array_merge($generic->parse($raw), (new WindowsEventParser)->parse($raw), ['parser' => 'windows_event', 'raw_log' => json_encode($raw)]);
        }
        if (($raw['source_type'] ?? '') === 'syslog' || $hint === 'syslog') {
            return array_merge($generic->parse($raw), (new SyslogParser)->parse($raw['message'] ?? json_encode($raw)), ['parser' => 'syslog', 'raw_log' => json_encode($raw)]);
        }
        $n = $generic->parse($raw);
        $n['parser'] = 'generic_json';
        $n['raw_log'] = json_encode($raw);

        // JSON-wrapped log lines (the agent's main format): when the client did
        // not declare an explicit event_type, sniff the message payload so
        // embedded syslog/CEF lines still classify (SSH, sudo, …).
        if (($n['event_type'] ?? 'generic') === 'generic'
            && ! isset($raw['event_type'])
            && isset($raw['message']) && is_string($raw['message']) && $raw['message'] !== ''
        ) {
            $sniffed = null;
            if (str_starts_with(ltrim($raw['message']), 'CEF:')) {
                $sniffed = (new CefParser)->parse(trim($raw['message']));
                $n['parser'] = 'cef';
            } else {
                $sniffed = (new SyslogParser)->parse($raw['message']);
                if (($sniffed['event_type'] ?? 'system') !== 'system') {
                    $n['parser'] = 'syslog';
                } else {
                    $sniffed = null;
                }
            }
            if (is_array($sniffed)) {
                $n['event_type'] = $sniffed['event_type'];
                foreach (['severity', 'username', 'source_ip', 'destination_ip', 'action', 'status', 'hostname', 'process_name', 'process_id'] as $k) {
                    if (empty($n[$k]) && ! empty($sniffed[$k])) {
                        $n[$k] = $sniffed[$k];
                    }
                }
                if (! isset($raw['severity']) && ! empty($sniffed['severity'])) {
                    $n['severity'] = $sniffed['severity'];
                }
            }
        }

        return $n;
    }
}
