<?php

namespace Database\Seeders;

use App\Models\ThreatIntelProvider;
use Illuminate\Database\Seeder;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['VirusTotal', 'virustotal'], ['AbuseIPDB', 'abuseipdb'], ['AlienVault OTX', 'otx'], ['URLhaus', 'urlhaus']] as [$n,$s]) {
            ThreatIntelProvider::firstOrCreate(['slug' => $s], ['name' => $n, 'enabled' => true, 'mock_mode' => true, 'status' => 'not_configured']);
        }
    }
}
