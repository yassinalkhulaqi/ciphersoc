<?php

namespace App\Services\Detection;

// Minimal Sigma → cipherSOC rule converter (no extra deps).
// Supports: title, description, logsource, detection/selection filter maps, condition, level, tags.
// Example input covers 90% of community SSH/powershell/process Sigma rules.
class SigmaConverter
{
    private const SEV = ['informational' => 'info', 'low' => 'low', 'medium' => 'medium', 'high' => 'high', 'critical' => 'critical'];

    /**
     * @return array{rule: array, warnings: string[]}
     */
    public function convert(string $yaml): array
    {
        $warnings = [];
        $data = $this->parseSimpleYaml($yaml);
        $title = $data['title'] ?? 'Imported Sigma rule';
        $level = strtolower((string) ($data['level'] ?? 'medium'));
        $logsource = $data['logsource'] ?? [];
        $detection = $data['detection'] ?? [];
        $condition = strtolower((string) ($detection['condition'] ?? 'selection'));

        $eventType = $this->inferEventType($logsource, $detection);
        $items = [];
        $selections = array_filter($detection, fn ($k) => $k !== 'condition' && $k !== 'timeframe', ARRAY_FILTER_USE_KEY);
        // Flatten first selection level only (keep converter predictable).
        $first = reset($selections);
        if (is_array($first)) {
            foreach ($first as $field => $value) {
                $mapped = $this->mapField((string) $field, $value, $warnings);
                if ($mapped) {
                    $items[] = $mapped;
                }
            }
        }
        if (str_contains($condition, 'not ')) {
            $warnings[] = 'NOT conditions simplified to positive matches; review manually.';
        }
        $mitre = $this->extractMitre($data['tags'] ?? []);

        $rule = [
            'name' => substr((string) $title, 0, 255),
            'description' => substr((string) ($data['description'] ?? 'Imported from Sigma'), 0, 2000),
            'severity' => self::SEV[$level] ?? 'medium',
            'event_type' => $eventType,
            'rule_type' => 'threshold',
            'conditions' => ['logic' => str_contains($condition, ' or ') ? 'OR' : 'AND', 'items' => $items],
            'threshold' => 1,
            'time_window_minutes' => 5,
            'mitre_technique_id' => $mitre,
            'tags' => ['sigma-import'],
        ];

        return ['rule' => $rule, 'warnings' => $warnings];
    }

    private function mapField(string $field, mixed $value, array &$warnings): ?array
    {
        $f = strtolower($field);
        $map = [
            'eventid' => 'event_type', 'event_id' => 'event_type',
            'image' => 'process_name', 'imageloaded' => 'process_name',
            'commandline' => 'command_line', 'parentimage' => 'parent_process',
            'targetfilename' => 'file_path', 'targetobject' => 'file_path',
            'src_ip' => 'source_ip', 'dst_ip' => 'destination_ip',
            'user' => 'username', 'targetusername' => 'username',
            'computer' => 'hostname', 'hostname' => 'hostname',
        ];
        $target = $map[$f] ?? $f;
        $allowed = ['event_type', 'severity', 'hostname', 'username', 'source_ip', 'destination_ip', 'process_name', 'command_line', 'parent_process', 'file_path', 'url', 'domain', 'action', 'status'];
        if (! in_array($target, $allowed, true)) {
            // Keep unknown fields as command_line contains fallback when scalar.
            if (is_string($value)) {
                return ['field' => 'command_line', 'op' => 'contains', 'value' => $value];
            }
            $warnings[] = "Dropped unsupported field: {$field}";

            return null;
        }
        if (is_array($value)) {
            return ['field' => $target, 'op' => 'in', 'value' => array_values($value)];
        }
        $v = (string) $value;
        if (str_contains($v, '*')) {
            return ['field' => $target, 'op' => 'contains', 'value' => trim($v, '*')];
        }

        return ['field' => $target, 'op' => 'equals', 'value' => $v];
    }

    private function inferEventType(array $logsource, array $detection): string
    {
        $h = strtolower(json_encode([$logsource, $detection]));
        foreach (['powershell' => 'powershell_script', 'sysmon' => 'process_creation', '4625' => 'authentication_failure', '4624' => 'authentication_success', 'ssh' => 'authentication_failure', 'dns' => 'dns_query', 'firewall' => 'firewall_deny', 'process_creation' => 'process_creation'] as $k => $v) {
            if (str_contains($h, $k)) {
                return $v;
            }
        }

        return '*';
    }

    private function extractMitre(mixed $tags): ?string
    {
        if (! is_array($tags)) {
            return null;
        }
        foreach ($tags as $t) {
            if (preg_match('/T\d{4}(?:\.\d{3})?/i', (string) $t, $m)) {
                return strtoupper($m[0]);
            }
        }

        return null;
    }

    // Tiny indentation-based YAML subset parser (maps + lists + scalars only).
    private function parseSimpleYaml(string $yaml): array
    {
        $lines = preg_split('/\r?\n/', $yaml);
        $root = [];
        $stack = [&$root];
        $indents = [-1];
        foreach ($lines as $line) {
            if (trim($line) === '' || str_starts_with(trim($line), '#')) {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line, ' '));
            $trim = trim($line);
            while (end($indents) >= $indent) {
                array_pop($stack);
                array_pop($indents);
            }
            if (str_starts_with($trim, '- ')) {
                $val = trim(substr($trim, 2));
                $parent = &$stack[count($stack) - 1];
                if (! is_array($parent)) {
                    $parent = [];
                }
                $parent[] = trim($val, '"\'');

                continue;
            }
            if (! str_contains($trim, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $trim, 2);
            $k = trim($k);
            $v = trim($v);
            $parent = &$stack[count($stack) - 1];
            if ($v === '') {
                $parent[$k] = [];
                $stack[] = &$parent[$k];
                $indents[] = $indent;
            } else {
                $parent[$k] = trim($v, '"\'');
            }
        }

        return $root;
    }
}
