<?php

namespace App\Policies;

use App\Models\User;

class SocPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAlerts(User $u): bool
    {
        return $u->hasPermission('alerts.view');
    }

    public function manageAlerts(User $u): bool
    {
        return $u->hasPermission('alerts.update');
    }

    public function viewIncidents(User $u): bool
    {
        return $u->hasPermission('incidents.view');
    }

    public function manageIncidents(User $u): bool
    {
        return $u->hasPermission('incidents.update');
    }

    public function manageRules(User $u): bool
    {
        return $u->hasPermission('rules.create') || $u->hasPermission('rules.update');
    }

    public function manageIocs(User $u): bool
    {
        return $u->hasPermission('iocs.create') || $u->hasPermission('iocs.update');
    }

    public function manageUsers(User $u): bool
    {
        return $u->hasPermission('users.manage');
    }
}
