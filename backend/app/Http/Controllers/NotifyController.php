<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Notification;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class NotifyController extends Controller
{
    public function index(Request $r)
    {
        $q = Notification::where(fn ($qq) => $qq->where('user_id', $r->user()->id)->orWhereNull('user_id'))->orderByDesc('created_at');

        return ApiResponse::paginated($q->paginate(25));
    }

    public function read(Request $r, Notification $notification)
    {
        $notification->update(['read_at' => now()]);

        return ApiResponse::ok($notification, 'Marked read');
    }

    public function readAll(Request $r)
    {
        Notification::where('user_id', $r->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return ApiResponse::ok(null, 'All marked read');
    }

    public function preferences(Request $r)
    {
        $prefs = AppSetting::where('key', 'notify_prefs_'.$r->user()->id)->first();
        if ($r->isMethod('put')) {
            $data = $r->validate(['critical_alert' => 'sometimes|boolean', 'incident_escalation' => 'sometimes|boolean', 'agent_offline' => 'sometimes|boolean', 'threatintel_hit' => 'sometimes|boolean']);
            AppSetting::updateOrCreate(['key' => 'notify_prefs_'.$r->user()->id], ['value' => $data, 'group' => 'notifications']);

            return ApiResponse::ok($data, 'Preferences saved');
        }

        return ApiResponse::ok($prefs?->value ?? ['critical_alert' => true, 'incident_escalation' => true, 'agent_offline' => true, 'threatintel_hit' => true]);
    }
}
