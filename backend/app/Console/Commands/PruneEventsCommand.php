<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Event;
use Illuminate\Console\Command;

// Retention enforcement: delete old telemetry in chunks to avoid long locks.
class PruneEventsCommand extends Command
{
    protected $signature = 'events:prune {--days=90 : keep window in days} {--chunk=5000 : rows per delete chunk} {--alerts-days=365 : alert retention} {--dry-run : report only}';

    protected $description = 'Prune old events/alerts outside the retention window';

    public function handle(): int
    {
        $days = max(7, (int) $this->option('days'));
        $chunk = max(100, (int) $this->option('chunk'));
        $alertsDays = max(30, (int) $this->option('alerts-days'));
        $dry = (bool) $this->option('dry-run');
        $eventCutoff = now()->subDays($days);
        $alertCutoff = now()->subDays($alertsDays);

        $eventCount = Event::where('event_timestamp', '<', $eventCutoff)->count();
        $alertCount = Alert::where('created_at', '<', $alertCutoff)->whereIn('status', ['resolved', 'closed', 'false_positive'])->count();
        $this->info("events older than {$eventCutoff->toDateTimeString()}: {$eventCount}");
        $this->info("closed alerts older than {$alertCutoff->toDateTimeString()}: {$alertCount}");

        if ($dry) {
            return self::SUCCESS;
        }
        $deleted = 0;
        do {
            $n = Event::where('event_timestamp', '<', $eventCutoff)->limit($chunk)->delete();
            $deleted += $n;
        } while ($n > 0);
        $aDeleted = Alert::where('created_at', '<', $alertCutoff)->whereIn('status', ['resolved', 'closed', 'false_positive'])->limit($chunk)->delete();
        $this->info("pruned {$deleted} events, {$aDeleted} alerts");

        return self::SUCCESS;
    }
}
