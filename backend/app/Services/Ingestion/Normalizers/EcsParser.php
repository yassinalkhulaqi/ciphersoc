<?php

namespace App\Services\Ingestion\Normalizers;

// Elastic Common Schema (ECS) subset: filebeat/winlogbeat/metricbeat style docs.
class EcsParser
{
    public function supports(array $raw): bool
    {
        return isset($raw['@timestamp']) || isset($raw['ecs']) || isset($raw['agent']) || isset($raw['event']);
    }

    public function parse(array $raw): array
    {
        $event = $raw['event'] ?? [];
        $src = $raw['source'] ?? [];
        $dst = $raw['destination'] ?? [];
        $host = $raw['host'] ?? [];
        $proc = $raw['process'] ?? [];
        $file = $raw['file'] ?? [];
        $url = $raw['url'] ?? [];

        $action = $event['action'] ?? ($event['type'] ?? null);
        $category = $event['category'] ?? null;
        $type = $this->mapType($category, $action, $event);

        return [
            'source_type' => 'ecs',
            'event_type' => $type,
            'severity' => $this->mapSev($event['severity'] ?? ($raw['log']['level'] ?? 'info')),
            'message' => $raw['message'] ?? json_encode($raw),
            'event_timestamp' => $raw['@timestamp'] ?? null,
            'hostname' => $host['hostname'] ?? ($host['name'] ?? null),
            'username' => $raw['user']['name'] ?? ($raw['user']['id'] ?? null),
            'source_ip' => $src['ip'] ?? null,
            'destination_ip' => $dst['ip'] ?? null,
            'source_port' => isset($src['port']) && is_numeric($src['port']) ? (int) $src['port'] : null,
            'destination_port' => isset($dst['port']) && is_numeric($dst['port']) ? (int) $dst['port'] : null,
            'protocol' => $raw['network']['protocol'] ?? null,
            'process_name' => $proc['name'] ?? ($proc['executable'] ?? null),
            'process_id' => isset($proc['pid']) && is_numeric($proc['pid']) ? (int) $proc['pid'] : null,
            'parent_process' => $proc['parent']['name'] ?? null,
            'command_line' => $proc['command_line'] ?? ($proc['args'] ?? null),
            'file_path' => $file['path'] ?? null,
            'hash' => $file['hash']['sha256'] ?? ($file['hash']['md5'] ?? null),
            'url' => is_array($url) ? ($url['full'] ?? ($url['original'] ?? null)) : $url,
            'domain' => $raw['dns']['question']['name'] ?? null,
            'action' => is_string($action) ? $action : null,
        ];
    }

    private function mapType(mixed $category, mixed $action, array $event): string
    {
        $h = strtolower(json_encode([$category, $action, $event['code'] ?? '', $event['dataset'] ?? '']));
        if (str_contains($h, 'authentication') && str_contains($h, 'fail')) {
            return 'authentication_failure';
        }
        if (str_contains($h, 'authentication') || str_contains($h, 'logon')) {
            return str_contains($h, 'fail') ? 'authentication_failure' : 'authentication_success';
        }
        if (str_contains($h, 'process') && str_contains($h, 'start')) {
            return 'process_creation';
        }
        if (str_contains($h, 'network')) {
            return 'network_connection';
        }
        if (str_contains($h, 'dns')) {
            return 'dns_query';
        }
        if (str_contains($h, 'file')) {
            return 'file_created';
        }
        if (str_contains($h, 'registry')) {
            return 'registry_modified';
        }

        return 'generic';
    }

    private function mapSev(mixed $s): string
    {
        $s = strtolower((string) $s);
        foreach (['critical', 'high', 'medium', 'low'] as $k) {
            if (str_contains($s, $k)) {
                return $k;
            }
        }
        if (in_array($s, ['error', 'err', 'warning', 'warn'])) {
            return $s === 'error' || $s === 'err' ? 'high' : 'medium';
        }

        return 'info';
    }
}
