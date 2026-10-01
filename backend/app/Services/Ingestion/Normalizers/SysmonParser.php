<?php

namespace App\Services\Ingestion\Normalizers;

// Sysmon (EventID 1/3/7/11/13 + PowerShell 4103/4104) in XML-converted JSON or winlogbeat form.
class SysmonParser
{
    private const MAP = [
        1 => 'process_creation',
        3 => 'network_connection',
        7 => 'image_loaded',
        11 => 'file_created',
        13 => 'registry_modified',
        22 => 'dns_query',
        4103 => 'powershell_script',
        4104 => 'powershell_script',
    ];

    public function supports(array $raw): bool
    {
        $provider = strtolower((string) ($raw['Provider'] ?? ($raw['provider_name'] ?? ($raw['winlog']['provider_name'] ?? ''))));
        $channel = strtolower((string) ($raw['Channel'] ?? ($raw['winlog']['channel'] ?? '')));
        $id = $raw['EventID'] ?? ($raw['event_id'] ?? ($raw['winlog']['event_id'] ?? null));

        return str_contains($provider, 'sysmon') || str_contains($channel, 'sysmon') || (in_array((int) $id, [1, 3, 7, 11, 13, 22]) && $this->looksLikeSysmon($raw));
    }

    public function parse(array $raw): array
    {
        $data = $raw['EventData'] ?? ($raw['event_data'] ?? ($raw['winlog']['event_data'] ?? $raw));
        if (! is_array($data)) {
            $data = [];
        }
        $id = (int) ($raw['EventID'] ?? ($raw['event_id'] ?? ($raw['winlog']['event_id'] ?? 0)));
        $type = self::MAP[$id] ?? 'generic';

        return [
            'source_type' => 'sysmon',
            'event_type' => $type,
            'severity' => $type === 'powershell_script' ? 'high' : ($type === 'process_creation' ? 'medium' : 'info'),
            'message' => $raw['Message'] ?? ($raw['message'] ?? json_encode($raw)),
            'hostname' => $raw['Computer'] ?? ($raw['hostname'] ?? ($raw['host']['hostname'] ?? null)),
            'username' => $data['User'] ?? ($data['TargetUserName'] ?? null),
            'source_ip' => $data['SourceIp'] ?? ($data['IpAddress'] ?? null),
            'destination_ip' => $data['DestinationIp'] ?? null,
            'destination_port' => isset($data['DestinationPort']) && is_numeric($data['DestinationPort']) ? (int) $data['DestinationPort'] : null,
            'protocol' => $data['Protocol'] ?? null,
            'process_name' => $data['Image'] ?? ($data['ProcessName'] ?? null),
            'process_id' => isset($data['ProcessId']) && is_numeric($data['ProcessId']) ? (int) $data['ProcessId'] : null,
            'parent_process' => $data['ParentImage'] ?? ($data['ParentCommandLine'] ?? null),
            'command_line' => $data['CommandLine'] ?? ($data['ParentCommandLine'] ?? null),
            'file_path' => $data['TargetFilename'] ?? ($data['TargetObject'] ?? null),
            'hash' => $this->pickHash($data),
            'url' => null,
            'domain' => $data['QueryName'] ?? null,
        ];
    }

    private function looksLikeSysmon(array $raw): bool
    {
        $keys = implode(' ', array_keys($raw));
        foreach (['Image', 'ParentImage', 'CommandLine', 'TargetFilename', 'DestinationIp'] as $k) {
            if (str_contains($keys, $k)) {
                return true;
            }
        }

        return false;
    }

    private function pickHash(array $data): ?string
    {
        foreach (['SHA256', 'Hashes', 'SHA1', 'MD5', 'IMPHASH'] as $k) {
            if (! empty($data[$k]) && is_string($data[$k])) {
                if ($k === 'Hashes' && preg_match('/SHA256=([A-Fa-f0-9]{64})/', $data[$k], $m)) {
                    return strtolower($m[1]);
                }
                if (preg_match('/^[A-Fa-f0-9]{32,64}$/', $data[$k])) {
                    return strtolower($data[$k]);
                }
            }
        }

        return null;
    }
}
