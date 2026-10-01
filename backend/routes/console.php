<?php

use App\Jobs\AgentHealthCheckJob;
use App\Jobs\ExpireIocsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new AgentHealthCheckJob)->everyFiveMinutes();
Schedule::job(new ExpireIocsJob)->hourly();
Schedule::command('queue:prune-failed --hours=72')->daily();
Schedule::command('events:prune --days=90 --alerts-days=365')->dailyAt('02:30');
