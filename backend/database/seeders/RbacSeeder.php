<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $perms = ['dashboard.view', 'alerts.view', 'alerts.create', 'alerts.update', 'alerts.assign', 'alerts.close', 'incidents.view', 'incidents.create', 'incidents.update', 'incidents.close', 'events.view', 'rules.view', 'rules.create', 'rules.update', 'rules.delete', 'iocs.view', 'iocs.create', 'iocs.update', 'iocs.delete', 'threatintel.view', 'agents.view', 'agents.manage', 'reports.view', 'reports.generate', 'users.view', 'users.manage', 'audit.view', 'settings.manage', 'api.manage'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p], ['group' => explode('.', $p)[0]]);
        }
        $matrix = [
            'admin' => $perms,
            'manager' => array_filter($perms, fn ($p) => ! in_array($p, ['users.manage', 'settings.manage'])),
            'analyst' => ['dashboard.view', 'alerts.view', 'alerts.create', 'alerts.update', 'alerts.assign', 'incidents.view', 'incidents.create', 'incidents.update', 'events.view', 'rules.view', 'iocs.view', 'iocs.create', 'iocs.update', 'threatintel.view', 'agents.view', 'reports.view', 'reports.generate', 'api.manage'],
            'viewer' => ['dashboard.view', 'alerts.view', 'incidents.view', 'events.view', 'rules.view', 'iocs.view', 'threatintel.view', 'agents.view', 'reports.view'],
        ];
        foreach ($matrix as $role => $list) {
            $r = Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)]);
            $r->permissions()->sync(Permission::whereIn('name', $list)->pluck('id'));
        }
    }
}
