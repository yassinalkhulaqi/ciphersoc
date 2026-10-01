@extends('reports.layout')
@section('title','Incident Report')
@section('content')
<h2>Incident Overview</h2>
@foreach($incidents as $i)
<h3>{{ $i->incident_id }} &mdash; {{ $i->title }}</h3>
<p>Status: {{ $i->status }} | Severity: {{ $i->severity }} | Priority: {{ $i->priority }}</p>
<p>{{ $i->description }}</p>
@endforeach
<h2>Alert Overview</h2>
<table><tr><th>Title</th><th>Severity</th><th>Status</th></tr>
@foreach($alerts->take(50) as $a)<tr><td>{{ $a->title }}</td><td>{{ $a->severity }}</td><td>{{ $a->status }}</td></tr>@endforeach
</table>
@endsection
