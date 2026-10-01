<?php

namespace Database\Seeders;

use App\Models\MitreTactic;
use App\Models\MitreTechnique;
use Illuminate\Database\Seeder;

class MitreSeeder extends Seeder
{
    public function run(): void
    {
        $tactics = [
            ['tactic_id' => 'TA0001', 'name' => 'Initial Access', 'description' => 'Techniques used to gain a foothold.'],
            ['tactic_id' => 'TA0002', 'name' => 'Execution', 'description' => 'Running adversary-controlled code.'],
            ['tactic_id' => 'TA0003', 'name' => 'Persistence', 'description' => 'Maintaining access across restarts.'],
            ['tactic_id' => 'TA0004', 'name' => 'Privilege Escalation', 'description' => 'Gaining higher-level permissions.'],
            ['tactic_id' => 'TA0005', 'name' => 'Defense Evasion', 'description' => 'Avoiding detection.'],
            ['tactic_id' => 'TA0006', 'name' => 'Credential Access', 'description' => 'Stealing credentials.'],
            ['tactic_id' => 'TA0007', 'name' => 'Discovery', 'description' => 'Learning about the environment.'],
            ['tactic_id' => 'TA0008', 'name' => 'Lateral Movement', 'description' => 'Moving through the environment.'],
            ['tactic_id' => 'TA0009', 'name' => 'Collection', 'description' => 'Gathering data of interest.'],
        ];
        foreach ($tactics as $t) {
            MitreTactic::firstOrCreate(['tactic_id' => $t['tactic_id']], $t);
        }
        $techs = [
            ['technique_id' => 'T1110', 'name' => 'Brute Force', 'description' => 'Adversaries attempt to guess credentials by trying many passwords.', 'tactic' => 'credential-access'],
            ['technique_id' => 'T1078', 'name' => 'Valid Accounts', 'description' => 'Use of legitimate credentials to gain access.', 'tactic' => 'defense-evasion'],
            ['technique_id' => 'T1003', 'name' => 'OS Credential Dumping', 'description' => 'Dumping credentials from operating system.', 'tactic' => 'credential-access'],
            ['technique_id' => 'T1059', 'name' => 'Command and Scripting Interpreter', 'description' => 'Abuse of command-line interpreters such as PowerShell.', 'tactic' => 'execution'],
            ['technique_id' => 'T1059.001', 'name' => 'PowerShell', 'description' => 'Adversaries abuse PowerShell to execute commands, often encoded.', 'tactic' => 'execution', 'is_subtechnique' => true, 'parent_id' => 'T1059'],
            ['technique_id' => 'T1053', 'name' => 'Scheduled Task/Job', 'description' => 'Abuse of task scheduling for persistence/execution.', 'tactic' => 'execution'],
            ['technique_id' => 'T1133', 'name' => 'External Remote Services', 'description' => 'Use of external remote services such as VPN/SSH.', 'tactic' => 'initial-access'],
            ['technique_id' => 'T1071', 'name' => 'Application Layer Protocol', 'description' => 'C2 over common application protocols (HTTP/DNS).', 'tactic' => 'command-and-control'],
            ['technique_id' => 'T1105', 'name' => 'Ingress Tool Transfer', 'description' => 'Transfer of tools into the network from external systems.', 'tactic' => 'command-and-control'],
            ['technique_id' => 'T1083', 'name' => 'File and Directory Discovery', 'description' => 'Enumerating files and directories.', 'tactic' => 'discovery'],
            ['technique_id' => 'T1018', 'name' => 'Remote System Discovery', 'description' => 'Discovering remote systems on the network.', 'tactic' => 'discovery'],
            ['technique_id' => 'T1021', 'name' => 'Remote Services', 'description' => 'Use of remote services (RDP/SSH/SMB) for lateral movement.', 'tactic' => 'lateral-movement'],
            ['technique_id' => 'T1136', 'name' => 'Create Account', 'description' => 'Creating local or domain accounts for persistence.', 'tactic' => 'persistence'],
            ['technique_id' => 'T1562', 'name' => 'Impair Defenses', 'description' => 'Disabling or modifying security tooling.', 'tactic' => 'defense-evasion'],
            ['technique_id' => 'T1046', 'name' => 'Network Service Discovery', 'description' => 'Scanning for open ports/services.', 'tactic' => 'discovery'],
        ];
        foreach ($techs as $t) {
            $tactic = MitreTactic::where('name', 'like', '%'.explode('-', $t['tactic'])[0].'%')->first();
            MitreTechnique::firstOrCreate(['technique_id' => $t['technique_id']], [...$t, 'tactic_id' => $tactic?->id]);
        }
    }
}
