<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('soc.alerts', fn ($user) => $user->hasPermission('alerts.view'));
Broadcast::channel('soc.incidents', fn ($user) => $user->hasPermission('incidents.view'));
Broadcast::channel('soc.agents', fn ($user) => $user->hasPermission('agents.view'));
Broadcast::channel('soc.notifications.{id}', fn ($user, $id) => (int) $user->id === (int) $id || $user->hasRole('admin'));
