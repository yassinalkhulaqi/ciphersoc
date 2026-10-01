<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $all = AppSetting::all()->groupBy('group')->map(fn ($g) => $g->pluck('value', 'key'));

        return ApiResponse::ok($all);
    }

    public function update(Request $r)
    {
        $data = $r->validate(['settings' => 'required|array']);
        foreach ($data['settings'] as $k => $v) {
            if (! preg_match('/^[a-z0-9_\.\-]+$/i', $k)) {
                continue;
            }
            AppSetting::updateOrCreate(['key' => $k], ['value' => is_array($v) ? $v : ['v' => $v], 'group' => $r->get('group', 'general')]);
        }
        AuditLogger::log('settings.update', 'settings', null, null, $data);

        return ApiResponse::ok(null, 'Settings saved');
    }
}
