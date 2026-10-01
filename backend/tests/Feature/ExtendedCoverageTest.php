<?php

namespace Tests\Feature;

use App\Models\DetectionRule;
use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use App\Services\Detection\DetectionEngine;
use App\Services\Ingestion\EventNormalizer;
use Database\Seeders\MitreSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExtendedCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(MitreSeeder::class);
    }

    public function test_ecs_normalizer(): void
    {
        $n = (new EventNormalizer)->normalize([
            '@timestamp' => '2026-01-01T00:00:00.000Z',
            'message' => 'Failed authentication',
            'event' => ['category' => 'authentication', 'type' => ['failure']],
            'source' => ['ip' => '10.0.0.9'],
            'host' => ['hostname' => 'web-01'],
            'user' => ['name' => 'root'],
        ]);
        $this->assertEquals('ecs', $n['parser']);
        $this->assertEquals('authentication_failure', $n['event_type']);
        $this->assertEquals('10.0.0.9', $n['source_ip']);
    }

    public function test_sysmon_normalizer(): void
    {
        $n = (new EventNormalizer)->normalize([
            'Provider' => 'Microsoft-Windows-Sysmon',
            'EventID' => 1,
            'Computer' => 'WS-01',
            'EventData' => ['Image' => 'C:\\Windows\\System32\\cmd.exe', 'CommandLine' => 'cmd.exe /c whoami', 'ParentImage' => 'C:\\Windows\\explorer.exe'],
        ]);
        $this->assertEquals('sysmon', $n['parser']);
        $this->assertEquals('process_creation', $n['event_type']);
    }

    public function test_suricata_normalizer(): void
    {
        $n = (new EventNormalizer)->normalize([
            'event_type' => 'alert', 'src_ip' => '1.2.3.4', 'dest_ip' => '5.6.7.8', 'flow_id' => 123,
            'alert' => ['signature' => 'ET MALWARE Possible Trojan', 'severity' => 1],
        ]);
        $this->assertEquals('suricata', $n['parser']);
        $this->assertEquals('ids_alert', $n['event_type']);
        $this->assertEquals('critical', $n['severity']);
    }

    public function test_detection_extended_operators(): void
    {
        $this->seedBase();
        $engine = new DetectionEngine;
        $mk = fn (array $attrs) => Event::create(array_merge([
            'event_id' => (string) Str::uuid(), 'event_timestamp' => now(), 'source' => 't',
            'event_type' => 'process_creation', 'severity' => 'medium', 'processing_status' => 'stored', 'raw_log' => 'x',
        ], $attrs));
        $rule = fn (array $conds) => new DetectionRule([
            'rule_id' => 'T-'.Str::random(4), 'name' => 't', 'severity' => 'high', 'enabled' => true,
            'status' => 'active', 'event_type' => '*', 'rule_type' => 'threshold', 'conditions' => $conds,
            'threshold' => 1, 'time_window_minutes' => 5, 'cooldown_minutes' => 60,
        ]);

        $e = $mk(['command_line' => 'C:\\Temp\\evil.exe', 'process_name' => 'evil.exe']);
        $this->assertTrue($engine->conditionsMatch(['logic' => 'AND', 'items' => [['field' => 'command_line', 'op' => 'startswith', 'value' => 'C:\\Temp']]], $e));
        $this->assertTrue($engine->conditionsMatch(['logic' => 'AND', 'items' => [['field' => 'process_name', 'op' => 'endswith', 'value' => '.exe']]], $e));
        $this->assertTrue($engine->conditionsMatch(['logic' => 'AND', 'items' => [['field' => 'command_line', 'op' => 'not_contains', 'value' => 'legit']]], $e));
        $this->assertFalse($engine->conditionsMatch(['logic' => 'AND', 'items' => [['field' => 'command_line', 'op' => 'not_contains', 'value' => 'evil']]], $e));

        $e2 = $mk(['destination_port' => 4444]);
        $this->assertTrue($engine->conditionsMatch(['logic' => 'AND', 'items' => [['field' => 'destination_port', 'op' => 'gt', 'value' => 1024]]], $e2));
        $this->assertTrue($engine->conditionsMatch(['logic' => 'AND', 'items' => [['field' => 'destination_port', 'op' => 'lt', 'value' => 5000]]], $e2));
        $this->assertFalse($engine->conditionsMatch(['logic' => 'AND', 'items' => [['field' => 'destination_port', 'op' => 'gte', 'value' => 5000]]], $e2));
        // Non-numeric guard: must not match.
        $e3 = $mk(['command_line' => 'not-a-number']);
        $this->assertFalse($engine->conditionsMatch(['logic' => 'AND', 'items' => [['field' => 'command_line', 'op' => 'gt', 'value' => 5]]], $e3));
        $this->assertFalse($rule === null);
    }

    public function test_search_grammar_end_to_end(): void
    {
        $this->seedBase();
        $u = User::create(['name' => 'a', 'email' => 'a@test.local', 'password' => 'password123']);
        $u->roles()->sync(Role::where('name', 'analyst')->pluck('id'));
        $h = ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];
        Event::create(['event_id' => (string) Str::uuid(), 'event_timestamp' => now(), 'source' => 't', 'event_type' => 'authentication_failure', 'severity' => 'medium', 'hostname' => 'web-01', 'source_ip' => '9.9.9.9', 'message' => 'ssh fail', 'processing_status' => 'stored', 'raw_log' => 'x']);
        $this->getJson('/api/v1/events?search='.urlencode('source_ip:9.9.9.9 ssh'), $h)->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/alerts?search='.urlencode('severity:high brute'), $h)->assertOk();
    }
}
