<?php

namespace App\Http\Controllers;

class ApiDocsController extends Controller
{
    public function openapi()
    {
        return response()->json([
            'openapi' => '3.0.3', 'info' => ['title' => 'cipherSOC API', 'version' => '1.0.0', 'description' => 'cipherSOC Security Operations Center REST API'],
            'servers' => [['url' => '/api/v1']],
            'security' => [['bearerAuth' => []]],
            'components' => ['securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer'], 'agentAuth' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Agent-ID']]],
            'paths' => [
                '/auth/login' => ['post' => ['summary' => 'Login', 'tags' => ['auth']]],
                '/dashboard/overview' => ['get' => ['summary' => 'SOC overview metrics', 'tags' => ['dashboard']]],
                '/events' => ['get' => ['summary' => 'List events', 'tags' => ['events']]],
                '/alerts' => ['get' => ['summary' => 'List alerts', 'tags' => ['alerts']]],
                '/incidents' => ['get' => ['summary' => 'List incidents', 'tags' => ['incidents']]],
                '/rules' => ['get' => ['summary' => 'List detection rules', 'tags' => ['rules']]],
                '/iocs' => ['get' => ['summary' => 'List IOCs', 'tags' => ['iocs']]],
                '/threat-intel/lookup' => ['post' => ['summary' => 'Threat intel lookup', 'tags' => ['threat-intel']]],
                '/agents' => ['get' => ['summary' => 'List agents', 'tags' => ['agents']]],
                '/agents/register' => ['post' => ['summary' => 'Register agent', 'tags' => ['agents']]],
                '/ingest/events' => ['post' => ['summary' => 'Ingest events (agent auth)', 'tags' => ['ingest'], 'security' => [['agentAuth' => []]]]],
                '/mitre/techniques' => ['get' => ['summary' => 'MITRE techniques', 'tags' => ['mitre']]],
                '/reports' => ['get' => ['summary' => 'List reports', 'tags' => ['reports']]],
                '/audit-logs' => ['get' => ['summary' => 'Audit logs', 'tags' => ['audit']]],
                '/health' => ['get' => ['summary' => 'Health', 'tags' => ['ops'], 'security' => []]],
            ],
        ]);
    }
}
