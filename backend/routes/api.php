<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CorrelationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IngestController;
use App\Http\Controllers\IocController;
use App\Http\Controllers\LogStreamController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\MitreController;
use App\Http\Controllers\NotifyController;
use App\Http\Controllers\PlaybookController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RuleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ThreatIntelController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'health']);
Route::get('/openapi.json', [ApiDocsController::class, 'openapi']);

// Agent public + agent-auth endpoints
Route::prefix('v1')->group(function () {
    Route::post('/agents/register', [AgentController::class, 'register'])->middleware('throttle:30,1');
    Route::post('/agents/heartbeat', [AgentController::class, 'heartbeat'])->middleware(['agent.auth', 'throttle:120,1']);
    Route::post('/ingest/events', [IngestController::class, 'ingest'])->middleware(['agent.auth', 'throttle:120,1']);
});

// Auth
Route::prefix('v1/auth')->middleware('throttle:30,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/tokens', [AuthController::class, 'tokens']);
        Route::post('/tokens', [AuthController::class, 'createToken']);
        Route::delete('/tokens/{id}', [AuthController::class, 'revokeToken']);
        Route::get('/mfa/setup', [AuthController::class, 'mfaSetup']);
        Route::post('/mfa/enable', [AuthController::class, 'mfaEnable']);
        Route::post('/mfa/disable', [AuthController::class, 'mfaDisable']);
    });
});

