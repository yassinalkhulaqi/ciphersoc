<?php

namespace App\Services\Ingestion\Normalizers;

// Suricata EVE JSON: alert/dns/http/tls/flow/fileinfo.
class SuricataParser
{
    public function supports(array $raw): bool
    {
        return isset($raw['event_type']) && isset($raw['src_ip']) && isset($raw['flow_id']) || (($raw['event_type'] ?? '') === 'suricata' || isset($raw['alert']));
    }

    public function parse(array $raw): array
    {
        $eve = strtolower((string) ($raw['event_type'] ?? 'alert'));
        $alert = $raw['alert'] ?? [];
        $type = match (true) {
            $eve === 'alert' => 'ids_alert',
            $eve === 'dns' => 'dns_query',
            $eve === 'http' => 'http_request',
            $eve === 'tls' => 'tls_handshake',
            $eve === 'flow' => 'network_connection',
            $eve === 'fileinfo' => 'file_created',
            default => 'ids_alert',
        };

        return [
            'source_type' => 'suricata',
            'event_type' => $type,
            'severity' => $this->mapSev($alert['severity'] ?? null),
            'message' => $alert['signature'] ?? ($raw['dns']['query']['rrname'] ?? ($raw['http']['url'] ?? json_encode($raw))),
            'hostname' => $raw['host'] ?? ($raw['hostname'] ?? null),
            'source_ip' => $raw['src_ip'] ?? null,
            'destination_ip' => $raw['dest_ip'] ?? null,
            'source_port' => isset($raw['src_port']) && is_numeric($raw['src_port']) ? (int) $raw['src_port'] : null,
            'destination_port' => isset($raw['dest_port']) && is_numeric($raw['dest_port']) ? (int) $raw['dest_port'] : null,
            'protocol' => $raw['proto'] ?? null,
            'url' => $raw['http']['url'] ?? null,
            'domain' => $raw['dns']['query']['rrname'] ?? ($raw['tls']['sni'] ?? null),
            'action' => isset($alert['action']) && is_string($alert['action']) ? $alert['action'] : null,
        ];
    }

    private function mapSev(mixed $s): string
    {
        $n = (int) $s;
        if ($n >= 1 && $n <= 3) {
            return [1 => 'critical', 2 => 'high', 3 => 'medium'][$n];
        }

        return 'medium';
    }
}
