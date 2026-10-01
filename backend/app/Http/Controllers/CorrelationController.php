<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\Detection\GraphCorrelator;
use App\Support\ApiResponse;

class CorrelationController extends Controller
{
    public function related(Alert $alert, GraphCorrelator $graph)
    {
        $alert->load('rule');

        return ApiResponse::ok($graph->related($alert));
    }

    public function suggestions(GraphCorrelator $graph)
    {
        return ApiResponse::ok($graph->incidentSuggestions());
    }
}
