<?php

namespace Database\Seeders;

use App\Models\Playbook;
use Illuminate\Database\Seeder;

class PlaybookSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'name' => 'Auto-acknowledge brute force',
                'description' => 'Acknowledge + comment on high-risk SSH brute force for triage.',
                'enabled' => true,
                'trigger' => ['severity' => ['high', 'critical'], 'min_risk' => 60],
                'actions' => [
                    ['type' => 'status', 'status' => 'acknowledged'],
                    ['type' => 'comment', 'body' => 'Auto-triaged: brute-force pattern, verify source IP reputation.'],
                ],
            ],
            [
                'name' => 'Critical → incident',
                'description' => 'Open an incident for critical alerts with full context.',
                'enabled' => true,
                'trigger' => ['severity' => ['critical'], 'min_risk' => 70],
                'actions' => [
                    ['type' => 'status', 'status' => 'escalated'],
                    ['type' => 'create_incident', 'title' => 'Auto-incident'],
                ],
            ],
        ];
        foreach ($defaults as $d) {
            Playbook::firstOrCreate(['name' => $d['name']], $d);
        }
    }
}
