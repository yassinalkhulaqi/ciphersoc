<?php

namespace App\Services\Ingestion\Normalizers;

class CefParser
{
    public function parse(string $line): array
    {
        $out = ['source_type' => 'cef', 'event_type' => 'generic', 'severity' => 'info', 'message' => $line];
        if (! str_starts_with($line, 'CEF:')) {
            return $out;
        }
        $parts = explode('|', $line, 8);
        if (count($parts) >= 8) {
            [, $vendor, $product, $version, $sigId, $name, $sev] = $parts;
            $out['source'] = trim($vendor.' '.$product);
            $out['message'] = trim($name);
            $out['event_type'] = $this->mapEvent($sigId, $name);
            $out['severity'] = $this->mapSev($sev);
            parse_str(str_replace('=', '&', ''), $dummy); // noop guard
            $ext = $parts[7];
            foreach (['src' => 'source_ip', 'dst' => 'destination_ip', 'spt' => 'source_port', 'dpt' => 'destination_port', 'proto' => 'protocol', 'suser' => 'username', 'duser' => 'username', 'shost' => 'hostname', 'request' => 'url', 'fname' => 'file_path', 'fileHash' => 'hash'] as $cef => $norm) {
                if (preg_match('/\b'.preg_quote($cef, '/').'=([^\s]+)/', $ext, $mm)) {
                    $out[$norm] = $mm[1];
                }
            }
            if (preg_match('/\bmsg=(.+?)(?:\s+[a-zA-Z_][a-zA-Z0-9_]*=|$)/', $ext, $mm)) {
                $out['message'] = trim($mm[1]);
            }
        }

        return $out;
    }

    private function mapEvent(string $sig, string $name): string
    {
        $h = strtolower($sig.' '.$name);
        if (str_contains($h, 'login') && str_contains($h, 'fail')) {
            return 'authentication_failure';
        }
        if (str_contains($h, 'login') && str_contains($h, 'success')) {
            return 'authentication_success';
        }
        if (str_contains($h, 'malware') || str_contains($h, 'virus')) {
            return 'malware_detection';
        }
        if (str_contains($h, 'dns')) {
            return 'dns_query';
        }
        if (str_contains($h, 'firewall') || str_contains($h, 'deny') || str_contains($h, 'drop')) {
            return 'firewall_deny';
        }

        return 'generic';
    }

    private function mapSev(string $s): string
    {
        $s = trim($s);
        if (is_numeric($s)) {
            $n = (int) $s;

            return $n >= 8 ? 'critical' : ($n >= 6 ? 'high' : ($n >= 4 ? 'medium' : 'low'));
        }

        return strtolower($s) ?: 'info';
    }
}
