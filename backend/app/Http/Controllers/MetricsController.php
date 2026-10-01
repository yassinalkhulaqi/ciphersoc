<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Event;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function metrics()
    {
        $open = fn () => Alert::whereNotIn('status', ['resolved', 'closed', 'false_positive'])->count();
        $data = [
            'events_total' => $this->count('events'),
            'alerts_total' => $this->count('alerts'),
            'alerts_open' => $this->safe($open),
            'incidents_open' => $this->countWhere('incidents', ['resolved', 'closed'], true),
            'iocs_total' => $this->count('iocs'),
            'agents_online' => $this->countWhereRaw('agents', "status='online'"),
            'queue_pending' => $this->count('jobs'),
            'queue_failed' => $this->count('failed_jobs'),
        ];
        if (request()->getAcceptableContentTypes() && in_array('text/plain', request()->getAcceptableContentTypes(), true)) {
            $lines = ['# HELP ciphersoc_entity_total Current totals', '# TYPE ciphersoc_entity_total gauge'];
            foreach ($data as $k => $v) {
                $lines[] = "ciphersoc_entity_total{entity=\"{$k}\"} ".($v ?? 0);
            }

            return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; version=0.0.4']);
        }

        return ApiResponse::ok(array_merge($data, ['events_last_hour' => $this->recentEvents()]));
    }

    private function count(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable) {
            return null;
        }
    }

    private function countWhere(string $table, array $excluded, bool $notIn): ?int
    {
        try {
            return DB::table($table)->whereNotIn('status', $excluded)->count();
        } catch (\Throwable) {
            return null;
        }
    }

    private function countWhereRaw(string $table, string $raw): ?int
    {
        try {
            return DB::table($table)->whereRaw($raw)->count();
        } catch (\Throwable) {
            return null;
        }
    }

    private function safe(callable $fn): ?int
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }

    private function recentEvents(): ?int
    {
        try {
            return Event::where('event_timestamp', '>=', now()->subHour())->count();
        } catch (\Throwable) {
            return null;
        }
    }
}
