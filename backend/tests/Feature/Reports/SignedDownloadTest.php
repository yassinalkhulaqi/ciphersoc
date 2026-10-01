<?php

namespace Tests\Feature\Reports;

use App\Models\Report;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\SocTestCase;

class SignedDownloadTest extends SocTestCase
{
    public function test_guest_cannot_mint_link(): void
    {
        $this->seedBase();
        $rep = Report::create(['report_id' => 'RPT-T1', 'type' => 'soc_summary', 'title' => 't', 'status' => 'completed']);
        $this->postJson('/api/v1/reports/'.$rep->id.'/link')->assertUnauthorized();
    }

    public function test_queued_report_has_no_link(): void
    {
        $this->seedBase();
        $h = $this->authHeaders('analyst');
        $rep = Report::create(['report_id' => 'RPT-T2', 'type' => 'soc_summary', 'title' => 't', 'status' => 'queued']);
        $this->postJson('/api/v1/reports/'.$rep->id.'/link', [], $h)->assertStatus(409);
    }

    public function test_signed_url_downloads_without_bearer_and_tamper_fails(): void
    {
        $this->seedBase();
        Storage::fake('local');
        Storage::disk('local')->put('reports/RPT-T3.pdf', '%PDF-1.4 fake');
        $rep = Report::create(['report_id' => 'RPT-T3', 'type' => 'soc_summary', 'title' => 't',
            'status' => 'completed', 'file_path' => 'reports/RPT-T3.pdf']);

        $h = $this->authHeaders('analyst');
        $link = $this->postJson('/api/v1/reports/'.$rep->id.'/link', [], $h)->assertOk()->json('data.url');
        $this->assertStringContainsString('signature=', $link);

        // no Authorization header: signature alone authorizes
        $this->get($link)->assertOk();
        // tampered signature rejected
        $this->get($link.'tampered')->assertForbidden();
    }

    public function test_user_show_returns_resource_shape_without_secrets(): void
    {
        $this->seedBase();
        $admin = $this->makeUser('admin', 'boss@test.local');
        $h = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];
        $body = $this->getJson('/api/v1/users/'.$admin->id, $h)->assertOk()->json('data');
        $this->assertEquals(['boss@test.local'], [$body['email']]);
        $this->assertContains('admin', $body['roles']);
        $this->assertArrayNotHasKey('password', $body);
    }
}
