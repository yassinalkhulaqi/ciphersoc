<?php

namespace App\Services\Ingestion\Normalizers;

use Carbon\Carbon;

class GenericJsonParser
{
    public function parse(array $raw): array
    {
        $get = fn ($k, $d = null) => $raw[$k] ?? $d;

        return [
            'event_timestamp' => $this->ts($get('timestamp', $get('@timestamp', $get('event_time')))),
            'source' => $get('source', $get('log_source', 'generic')),
            'source_type' => $get('source_type', 'generic'),
            'event_type' => $get('event_type', $get('event', 'generic')),
            'severity' => strtolower((string) $get('severity', $get('level', 'info'))),
            'message' => $get('message', $get('msg', substr(json_encode($raw), 0, 1000))),
            'username' => $get('username', $get('user', $get('account'))),
            'source_ip' => $get('source_ip', $get('src_ip', $get('srcip'))),
            'destination_ip' => $get('destination_ip', $get('dst_ip', $get('dstip'))),
            'source_port' => $this->intOrNull($get('source_port', $get('src_port'))),
            'destination_port' => $this->intOrNull($get('destination_port', $get('dst_port', $get('dport')))),
            'protocol' => $get('protocol', $get('proto')),
            'process_name' => $get('process_name', $get('process', $get('image'))),
            'process_id' => $this->intOrNull($get('process_id', $get('pid'))),
            'parent_process' => $get('parent_process', $get('parent_image')),
            'file_path' => $get('file_path', $get('file', $get('path'))),
            'command_line' => $get('command_line', $get('cmdline', $get('command'))),
            'hostname' => $get('hostname', $get('host', $get('computer'))),
            'domain' => $get('domain', $get('dns_query')),
            'url' => $get('url'),
            'hash' => $get('hash', $get('sha256', $get('md5'))),
            'hash_type' => $get('hash_type'),
            'action' => $get('action', $get('event_action')),
            'status' => $get('status', $get('result', $get('outcome'))),
        ];
    }

    private function ts(mixed $v): string
    {
        if (! $v) {
            return now()->toDateTimeString();
        }
        try {
            return Carbon::parse((string) $v)->toDateTimeString();
        } catch (\Throwable) {
            return now()->toDateTimeString();
        }
    }

    private function intOrNull(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }
}
