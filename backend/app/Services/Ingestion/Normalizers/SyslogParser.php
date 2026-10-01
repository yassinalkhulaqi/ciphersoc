<?php

namespace App\Services\Ingestion\Normalizers;

class SyslogParser
{
    public function parse(string $line): array
    {
        $out = ['source_type' => 'syslog', 'event_type' => 'system', 'severity' => 'info', 'message' => $line];
        // <pri>MMM DD HH:MM:SS host proc[pid]: msg
        if (preg_match('/^(?:<\d+>)?(\w{3}\s+\d+\s+[\d:]+)\s+(\S+)\s+([^\[:]+)(?:\[(\d+)\])?:\s*(.*)$/', $line, $m)) {
            $out['hostname'] = $m[2];
            $out['process_name'] = trim($m[3]);
            $out['process_id'] = is_numeric($m[4] ?? null) ? (int) $m[4] : null;
            $out['message'] = $m[5];
        }
        // SSH auth signals
        if (stripos($line, 'Failed password') !== false || stripos($line, 'authentication failure') !== false) {
            $out['event_type'] = 'authentication_failure';
            $out['severity'] = 'medium';
            $out['action'] = 'login';
            $out['status'] = 'failure';
            if (preg_match('/for (?:invalid user )?(\S+) from (\d+\.\d+\.\d+\.\d+)/', $line, $mm)) {
                $out['username'] = $mm[1];
                $out['source_ip'] = $mm[2];
            }
        } elseif (stripos($line, 'Accepted password') !== false || stripos($line, 'Accepted publickey') !== false) {
            $out['event_type'] = 'authentication_success';
            $out['severity'] = 'info';
            $out['action'] = 'login';
            $out['status'] = 'success';
            if (preg_match('/for (\S+) from (\d+\.\d+\.\d+\.\d+)/', $line, $mm)) {
                $out['username'] = $mm[1];
                $out['source_ip'] = $mm[2];
            }
        } elseif (stripos($line, 'sudo') !== false) {
            $out['event_type'] = 'privilege_escalation';
            $out['severity'] = 'medium';
        }

        return $out;
    }
}