// Authenticated SOC API
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {
    Route::get('/dashboard/overview', [DashboardController::class, 'overview'])->middleware('perm:dashboard.view');
    Route::get('/dashboard/kpis', [DashboardController::class, 'kpis'])->middleware('perm:dashboard.view');
    Route::get('/dashboard/timeline', [DashboardController::class, 'timeline'])->middleware('perm:dashboard.view');
    Route::get('/events', [EventController::class, 'index'])->middleware('perm:events.view');
    Route::get('/events/{event}', [EventController::class, 'show'])->middleware('perm:events.view');
    Route::get('/logs', [EventController::class, 'index'])->middleware('perm:events.view');
    Route::get('/logs/stream', [LogStreamController::class, 'stream'])->middleware('perm:events.view');
    Route::get('/alerts', [AlertController::class, 'index'])->middleware('perm:alerts.view');
    Route::get('/alerts/{alert}', [AlertController::class, 'show'])->middleware('perm:alerts.view');
    Route::patch('/alerts/{alert}', [AlertController::class, 'update'])->middleware('perm:alerts.update');
    Route::post('/alerts/{alert}/assign', [AlertController::class, 'assign'])->middleware('perm:alerts.assign');
    Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->middleware('perm:alerts.update');
    Route::post('/alerts/bulk', [AlertController::class, 'bulk'])->middleware('perm:alerts.update');
    Route::post('/alerts/{alert}/comments', [AlertController::class, 'comment'])->middleware('perm:alerts.update');
    Route::get('/incidents', [IncidentController::class, 'index'])->middleware('perm:incidents.view');
    Route::post('/incidents', [IncidentController::class, 'store'])->middleware('perm:incidents.create');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->middleware('perm:incidents.view');
    Route::patch('/incidents/{incident}', [IncidentController::class, 'update'])->middleware('perm:incidents.update');
    Route::post('/incidents/{incident}/alerts', [IncidentController::class, 'attachAlerts'])->middleware('perm:incidents.update');
    Route::post('/incidents/{incident}/iocs', [IncidentController::class, 'attachIocs'])->middleware('perm:incidents.update');
    Route::post('/incidents/{incident}/comments', [IncidentController::class, 'comment'])->middleware('perm:incidents.update');
    Route::post('/incidents/{incident}/timeline', [IncidentController::class, 'timeline'])->middleware('perm:incidents.update');
    Route::post('/incidents/{incident}/escalate', [IncidentController::class, 'escalate'])->middleware('perm:incidents.update');
    Route::get('/assets', [AssetController::class, 'index'])->middleware('perm:agents.view');
    Route::post('/assets', [AssetController::class, 'store'])->middleware('perm:agents.manage');
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->middleware('perm:agents.view');
    Route::get('/assets/{asset}/vulns', [AssetController::class, 'vulns'])->middleware('perm:agents.view');
    Route::get('/network/topology', [AssetController::class, 'topology'])->middleware('perm:agents.view');
    Route::get('/rules', [RuleController::class, 'index'])->middleware('perm:rules.view');
    Route::post('/rules', [RuleController::class, 'store'])->middleware('perm:rules.create');
    Route::post('/rules/import-sigma', [RuleController::class, 'importSigma'])->middleware('perm:rules.create');
    Route::get('/rules/{rule}', [RuleController::class, 'show'])->middleware('perm:rules.view');
    Route::patch('/rules/{rule}', [RuleController::class, 'update'])->middleware('perm:rules.update');
    Route::delete('/rules/{rule}', [RuleController::class, 'destroy'])->middleware('perm:rules.delete');
    Route::post('/rules/{rule}/test', [RuleController::class, 'test'])->middleware('perm:rules.view');
    Route::get('/correlations/alerts/{alert}', [CorrelationController::class, 'related'])->middleware('perm:correlations.view');
    Route::get('/correlations/suggestions', [CorrelationController::class, 'suggestions'])->middleware('perm:correlations.view');
    Route::get('/mitre/coverage', [MitreController::class, 'coverage'])->middleware('perm:dashboard.view');
    Route::get('/playbooks', [PlaybookController::class, 'index'])->middleware('perm:playbooks.view');
    Route::post('/playbooks', [PlaybookController::class, 'store'])->middleware('perm:playbooks.manage');
    Route::get('/playbooks/{playbook}', [PlaybookController::class, 'show'])->middleware('perm:playbooks.view');
    Route::patch('/playbooks/{playbook}', [PlaybookController::class, 'update'])->middleware('perm:playbooks.manage');
    Route::delete('/playbooks/{playbook}', [PlaybookController::class, 'destroy'])->middleware('perm:playbooks.manage');
    Route::post('/playbooks/{playbook}/run', [PlaybookController::class, 'run'])->middleware('perm:playbooks.view');
    Route::get('/metrics', [MetricsController::class, 'metrics'])->middleware('perm:dashboard.view');
    Route::get('/iocs', [IocController::class, 'index'])->middleware('perm:iocs.view');
    Route::post('/iocs', [IocController::class, 'store'])->middleware('perm:iocs.create');
    Route::get('/iocs/{ioc}', [IocController::class, 'show'])->middleware('perm:iocs.view');
    Route::patch('/iocs/{ioc}', [IocController::class, 'update'])->middleware('perm:iocs.update');
    Route::delete('/iocs/{ioc}', [IocController::class, 'destroy'])->middleware('perm:iocs.delete');
    Route::post('/iocs/{ioc}/enrich', [IocController::class, 'enrich'])->middleware('perm:iocs.update');
    Route::get('/threat-intel/providers', [ThreatIntelController::class, 'providers'])->middleware('perm:threatintel.view');
    Route::post('/threat-intel/lookup', [ThreatIntelController::class, 'lookup'])->middleware('perm:threatintel.view');
    Route::get('/threat-intel/results', [ThreatIntelController::class, 'results'])->middleware('perm:threatintel.view');
    Route::get('/mitre/tactics', [MitreController::class, 'tactics'])->middleware('perm:dashboard.view');
    Route::get('/mitre/techniques', [MitreController::class, 'techniques'])->middleware('perm:dashboard.view');
    Route::get('/mitre/techniques/{id}', [MitreController::class, 'show'])->middleware('perm:dashboard.view');
    Route::get('/agents', [AgentController::class, 'index'])->middleware('perm:agents.view');
    Route::get('/agents/{agent}', [AgentController::class, 'show'])->middleware('perm:agents.view');
    Route::delete('/agents/{agent}', [AgentController::class, 'destroy'])->middleware('perm:agents.manage');
    Route::get('/hosts', [AgentController::class, 'hosts'])->middleware('perm:agents.view');
    Route::get('/users', [UserController::class, 'index'])->middleware('perm:users.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('perm:users.manage');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('perm:users.view');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('perm:users.manage');
    Route::get('/roles', [UserController::class, 'roles'])->middleware('perm:users.view');
    Route::get('/audit-logs', [AuditController::class, 'index'])->middleware('perm:audit.view');
    Route::get('/audit', [AuditController::class, 'index'])->middleware('perm:audit.view');
    Route::get('/reports', [ReportController::class, 'index'])->middleware('perm:reports.view');
    Route::post('/reports', [ReportController::class, 'store'])->middleware('perm:reports.generate');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->middleware('perm:reports.view');
    Route::get('/reports/{report}/download', [ReportController::class, 'download'])->middleware('perm:reports.view');
    Route::post('/reports/{report}/link', [ReportController::class, 'link'])->middleware('perm:reports.view');
    // Signed, short-lived file fetch for direct browser download (signature is the credential).
    Route::get('/reports/file/{report}', [ReportController::class, 'download'])->name('report.download')->middleware(['signed', 'throttle:60,1']);
    Route::get('/notifications', [NotifyController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotifyController::class, 'read']);
    Route::post('/notifications/read-all', [NotifyController::class, 'readAll']);
    Route::get('/notifications/preferences', [NotifyController::class, 'preferences']);
    Route::put('/notifications/preferences', [NotifyController::class, 'preferences']);
    Route::get('/settings', [SettingsController::class, 'index'])->middleware('perm:settings.manage');
    Route::put('/settings', [SettingsController::class, 'update'])->middleware('perm:settings.manage');
});
