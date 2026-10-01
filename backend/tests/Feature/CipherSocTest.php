<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Event;
use App\Models\Ioc;
use App\Models\Role;
use App\Models\User;
use App\Services\Detection\DetectionEngine;
use App\Services\Ingestion\EventNormalizer;
use App\Services\Risk\RiskScorer;
use Database\Seeders\DetectionRuleSeeder;
use Database\Seeders\MitreSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CipherSocTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(MitreSeeder::class);
    }

    private function makeUser(string $role, string $email): User
    {
        $u = User::create(['name' => $role, 'email' => $email, 'password' => 'password123']);
        $u->roles()->sync(Role::where('name', $role)->pluck('id'));

        return $u;
    }

    private function auth(string $role = 'analyst'): array
    {
        $u = $this->makeUser($role, $role.'_'.Str::random(4).'@test.local');

        return ['user' => $u, 'headers' => ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken]];
    }

    public function test_auth_login_and_me(): void
    {
        $this->seedBase();
        $u = $this->makeUser('analyst', 'analyst_login@test.local');
        $r = $this->postJson('/api/v1/auth/login', ['email' => 'analyst_login@test.local', 'password' => 'password123']);
        $r->assertOk()->assertJsonPath('success', true);
        $token = $r->json('data.token');
        $this->assertNotEmpty($token);
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('success', true);
    }

    public function test_rbac_viewer_cannot_create_rule(): void
    {
        $this->seedBase();
        $a = $this->auth('viewer');
        $this->postJson('/api/v1/rules', ['name' => 'x', 'severity' => 'high', 'conditions' => []], $a['headers'])->assertForbidden();
    }

    public function test_normalizers(): void
    {
        $n = new EventNormalizer;
        $ssh = $n->normalize('Nov 12 10:11:12 web-01 sshd[1234]: Failed password for root from 10.0.0.1 port 22 ssh2');
        $this->assertEquals('authentication_failure', $ssh['event_type']);
        $this->assertEquals('10.0.0.1', $ssh['source_ip']);
        $cef = $n->normalize('CEF:0|Vendor|Product|1.0|100|Failed login|8|src=1.2.3.4 dst=5.6.7.8');
        $this->assertEquals('1.2.3.4', $cef['source_ip']);
        $win = $n->normalize(['EventID' => 4625, 'Computer' => 'DC-01', 'TargetUserName' => 'admin']);
        $this->assertEquals('authentication_failure', $win['event_type']);
        $g = $n->normalize(['event_type' => 'dns_query', 'message' => 'hello']);
        $this->assertEquals('dns_query', $g['event_type']);
        // JSON-wrapped syslog line (agent format) must still classify
        $wrapped = $n->normalize(['message' => 'Nov 12 10:11:12 web-01 sshd[1234]: Failed password for root from 10.0.0.1 port 22 ssh2']);
        $this->assertEquals('authentication_failure', $wrapped['event_type']);
        $this->assertEquals('10.0.0.1', $wrapped['source_ip']);
        // …but an explicit event_type always wins over sniffing
        $explicit = $n->normalize(['event_type' => 'custom', 'message' => 'Nov 12 10:11:12 h sshd[1]: Failed password for root from 10.0.0.1 port 22 ssh2']);
        $this->assertEquals('custom', $explicit['event_type']);
    }

    public function test_detection_threshold_and_dedup(): void
    {
        $this->seedBase();
        $this->seed(DetectionRuleSeeder::class);
        $engine = new DetectionEngine;
        $mk = function () {
            return Event::create(['event_id' => (string) Str::uuid(), 'event_timestamp' => now(), 'source' => 't', 'event_type' => 'authentication_failure', 'severity' => 'medium', 'source_ip' => '9.9.9.9', 'processing_status' => 'stored', 'raw_log' => 'x']);
        };
        // threshold=5 for RL-SSH001 grouped by source_ip: create 5 events sequentially
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results = array_merge($results, $engine->evaluateEvent($mk()));
        }
        $this->assertNotEmpty(array_filter($results, fn ($r) => $r['created']));
        $count = Alert::count();
        // dedup: one more event within cooldown should aggregate silently (no notify)
        $quiet = $engine->evaluateEvent($mk());
        $this->assertEquals($count, Alert::count());
        foreach ($quiet as $res) {
            $this->assertFalse($res['notify']);
        }
        $this->assertGreaterThanOrEqual(2, Alert::max('occurrence_count'));
    }

    public function test_encoded_powershell_rule(): void
    {
        $this->seedBase();
        $this->seed(DetectionRuleSeeder::class);
        $engine = new DetectionEngine;
        $e = Event::create(['event_id' => (string) Str::uuid(), 'event_timestamp' => now(), 'source' => 't', 'event_type' => 'powershell_script', 'severity' => 'high', 'command_line' => 'powershell.exe -EncodedCommand aGVsbG8=', 'processing_status' => 'stored', 'raw_log' => 'x']);
        $results = $engine->evaluateEvent($e);
        $this->assertNotEmpty(array_filter($results, fn ($r) => $r['created'] && $r['notify']));
        $this->assertTrue($engine->isEncodedPowershell('powershell -enc aGVsbG8gd29ybGQgdGVzdA=='));
    }

    public function test_agent_register_ingest_end_to_end(): void
    {
        $this->seedBase();
        $this->seed(DetectionRuleSeeder::class);
        config(['services.agent.enrollment_token' => 'test-enroll']);
        $r = $this->postJson('/api/v1/agents/register', ['hostname' => 'e2e-host', 'os' => 'Linux', 'enrollment_token' => 'test-enroll']);
        $r->assertOk();
        $agentId = $r->json('data.agent_id');
        $token = $r->json('data.api_token');
        $h = ['X-Agent-ID' => $agentId, 'Authorization' => 'Bearer '.$token];
        $hb = $this->postJson('/api/v1/agents/heartbeat', ['agent_version' => '1.0.0'], $h);
        $hb->assertOk();
        $ing = $this->postJson('/api/v1/ingest/events', ['events' => [['message' => 'Nov 12 10:11:12 e2e-host sshd[1]: Failed password for root from 7.7.7.7 port 22 ssh2']]], $h);
        $ing->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('events', ['hostname' => 'e2e-host']);
    }

    public function test_alert_lifecycle_and_audit(): void
    {
        $this->seedBase();
        $a = $this->auth('analyst');
        $alert = Alert::create(['alert_id' => (string) Str::uuid(), 'title' => 't', 'severity' => 'high', 'status' => 'new', 'occurrence_count' => 1, 'confidence' => 70, 'risk_score' => 50, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $this->patchJson('/api/v1/alerts/'.$alert->id, ['status' => 'acknowledged'], $a['headers'])->assertOk();
        $this->postJson('/api/v1/alerts/'.$alert->id.'/comments', ['body' => 'looking into it'], $a['headers'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'alert.update']);
    }

    public function test_incident_workflow(): void
    {
        $this->seedBase();
        $a = $this->auth('analyst');
        $alert = Alert::create(['alert_id' => (string) Str::uuid(), 'title' => 't', 'severity' => 'high', 'status' => 'new', 'occurrence_count' => 1, 'confidence' => 70, 'risk_score' => 50, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $cr = $this->postJson('/api/v1/incidents', ['title' => 'inc', 'severity' => 'high', 'alert_ids' => [$alert->id]], $a['headers'])->assertOk();
        $incId = $cr->json('data.id');
        $this->postJson('/api/v1/incidents/'.$incId.'/comments', ['body' => 'note'], $a['headers'])->assertOk();
        $this->patchJson('/api/v1/incidents/'.$incId, ['status' => 'containment'], $a['headers'])->assertOk();
        $this->assertDatabaseHas('incident_timeline', ['incident_id' => $incId]);
    }

    public function test_ioc_normalization_and_enrichment(): void
    {
        $this->seedBase();
        $this->assertEquals('example.com', Ioc::normalize('domain', 'Example.COM.'));
        $this->assertEquals('10.0.0.1', Ioc::normalize('ipv4', '010.000.000.001'));
        $a = $this->auth('analyst');
        $cr = $this->postJson('/api/v1/iocs', ['type' => 'ipv4', 'value' => '203.0.113.45'], $a['headers'])->assertOk();
        $this->postJson('/api/v1/iocs/'.$cr->json('data.id').'/enrich', [], $a['headers'])->assertOk();
        $lk = $this->postJson('/api/v1/threat-intel/lookup', ['type' => 'ipv4', 'value' => '203.0.113.45'], $a['headers'])->assertOk();
        $this->assertNotEmpty($lk->json('data.results'));
    }

    public function test_mitre_and_dashboard_and_health(): void
    {
        $this->seedBase();
        $a = $this->auth('analyst');
        $this->getJson('/api/v1/mitre/tactics', $a['headers'])->assertOk();
        $this->getJson('/api/v1/mitre/techniques', $a['headers'])->assertOk();
        $this->getJson('/api/v1/dashboard/overview', $a['headers'])->assertOk()->assertJsonStructure(['data' => ['totals']]);
        $this->getJson('/api/health')->assertOk()->assertJsonPath('data.app', 'cipherSOC');
        $this->getJson('/api/openapi.json')->assertOk();
    }

    public function test_risk_scorer_transparency(): void
    {
        $s = new RiskScorer;
        $r = $s->score(['severity' => 'critical', 'confidence' => 80, 'repeat_count' => 10, 'affected_hosts' => 3, 'mitre' => ['T1003'], 'threat_intel_score' => 80]);
        $this->assertGreaterThanOrEqual(80, $r['score']);
        $this->assertNotEmpty($r['factors']);
    }
}
