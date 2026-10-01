<?php

namespace App\Console\Commands;

use App\Services\ThreatIntel\FileFeedProvider;
use Illuminate\Console\Command;

class ImportThreatFeedCommand extends Command
{
    protected $signature = 'threatintel:import-feed {path : JSONL or CSV file} {--source=file-feed}';

    protected $description = 'Import IOCs from a local JSONL/CSV threat feed file';

    public function handle(FileFeedProvider $feeds): int
    {
        $res = $feeds->importFile($this->argument('path'), (string) $this->option('source'));
        $this->info("imported={$res['imported']} skipped={$res['skipped']}");
        foreach ($res['errors'] as $e) {
            $this->warn($e);
        }

        return empty($res['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
