<?php

namespace Tests\Feature\Detection;

use App\Models\Alert;
use App\Models\DetectionRule;
use App\Services\Detection\DetectionEngine;
use Tests\Feature\SocTestCase;

class SuppressionTest extends SocTestCase
{
    private function rule(): DetectionRule
    {
        return $this->mkRule([
            'rule_id' => 'RL-SUPP', 'event_type' => 'authentication_failure',
            'conditions' => ['logic' => 'AND', 'items' => [['field' => 'event_type', 'op' => 'equals', 'value' => 'authentication_failure']]],
            'threshold' => 1, 'cooldown_minutes' => 60, 'group_by' => 'source_ip',
        ]);
    }

    private function dims(): array
    {
        return ['event_type' => 'authentication_failure', 'source_ip' => '10.9.9.9', 'username' => 'root'];
    }

    public function test_repeat_inside_cooldown_aggregates_silently(): void
    {
        $this->seedBase();
        $this->rule();
        $engine = new DetectionEngine;

        $first = $engine->evaluateEvent($this->mkEvent($this->dims()));
        $this->assertTrue($first[0]['created'] && $first[0]['notify']);

        $second = $engine->evaluateEvent($this->mkEvent($this->dims()));
        $this->assertFalse($second[0]['created']);
        $this->assertFalse($second[0]['notify']);
        $this->assertEquals(1, Alert::count());
        $this->assertEquals(2, Alert::first()->fresh()->occurrence_count);
    }

    public function test_cooldown_expiry_triggers_renotify_burst(): void
    {
        $this->seedBase();
        $this->rule();
        $engine = new DetectionEngine;

        $engine->evaluateEvent($this->mkEvent($this->dims()));
        Alert::query()->update(['last_notified_at' => now()->subMinutes(61)]);

        $res = $engine->evaluateEvent($this->mkEvent($this->dims()));
        $this->assertFalse($res[0]['created']);
        $this->assertTrue($res[0]['notify']);
        $this->assertEquals(1, Alert::count());
        $this->assertTrue(Alert::first()->fresh()->last_notified_at->greaterThan(now()->subMinutes(2)));
    }

    public function test_activity_on_resolved_key_opens_linked_alert(): void
    {
        $this->seedBase();
        $this->rule();
        $engine = new DetectionEngine;

        $engine->evaluateEvent($this->mkEvent($this->dims()));
        $old = Alert::first();
        $old->update(['status' => 'resolved']);

        $res = $engine->evaluateEvent($this->mkEvent($this->dims()));
        $this->assertTrue($res[0]['created'] && $res[0]['notify']);
        $this->assertEquals(2, Alert::count());
        $this->assertEquals($old->id, $res[0]['alert']->superseded_by);
    }

    public function test_suppression_disabled_notifies_every_time(): void
    {
        $this->seedBase();
        $rule = $this->rule();
        $rule->update(['suppression_enabled' => false]);
        $engine = new DetectionEngine;

        $engine->evaluateEvent($this->mkEvent($this->dims()));
        $res = $engine->evaluateEvent($this->mkEvent($this->dims()));
        $this->assertTrue($res[0]['notify']);
    }
}
