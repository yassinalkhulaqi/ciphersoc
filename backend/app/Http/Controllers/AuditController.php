<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $r)
    {
        $q = AuditLog::with('actor')->orderByDesc('created_at');
        if ($r->filled('action')) {
            $q->where('action', 'like', '%'.$r->get('action').'%');
        }
        if ($r->filled('resource_type')) {
            $q->where('resource_type', $r->get('resource_type'));
        }
        if ($r->filled('actor_id')) {
            $q->where('actor_id', $r->get('actor_id'));
        }
        if ($r->filled('from')) {
            $q->where('created_at', '>=', $r->get('from'));
        }
        if ($r->filled('to')) {
            $q->where('created_at', '<=', $r->get('to'));
        }

        return ApiResponse::paginated($q->paginate(min(200, (int) $r->get('per_page', 25))));
    }
}
