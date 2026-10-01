<?php

namespace Tests\Feature;

use App\Models\DetectionRule;
use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MitreSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class SocTestCase extends TestCase
{
    use RefreshDatabase;

    protected function seedBase(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(MitreSeeder::class);
    }

    protected function makeUser(string $role, ?string $email = null): User
    {
        $u = User::create(['name' => $role, 'email' => $email ?? $role.'_'.Str::random(4).'@test.local', 'password' => 'password123']);
        $u->roles()->sync(Role::where('name', $role)->pluck('id'));

        return $u;
    }

    protected function authHeaders(string $role = 'analyst'): array
    {
        $u = $this->makeUser($role);

        return ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];
    }

    protected function mkEvent(array $over = []): Event
    {
        return Event::create(array_merge([
            'event_id' => (string) Str::uuid(), 'event_timestamp' => now(), 'source' => 't',
            'event_type' => 'generic', 'severity' => 'info',
            'processing_status' => 'stored', 'raw_log' => 'x',
        ], $over));
    }

    protected function mkRule(array $over = []): DetectionRule
    {
        return DetectionRule::create(array_merge([
            'rule_id' => 'RL-'.strtoupper(Str::random(6)), 'name' => 'Test rule '.Str::random(4),
            'severity' => 'high', 'enabled' => true, 'status' => 'active',
            'rule_type' => 'threshold', 'conditions' => ['logic' => 'AND', 'items' => []],
            'threshold' => 1, 'time_window_minutes' => 5, 'cooldown_minutes' => 15,
        ], $over));
    }
}
