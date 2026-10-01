<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function health()
    {
        $db = 'ok';
        try {
            DB::select('select 1');
        } catch (\Throwable $e) {
            $db = 'error: '.$e->getMessage();
        }
        $redis = 'ok';
        try {
            Redis::ping();
        } catch (\Throwable $e) {
            $redis = 'unavailable: '.$e->getMessage();
        }
        $queueSize = null;
        try {
            $queueSize = DB::table('jobs')->count();
        } catch (\Throwable) {
        }

        return ApiResponse::ok(['app' => 'cipherSOC', 'version' => '1.0.0', 'database' => $db, 'redis' => $redis, 'queue_pending' => $queueSize, 'websocket' => config('reverb.app.key') ? 'configured' : 'not_configured', 'providers' => ['virustotal' => (bool) config('services.threatintel.virustotal_key'), 'abuseipdb' => (bool) config('services.threatintel.abuseipdb_key'), 'otx' => (bool) config('services.threatintel.otx_key'), 'urlhaus' => true], 'time' => now()]);
    }
}
