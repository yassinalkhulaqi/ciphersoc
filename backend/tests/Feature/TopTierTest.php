<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Playbook;
use App\Models\Role;
use App\Models\User;
use App\Services\Automation\PlaybookRunner;
use App\Services\Detection\AnomalyScorer;
use App\Services\Detection\GraphCorrelator;
use App\Services\Detection\SigmaConverter;
use App\Services\ThreatIntel\FileFeedProvider;
use Database\Seeders\MitreSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TopTierTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(MitreSeeder::class);
    }

    private function auth(string $role = 'analyst'): array
    {
        $u = User::create(['name' => $role, 'email' => $role.'_'.Str::random(4).'@test.local', 'password' => 'password123']);
        $u->roles()->sync(Role::where('name', $role)->pluck('id'));

        return ['user' => $u, 'headers' => ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken]];
    }

    public function test_sigma_converter(): void
    {
        $yaml = "title: SSH Brute Force\nlevel: high\ndescription: Detects SSH failures\nlogsource:\n  product: linux\n  service: sshd\ndetection:\n  selection:\n    EventID: 4625\n    Image: sshd\n  condition: selection\ntags:\n  - attack.t1110\n";
        $c = new SigmaConverter;
        $res = $c->convert($yaml);
        $this->assertEquals('high', $res['rule']['severity']);
        $this->assertEquals('T1110', $res['rule']['mitre_technique_id']);
        $this->assertNotEmpty($res['rule']['conditions']['items']);
    }

    public function test_sigma_import_endpoint(): void
    {
        $this->seedBase();
        $yaml = "title: Test Sigma\nlevel: medium\ndetection:\n  selection:\n    CommandLine: mimikatz\n  condition: selection\n";
        $admin = $this->auth('admin');
        $r = $this->postJson('/api/v1/rules/import-sigma', ['yaml' => $yaml, 'dry_run' => true], $admin['headers'])->assertOk();
        $this->assertNotEmpty($r->json('data.rule.conditions.items'));
        $r2 = $this->postJson('/api/v1/rules/import-sigma', ['yaml' => $yaml], $admin['headers'])->assertOk();
        $this->assertNotEmpty($r2->json('data.rule.id'));
    }

    public function test_sigma_import_forbidden_for_analyst(): void
    {
        $this->seedBase();
        $a = $this->auth('analyst');
        $yaml = "title: Test Sigma\nlevel: medium\ndetection:\n  selection:\n    CommandLine: mimikatz\n  condition: selection\n";
        $this->postJson('/api/v1/rules/import-sigma', ['yaml' => $yaml, 'dry_run' => true], $a['headers'])->assertForbidden();
    }

    public function test_graph_correlator(): void
    {
        $this->seedBase();
        $mk = fn (array $ctx) => Alert::create(['alert_id' => (string) Str::uuid(), 'title' => 't', 'severity' => 'high', 'status' => 'new', 'occurrence_count' => 1, 'confidence' => 70, 'risk_score' => 80, 'first_seen_at' => now(), 'last_seen_at' => now(), 'context' => $ctx]);
        $a1 = $mk(['source_ip' => '9.9.9.9', 'username' => 'root']);
        $a2 = $mk(['source_ip' => '9.9.9.9', 'username' => 'root']);
        $mk(['source_ip' => '1.1.1.1']);
        $g = new GraphCorrelator;
        $rel = $g->related($a1);
        $this->assertNotEmpty($rel['items']);
        $this->assertEquals($a2->id, $rel['items'][0]['id']);
        $this->assertStringContainsString('shared ip', $rel['items'][0]['reasons'][0]);
    }

    public function test_playbook_run(): void
    {
        $this->seedBase();
        $admin = $this->auth('admin');
        $alert = Alert::create(['alert_id' => (string) Str::uuid(), 'title' => 'brute', 'severity' => 'critical', 'status' => 'new', 'occurrence_count' => 1, 'confidence' => 80, 'risk_score' => 90, 'first_seen_at' => now(), 'last_seen_at' => now(), 'context' => []]);
        $pb = Playbook::create(['name' => 'pb', 'trigger' => ['severity' => ['critical']], 'actions' => [['type' => 'status', 'status' => 'acknowledged'], ['type' => 'comment', 'body' => 'auto']], 'created_by' => $admin['user']->id]);
        $runner = new PlaybookRunner;
        $this->assertTrue($runner->matches($pb, $alert));
        $res = $runner->run($pb, [$alert->id], $admin['user']->id, true);
        $this->assertCount(2, $res['applied']);
        $this->assertEquals('new', $alert->fresh()->status); // dry-run untouched
        $this->postJson("/api/v1/playbooks/{$pb->id}/run", ['alert_ids' => [$alert->id]], $admin['headers'])->assertOk();
        $this->assertEquals('acknowledged', $alert->fresh()->status);
    }

    public function test_file_feed_import(): void
    {
        $this->seedBase();
        $tmp = tempnam(sys_get_temp_dir(), 'feed').'.jsonl';
        file_put_contents($tmp, "{\"type\":\"ipv4\",\"value\":\"9.9.9.9\",\"confidence\":90}\nnot-json\n");
        $res = (new FileFeedProvider)->importFile($tmp, 'test-feed');
        $this->assertEquals(1, $res['imported']);
        $this->assertDatabaseHas('iocs', ['normalized_value' => '9.9.9.9']);
        unlink($tmp);
    }

    public function test_metrics_and_coverage_and_tokens(): void
    {
        $this->seedBase();
        $a = $this->auth('analyst');
        $this->getJson('/api/v1/metrics', $a['headers'])->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/mitre/coverage', $a['headers'])->assertOk()->assertJsonStructure(['data' => ['coverage_pct']]);
        $this->getJson('/api/v1/correlations/suggestions', $a['headers'])->assertOk();
        // tokens CRUD
        $this->postJson('/api/v1/auth/tokens', ['name' => 'ci'], $a['headers'])->assertOk();
        $this->getJson('/api/v1/auth/tokens', $a['headers'])->assertOk();
        $an = new AnomalyScorer;
        $s = $an->score('authentication_failure', 'web-01');
        $this->assertArrayHasKey('score', $s);
    }
}
