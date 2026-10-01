<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\DetectionRule;
use App\Models\Event;
use App\Models\Host;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\Ioc;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'admin@ciphersoc.local')->exists()) {
            return;
        }
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@ciphersoc.local', 'password' => 'CipherSOC!admin123']);
        $mgr = User::create(['name' => 'SOC Manager', 'email' => 'manager@ciphersoc.local', 'password' => 'CipherSOC!manager123']);
        $ana = User::create(['name' => 'Analyst', 'email' => 'analyst@ciphersoc.local', 'password' => 'CipherSOC!analyst123']);
        $view = User::create(['name' => 'Viewer', 'email' => 'viewer@ciphersoc.local', 'password' => 'CipherSOC!viewer123']);
        $admin->roles()->sync(Role::where('name', 'admin')->pluck('id'));
        $mgr->roles()->sync(Role::where('name', 'manager')->pluck('id'));
        $ana->roles()->sync(Role::where('name', 'analyst')->pluck('id'));
        $view->roles()->sync(Role::where('name', 'viewer')->pluck('id'));

        $hosts = [];
        foreach ([['web-01', 'Ubuntu 22.04', '10.10.10.11'], ['dc-01', 'Windows Server 2022', '10.10.10.5'], ['wkst-042', 'Windows 11', '10.10.10.42']] as [$hn,$os,$ip]) {
            $h = Host::create(['host_id' => 'hst-'.Str::lower(Str::random(6)), 'hostname' => $hn, 'os' => $os, 'ip_address' => $ip, 'status' => 'online', 'criticality' => 70, 'first_seen_at' => now()->subDays(7), 'last_seen_at' => now(), 'tags' => ['demo']]);
            $hosts[] = $h;
            Agent::create(['agent_id' => 'agt-'.Str::lower(Str::random(8)), 'host_id' => $h->id, 'hostname' => $hn, 'os' => $os, 'agent_version' => '1.0.0', 'status' => 'online', 'enrolled_at' => now()->subDays(7), 'last_heartbeat_at' => now()]);
        }

        $mkEvent = function (array $o) use ($hosts) {
            $h = $hosts[array_rand($hosts)];

            return Event::create([...['event_id' => (string) Str::uuid(), 'event_timestamp' => now()->subMinutes(rand(5, 1400)), 'source' => 'demo', 'source_type' => 'syslog', 'parser' => 'syslog', 'host_id' => $h->id, 'event_type' => 'generic', 'severity' => 'info', 'processing_status' => 'processed', 'raw_log' => 'demo synthetic event'], ...$o, 'hostname' => $o['hostname'] ?? $h->hostname]);
        };
        // Scenario 1: brute force
        for ($i = 0; $i < 12; $i++) {
            $mkEvent(['event_type' => 'authentication_failure', 'severity' => 'medium', 'message' => "Failed password for root from 203.0.113.45 port 5123$i ssh2", 'username' => 'root', 'source_ip' => '203.0.113.45', 'action' => 'login', 'status' => 'failure']);
        }
        $mkEvent(['event_type' => 'authentication_success', 'severity' => 'high', 'message' => 'Accepted password for root from 203.0.113.45', 'username' => 'root', 'source_ip' => '203.0.113.45', 'action' => 'login', 'status' => 'success']);
        // Scenario 2: powershell
        $mkEvent(['event_type' => 'powershell_script', 'severity' => 'critical', 'source_type' => 'windows_event', 'parser' => 'windows_event', 'message' => 'Suspicious encoded powershell', 'command_line' => 'powershell.exe -NoP -W Hidden -EncodedCommand aQBmACgAWwBDAG8AbgB2AGUAcgB0AF0AXQA6ADoARgByAG8AbQBCAGEAcwBlADYANABTAHQAcgBpAG4AZwA=', 'process_name' => 'powershell.exe', 'hostname' => 'wkst-042', 'username' => 'jdoe']);
        $mkEvent(['event_type' => 'process_creation', 'severity' => 'high', 'message' => 'mimikatz execution', 'command_line' => 'mimikatz.exe privilege::debug sekurlsa::logonpasswords', 'process_name' => 'mimikatz.exe', 'hostname' => 'wkst-042']);
        $mkEvent(['event_type' => 'account_created', 'severity' => 'high', 'message' => 'New local admin created', 'username' => 'backup_admin', 'hostname' => 'dc-01']);
        for ($i = 0; $i < 5; $i++) {
            $mkEvent(['event_type' => 'firewall_deny', 'severity' => 'low', 'message' => 'DENY TCP 198.51.100.23 -> 10.10.10.11:443', 'source_ip' => '198.51.100.23', 'destination_ip' => '10.10.10.11', 'destination_port' => 443, 'protocol' => 'tcp']);
        }

        Ioc::firstOrCreate(['type' => 'ipv4', 'normalized_value' => '203.0.113.45'], ['value' => '203.0.113.45', 'confidence' => 90, 'severity' => 'critical', 'source' => 'demo-seed', 'status' => 'malicious', 'threat_score' => 92, 'reputation' => 'malicious', 'first_seen_at' => now()->subDays(2), 'last_seen_at' => now(), 'tags' => ['demo', 'bruteforce']]);
        Ioc::firstOrCreate(['type' => 'ipv4', 'normalized_value' => '198.51.100.23'], ['value' => '198.51.100.23', 'confidence' => 80, 'severity' => 'high', 'source' => 'demo-seed', 'status' => 'suspicious', 'threat_score' => 65, 'reputation' => 'suspicious', 'first_seen_at' => now()->subDays(1), 'last_seen_at' => now(), 'tags' => ['demo']]);
        Ioc::firstOrCreate(['type' => 'domain', 'normalized_value' => 'malicious-test.example'], ['value' => 'malicious-test.example', 'confidence' => 85, 'severity' => 'high', 'source' => 'demo-seed', 'status' => 'malicious', 'threat_score' => 88, 'reputation' => 'malicious', 'first_seen_at' => now()->subDays(1), 'last_seen_at' => now(), 'tags' => ['demo']]);

        $rule = DetectionRule::where('rule_id', 'RL-SSH001')->first();
        $host = $hosts[0];
        $alert = Alert::create(['alert_id' => (string) Str::uuid(), 'title' => 'Multiple failed SSH logins', 'description' => '12 failed logins from 203.0.113.45 followed by success (demo).', 'severity' => 'critical', 'status' => 'new', 'source' => 'detection-engine', 'detection_rule_id' => $rule?->id, 'host_id' => $host->id, 'occurrence_count' => 12, 'first_seen_at' => now()->subHours(3), 'last_seen_at' => now(), 'confidence' => 85, 'risk_score' => 87, 'risk_factors' => [['factor' => 'Severity (critical)', 'points' => 60], ['factor' => 'Repeated Activity (x12)', 'points' => 6], ['factor' => 'Confidence (85%)', 'points' => 8]], 'mitre' => ['technique' => 'T1110', 'tactic' => 'credential-access'], 'tags' => ['bruteforce', 'demo'], 'dedup_key' => hash('sha256', 'demo-ssh'), 'context' => ['source_ip' => '203.0.113.45', 'username' => 'root']]);
        $alert2 = Alert::create(['alert_id' => (string) Str::uuid(), 'title' => 'Suspicious PowerShell execution', 'description' => 'Encoded PowerShell on wkst-042 (demo).', 'severity' => 'critical', 'status' => 'investigating', 'source' => 'detection-engine', 'host_id' => $hosts[2]->id, 'occurrence_count' => 1, 'first_seen_at' => now()->subHours(1), 'last_seen_at' => now(), 'confidence' => 80, 'risk_score' => 82, 'risk_factors' => [['factor' => 'Severity (critical)', 'points' => 60], ['factor' => 'Confidence (80%)', 'points' => 8]], 'mitre' => ['technique' => 'T1059.001', 'tactic' => 'execution'], 'tags' => ['powershell', 'demo'], 'dedup_key' => hash('sha256', 'demo-ps'), 'context' => ['hostname' => 'wkst-042', 'command_line' => 'powershell.exe -EncodedCommand ...'], 'assignee_id' => $ana->id]);
        // Bulk realistic volume: 120 alerts, 15 incidents, 1000 logs, 80 IOCs, 25 assets (<10s).
        $titles = ['Mimikatz detected on WS-214', 'Brute-force SSH from 45.134.26.12', 'C2 beacon to evil.com', 'Suspicious PowerShell encoded', 'Lateral movement via SMB', 'Credential dumping LSASS', 'Port scan from internal host', 'DNS tunneling suspected', 'Ransomware canary tripped', 'Anomalous login midnight'];
        $sevs = ['critical', 'high', 'medium', 'low', 'info'];
        for ($i = 0; $i < 118; $i++) {
            $t = $titles[$i % count($titles)];
            $sev = $sevs[$i % count($sevs)];
            Alert::create(['alert_id' => (string) Str::uuid(), 'title' => $t." #$i", 'description' => 'Seeded bulk alert', 'severity' => $sev, 'status' => ['new', 'acknowledged', 'investigating'][$i % 3], 'source' => 'detection-engine', 'host_id' => $hosts[$i % count($hosts)]->id, 'occurrence_count' => rand(1, 8), 'first_seen_at' => now()->subHours(rand(1, 72)), 'last_seen_at' => now(), 'confidence' => rand(60, 95), 'risk_score' => rand(30, 95), 'mitre' => ['technique' => ['T1110', 'T1059.001', 'T1003', 'T1071'][$i % 4]], 'tags' => ['seed'], 'dedup_key' => hash('sha256', 'seed-alert-'.$i), 'context' => ['source_ip' => '45.134.26.'.($i % 250 + 1)]]);
        }
        for ($i = 0; $i < 14; $i++) {
            $newInc = Incident::create(['incident_id' => 'INC-SEED'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'title' => "Seed incident $i", 'description' => 'Bulk seeded case', 'severity' => $sevs[$i % count($sevs)], 'priority' => 'p'.(($i % 4) + 1), 'status' => ['open', 'investigating', 'containment'][$i % 3], 'assignee_id' => $ana->id]);
            IncidentTimeline::create(['incident_id' => $newInc->id, 'entry_type' => 'created', 'title' => 'Seeded', 'created_by' => $ana->id]);
        }
        for ($i = 0; $i < 980; $i++) {
            Event::create(['event_id' => (string) Str::uuid(), 'event_timestamp' => now()->subMinutes(rand(1, 4320)), 'source' => 'seed', 'source_type' => 'syslog', 'parser' => 'syslog', 'host_id' => $hosts[$i % count($hosts)]->id, 'event_type' => ['authentication_failure', 'authentication_success', 'process_creation', 'dns_query', 'firewall_deny'][$i % 5], 'severity' => $sevs[$i % count($sevs)], 'message' => "Seed log line $i", 'processing_status' => 'processed', 'raw_log' => 'seed', 'hostname' => $hosts[$i % count($hosts)]->hostname, 'source_ip' => '10.10.10.'.($i % 250 + 1)]);
        }
        for ($i = 0; $i < 77; $i++) {
            $ip = '198.51.100.'.($i + 10);
            Ioc::firstOrCreate(['type' => 'ipv4', 'normalized_value' => $ip], ['value' => $ip, 'confidence' => 70, 'severity' => 'medium', 'source' => 'seed-bulk', 'status' => 'suspicious', 'threat_score' => 60, 'first_seen_at' => now()->subDays(1), 'last_seen_at' => now()]);
        }
        for ($i = 0; $i < 22; $i++) {
            $hn = sprintf('srv-%02d', $i + 4);
            Asset::firstOrCreate(['hostname' => $hn], ['ip_address' => '10.10.20.'.($i + 10), 'os' => $i % 2 ? 'Ubuntu 22.04' : 'Windows Server 2022', 'criticality' => ['low', 'medium', 'high', 'critical'][$i % 4], 'status' => 'online', 'last_seen_at' => now()]);
        }
        $inc = Incident::create(['incident_id' => 'INC-DEMO0001', 'title' => 'Possible SSH compromise on web-01', 'description' => 'Brute force followed by successful login from 203.0.113.45. Isolate host, rotate credentials, review auth logs.', 'severity' => 'high', 'priority' => 'p1', 'status' => 'investigating', 'assignee_id' => $ana->id, 'team' => 'blue-team', 'tags' => ['demo', 'bruteforce'], 'mitre_techniques' => ['T1110', 'T1078']]);
        $inc->alerts()->attach($alert->id);
        IncidentTimeline::create(['incident_id' => $inc->id, 'entry_type' => 'created', 'title' => 'Incident created from alert', 'detail' => 'Auto-linked brute-force alert', 'created_by' => $ana->id]);
        AuditLog::create(['actor_id' => $admin->id, 'action' => 'seed.demo', 'resource_type' => 'system', 'resource_id' => 'demo', 'metadata' => ['note' => 'demo environment seeded']]);
    }
}
