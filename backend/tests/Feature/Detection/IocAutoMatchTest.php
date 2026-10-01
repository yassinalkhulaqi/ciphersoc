<?php

namespace Tests\Feature\Detection;

use App\Models\DetectionRule;
use App\Models\Ioc;
use App\Services\Detection\DetectionEngine;
use App\Services\Detection\EventIocMatcher;
use Tests\Feature\SocTestCase;

class IocAutoMatchTest extends SocTestCase
{
    private function iocRule(): DetectionRule
    {
        return $this->mkRule([
            'rule_id' => 'RL-IOC01', 'name' => 'Known malicious IOC match', 'severity' => 'critical',
            'event_type' => '*', 'rule_type' => 'ioc_match',
            'conditions' => ['logic' => 'AND', 'items' => [['field' => 'source_ip', 'op' => 'exists', 'value' => true]]],
        ]);
    }

    public function test_malicious_ip_event_raises_boosted_alert(): void
    {
        $this->seedBase();
        $this->iocRule();
        Ioc::create(['type' => 'ipv4', 'value' => '203.0.113.45', 'normalized_value' => '203.0.113.45',
            'status' => 'malicious', 'reputation' => 'malicious', 'threat_score' => 92,
            'first_seen_at' => now(), 'last_seen_at' => now()]);

        $engine = new DetectionEngine;
        $event = $this->mkEvent(['event_type' => 'firewall_deny', 'source_ip' => '203.0.113.45']);

        // generic loop must NOT fire ioc_match rules by itself
        $this->assertEmpty($engine->evaluateEvent($event));

        $results = (new EventIocMatcher)->match($event, $engine);
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['created'] && $results[0]['notify']);
        $matched = $results[0]['alert']->matched_iocs;
        $this->assertEquals('203.0.113.45', $matched[0]['value']);
        $this->assertEquals('malicious', $matched[0]['reputation']);
        $this->assertGreaterThan(50, $results[0]['alert']->risk_score);
    }

    public function test_benign_indicator_stays_quiet(): void
    {
        $this->seedBase();
        $this->iocRule();
        Ioc::create(['type' => 'ipv4', 'value' => '10.1.2.3', 'normalized_value' => '10.1.2.3',
            'status' => 'benign', 'reputation' => 'benign', 'threat_score' => 5,
            'first_seen_at' => now(), 'last_seen_at' => now()]);

        $event = $this->mkEvent(['event_type' => 'dns_query', 'source_ip' => '10.1.2.3']);
        $this->assertEmpty((new EventIocMatcher)->match($event, new DetectionEngine));
    }

    public function test_missing_ioc_rule_is_safe_noop(): void
    {
        $this->seedBase();
        $event = $this->mkEvent(['event_type' => 'dns_query', 'source_ip' => '9.9.9.9']);
        $this->assertEmpty((new EventIocMatcher)->match($event, new DetectionEngine));
    }
}
