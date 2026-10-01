<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Incident;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\TotpService;
use Database\Seeders\MitreSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MegaPromptTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(MitreSeeder::class);
    }

    private function auth(string $role = 'admin'): array
    {
        $u = User::create(['name' => $role, 'email' => $role.'_'.Str::random(4).'@test.local', 'password' => 'Password123']);
        $u->roles()->sync(Role::where('name', $role)->pluck('id'));

        return ['user' => $u, 'headers' => ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken]];
    }

    public function test_register_validates_password_strength(): void
    {
        $this->seedBase();
        $this->postJson('/api/v1/auth/register', ['name' => 'x', 'email' => 'x@test.local', 'password' => 'weak', 'password_confirmation' => 'weak'])->assertStatus(422);
        $r = $this->postJson('/api/v1/auth/register', ['name' => 'New', 'email' => 'new@test.local', 'password' => 'Strong123', 'password_confirmation' => 'Strong123'])->assertOk();
        $this->assertNotEmpty($r->json('data.token'));
    }

    public function test_totp_roundtrip(): void
    {
        $svc = new TotpService;
        $secret = $svc->generateSecret();
        // Verify with current code is time-dependent; use reflection to compute one valid code.
        $ref = new \ReflectionMethod($svc, 'hotp');
        $code = $ref->invoke($svc, $secret, intdiv(time(), 30));
        $this->assertTrue($svc->verify($secret, $code));
        $this->assertFalse($svc->verify($secret, '000000'));
        $this->assertStringStartsWith('otpauth://totp/', $svc->provisioningUri('a@test.local', $secret));
    }

    public function test_assets_crud_and_topology(): void
    {
        $this->seedBase();
        $a = $this->auth('admin');
        $r = $this->postJson('/api/v1/assets', ['hostname' => 'web-99', 'ip_address' => '10.0.0.99', 'os' => 'Ubuntu 22.04'], $a['headers'])->assertOk();
        $id = $r->json('data.id');
        $this->getJson('/api/v1/assets', $a['headers'])->assertOk();
        $this->getJson("/api/v1/assets/{$id}", $a['headers'])->assertOk();
        $this->getJson("/api/v1/assets/{$id}/vulns", $a['headers'])->assertOk();
        $this->getJson('/api/v1/network/topology', $a['headers'])->assertOk()->assertJsonStructure(['data' => ['nodes', 'edges', 'geo']]);
    }

    public function test_dashboard_kpis_timeline(): void
    {
        $this->seedBase();
        $a = $this->auth('analyst');
        $this->getJson('/api/v1/dashboard/kpis', $a['headers'])->assertOk()->assertJsonStructure(['data' => [['key', 'value']]]);
        $this->getJson('/api/v1/dashboard/timeline?hours=24', $a['headers'])->assertOk()->assertJsonStructure(['data' => ['points']]);
    }

    public function test_acknowledge_and_escalate(): void
    {
        $this->seedBase();
        $a = $this->auth('analyst');
        $alert = Alert::create(['alert_id' => (string) Str::uuid(), 'title' => 't', 'severity' => 'high', 'status' => 'new', 'occurrence_count' => 1, 'confidence' => 70, 'risk_score' => 50, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $this->postJson("/api/v1/alerts/{$alert->id}/acknowledge", [], $a['headers'])->assertOk();
        $inc = Incident::create(['incident_id' => 'INC-'.Str::random(6), 'title' => 'inc', 'severity' => 'high', 'status' => 'open']);
        $this->postJson("/api/v1/incidents/{$inc->id}/escalate", ['priority' => 'p1'], $a['headers'])->assertOk();
        $this->assertEquals('containment', $inc->fresh()->status);
    }
}
