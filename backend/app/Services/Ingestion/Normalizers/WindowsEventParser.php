<?php

namespace App\Services\Ingestion\Normalizers;

class WindowsEventParser
{
    private const MAP = [4624 => 'authentication_success', 4625 => 'authentication_failure', 4672 => 'privilege_escalation', 4720 => 'account_created', 4732 => 'group_membership_change', 4688 => 'process_creation', 4104 => 'powershell_script', 4103 => 'powershell_script', 1 => 'process_creation', 3 => 'network_connection', 11 => 'file_created', 13 => 'registry_modified'];

    public function parse(array $raw): array
    {
        $eventId = $raw['EventID'] ?? $raw['event_id'] ?? $raw['EventId'] ?? null;
        $out = ['source_type' => 'windows_event', 'event_type' => self::MAP[$eventId] ?? ($raw['event_type'] ?? 'generic'), 'severity' => 'info', 'message' => $raw['Message'] ?? $raw['message'] ?? json_encode($raw)];
        $out['hostname'] = $raw['Computer'] ?? $raw['hostname'] ?? $raw['computer'] ?? null;
        $out['username'] = $raw['TargetUserName'] ?? $raw['username'] ?? $raw['AccountName'] ?? null;
        $out['source_ip'] = $raw['IpAddress'] ?? $raw['source_ip'] ?? ($raw['WorkstationName'] ?? null);
        $out['process_name'] = $raw['NewProcessName'] ?? $raw['ProcessName'] ?? $raw['Image'] ?? null;
        $out['process_id'] = isset($raw['ProcessId']) && is_numeric($raw['ProcessId']) ? (int) $raw['ProcessId'] : null;
        $out['parent_process'] = $raw['ParentImage'] ?? null;
        $out['command_line'] = $raw['CommandLine'] ?? null;
        if (($eventId == 4625)) {
            $out['severity'] = 'medium';
            $out['action'] = 'login';
            $out['status'] = 'failure';
        }
        if (($eventId == 4624)) {
            $out['action'] = 'login';
            $out['status'] = 'success';
        }
        if (($eventId == 4720)) {
            $out['severity'] = 'high';
        }
        if (($eventId == 4104 || $eventId == 4103)) {
            $out['severity'] = 'high';
            $out['event_type'] = 'powershell_script';
        }
        if (($eventId == 4688)) {
            $out['severity'] = 'medium';
            $out['event_type'] = 'process_creation';
        }

        return $out;
    }
}
