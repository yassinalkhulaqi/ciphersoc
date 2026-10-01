<?php

namespace App\Services\Automation;

use App\Models\Alert;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\Playbook;
use App\Models\PlaybookRun;
use Illuminate\Support\Str;

// SOAR-lite runner: applies ordered actions to a set of alerts. Manual run today,
// auto-trigger wiring point for queue workers tomorrow (call matches()+run()).
class PlaybookRunner
{
    public function matches(Playbook $pb, Alert $alert): bool
    {
        $t = $pb->trigger ?? [];
        if (! empty($t['severity']) && ! in_array($alert->severity, (array) $t['severity'], true)) {
            return false;
        }
        if (! empty($t['rule_ids']) && $alert->rule && ! in_array($alert->rule->rule_id, (array) $t['rule_ids'], true)) {
            return false;
        }
        if (isset($t['min_risk']) && (int) $alert->risk_score < (int) $t['min_risk']) {
            return false;
        }

        return true;
    }

    /**
     * @param  int[]  $alertIds
     */
    public function run(Playbook $pb, array $alertIds, ?int $runBy = null, bool $dryRun = false): array
    {
        $alerts = Alert::whereIn('id', $alertIds)->get();
        $applied = [];
        $incidents = [];
        foreach ($alerts as $a) {
            foreach ($pb->actions as $action) {
                $type = $action['type'] ?? '';
                $res = match ($type) {
                    'assign' => $this->doAssign($a, $action, $dryRun),
                    'status' => $this->doStatus($a, $action, $dryRun),
                    'comment' => $this->doComment($a, $action, $dryRun, $runBy),
                    'create_incident' => $this->doIncident($a, $action, $dryRun, $runBy, $incidents),
                    default => ['skipped' => "unknown action {$type}"],
                };
                $applied[] = ['alert_id' => $a->id, 'action' => $type, 'result' => $res];
            }
        }
        if (! $dryRun) {
            PlaybookRun::create(['playbook_id' => $pb->id, 'alert_ids' => $alertIds, 'result' => ['applied' => $applied, 'incidents' => $incidents], 'run_by' => $runBy]);
        }

        return ['applied' => $applied, 'incidents' => $incidents];
    }

    private function doAssign(Alert $a, array $action, bool $dry): array
    {
        if (empty($action['assignee_id'])) {
            return ['skipped' => 'no assignee_id'];
        }
        if (! $dry) {
            $a->update(['assignee_id' => $action['assignee_id']]);
        }

        return ['assigned' => $action['assignee_id']];
    }

    private function doStatus(Alert $a, array $action, bool $dry): array
    {
        $to = $action['status'] ?? 'acknowledged';
        if (! $dry) {
            $a->update(['status' => $to]);
        }

        return ['status' => $to];
    }

    private function doComment(Alert $a, array $action, bool $dry, ?int $by): array
    {
        $body = '[playbook] '.($action['body'] ?? 'auto-triaged');
        if (! $dry && $by) {
            $a->comments()->create(['user_id' => $by, 'body' => $body]);
        }

        return ['commented' => ! $dry];
    }

    private function doIncident(Alert $a, array $action, bool $dry, ?int $by, array &$incidents): array
    {
        if (! $dry) {
            $inc = Incident::create([
                'incident_id' => 'INC-'.strtoupper(Str::random(8)),
                'title' => ($action['title'] ?? 'Playbook incident').": {$a->title}",
                'severity' => $a->severity, 'status' => 'open', 'assignee_id' => $action['assignee_id'] ?? $a->assignee_id,
            ]);
            $inc->alerts()->syncWithoutDetaching([$a->id]);
            IncidentTimeline::create(['incident_id' => $inc->id, 'entry_type' => 'created', 'title' => 'Created by playbook', 'created_by' => $by]);
            $incidents[] = $inc->id;

            return ['incident_id' => $inc->id];
        }

        return ['would_create' => true];
    }
}
