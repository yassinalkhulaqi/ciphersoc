@extends('reports.layout')
@section('title','SOC Summary Report')
@section('content')
<h2>Executive Summary</h2>
<p>Period {{ $from }} &ndash; {{ $to }}. {{ $alerts->count() }} alerts, {{ $incidents->count() }} incidents.</p>
<h2>Severity Distribution</h2>
<table><tr><th>Severity</th><th>Count</th></tr>
@foreach($bySev as $sev=>$count)<tr><td><span class="badge {{ $sev }}">{{ $sev }}</span></td><td>{{ $count }}</td></tr>@endforeach
</table>
<h2>Top Detection Rules</h2>
<table><tr><th>Rule</th><th>Alerts</th></tr>
@foreach($topRules as $name=>$count)<tr><td>{{ $name }}</td><td>{{ $count }}</td></tr>@endforeach
</table>
<h2>Recent Alerts</h2>
<table><tr><th>Title</th><th>Severity</th><th>Status</th><th>Created</th></tr>
@foreach($alerts->take(30) as $a)<tr><td>{{ $a->title }}</td><td>{{ $a->severity }}</td><td>{{ $a->status }}</td><td>{{ $a->created_at }}</td></tr>@endforeach
</table>
<h2>Incidents</h2>
<table><tr><th>ID</th><th>Title</th><th>Status</th><th>Severity</th></tr>
@foreach($incidents as $i)<tr><td>{{ $i->incident_id }}</td><td>{{ $i->title }}</td><td>{{ $i->status }}</td><td>{{ $i->severity }}</td></tr>@endforeach
</table>
<h2>Top IOCs</h2>
<table><tr><th>Value</th><th>Type</th><th>Reputation</th><th>Score</th></tr>
@foreach($topIocs as $ioc)<tr><td>{{ Str::limit($ioc->value,60) }}</td><td>{{ $ioc->type }}</td><td>{{ $ioc->reputation }}</td><td>{{ $ioc->threat_score }}</td></tr>@endforeach
</table>
@endsection
