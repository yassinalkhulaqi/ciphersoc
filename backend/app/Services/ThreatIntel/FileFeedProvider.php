<?php

namespace App\Services\ThreatIntel;

use App\Models\Ioc;

// File-based TI feed: JSONL `{"type":"ipv4","value":"1.2.3.4","confidence":80,"source":"my-feed"}`
// or CSV `type,value,confidence`. Used by `threatintel:import-feed` cron.
class FileFeedProvider
{
    /**
     * @return array{imported:int, skipped:int, errors:string[]}
     */
    public function importFile(string $path, string $defaultSource = 'file-feed'): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        if (! is_file($path)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ["not found: {$path}"]];
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $i => $line) {
            try {
                if ($ext === 'csv') {
                    $parts = str_getcsv($line);
                    if (($parts[0] ?? '') === 'type') {
                        continue;
                    }
                    $row = ['type' => trim($parts[0] ?? ''), 'value' => trim($parts[1] ?? ''), 'confidence' => (int) ($parts[2] ?? 70), 'source' => $defaultSource];
                } else {
                    $row = json_decode($line, true);
                    if (! is_array($row)) {
                        $skipped++;

                        continue;
                    }
                    $row['source'] = $row['source'] ?? $defaultSource;
                }
                if (empty($row['type']) || empty($row['value'])) {
                    $skipped++;

                    continue;
                }
                Ioc::firstOrCreate(
                    ['type' => $row['type'], 'normalized_value' => Ioc::normalize($row['type'], $row['value'])],
                    ['value' => $row['value'], 'source' => $row['source'], 'confidence' => max(0, min(100, (int) ($row['confidence'] ?? 70)))]
                );
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                if (count($errors) < 5) {
                    $errors[] = 'line '.($i + 1).': '.$e->getMessage();
                }
            }
        }

        return compact('imported', 'skipped', 'errors');
    }
}
