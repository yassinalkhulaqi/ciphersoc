<?php

namespace Tests\Feature\Detection;

use App\Models\Alert;
use App\Models\DetectionRule;
use App\Services\Detection\DetectionEngine;
use Tests\Feature\SocTestCase;

class SequenceCorrelationTest extends SocTestCase
{
    private function sequenceRule(): DetectionRule
    {
        return $this->mkRule([
            'rule_id' => 'RL-SEQ', 'name' => 'Success after failures', 'severity' => 'critical',
            'event_type' => 'authentication_success', 'rule_type' => 'correlation',
            'conditions' => ['logic' => 'SEQUENCE', 'within_minutes' => 15, 'group_by' => 'source_ip', 'steps' => [
                ['field' => 'event_type', 'op' => 'equals', 'value' => 'authentication_failure'],
                ['field' => 'event_type', 'op' => 'equals', 'value' => 'authentication_success'],
            ]],
        ]);
    }

    public function test_success_after_failures_fires(): void
    {
        $this->seedBase();
        $rule = $this->sequenceRule();
        $engine = new DetectionEngine;

        for ($i = 0; $i < 3; $i++) {
            $this->mkEvent(['event_type' => 'authentication_failure', 'source_ip' => '7.7.7.7', 'event_timestamp' => now()->subMinutes(10 - $i)]);
        }
        $success = $this->mkEvent(['event_type' => 'authentication_success', 'source_ip' => '7.7.7.7', 'status' => 'success']);

        $results = array_values(array_filter(
            $engine->evaluateEvent($success),
            fn ($r) => $r['alert']->detection_rule_id === $rule->id
        ));
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['created']);
        $this->assertEquals('critical', $results[0]['alert']->severity);
    }

    public function test_success_without_prior_failures_stays_quiet(): void
    {
        $this->seedBase();
        $rule = $this->sequenceRule();
        $engine = new DetectionEngine;

        $success = $this->mkEvent(['event_type' => 'authentication_success', 'source_ip' => '8.8.8.8', 'status' => 'success']);
        $results = array_filter(
            $engine->evaluateEvent($success),
            fn ($r) => $r['alert']->detection_rule_id === $rule->id
        );
        $this->assertEmpty($results);
    }

    public function test_same_second_burst_correlates(): void
    {
        $this->seedBase();
        $rule = $this->sequenceRule();
        $engine = new DetectionEngine;

        // all events share one timestamp: strict inequality bounds must not drop them
        $at = now();
        for ($i = 0; $i < 2; $i++) {
            $this->mkEvent(['event_type' => 'authentication_failure', 'source_ip' => '3.3.3.3', 'event_timestamp' => $at]);
        }
        $success = $this->mkEvent(['event_type' => 'authentication_success', 'source_ip' => '3.3.3.3', 'status' => 'success', 'event_timestamp' => $at]);
        $results = array_filter(
            $engine->evaluateEvent($success),
            fn ($r) => $r['alert']->detection_rule_id === $rule->id
        );
        $this->assertNotEmpty($results);
    }

    public function test_group_isolation_blocks_cross_ip_chain(): void
    {
        $this->seedBase();
        $rule = $this->sequenceRule();
        $engine = new DetectionEngine;

        $this->mkEvent(['event_type' => 'authentication_failure', 'source_ip' => '1.1.1.1', 'event_timestamp' => now()->subMinutes(5)]);
        $success = $this->mkEvent(['event_type' => 'authentication_success', 'source_ip' => '2.2.2.2', 'status' => 'success']);
        $results = array_filter(
            $engine->evaluateEvent($success),
            fn ($r) => $r['alert']->detection_rule_id === $rule->id
        );
        $this->assertEmpty($results);
        $this->assertEquals(0, Alert::where('detection_rule_id', $rule->id)->count());
    }
}
