@extends('reports.layout')
@section('title','Alert Report')
@section('content')
<h2>Alert Overview ({{ $alerts->count() }})</h2>
<table><tr><th>Title</th><th>Severity</th><th>Status</th><th>Risk</th><th>Created</th></tr>
@foreach($alerts->take(100) as $a)<tr><td>{{ $a->title }}</td><td>{{ $a->severity }}</td><td>{{ $a->status }}</td><td>{{ $a->risk_score }}</td><td>{{ $a->created_at }}</td></tr>@endforeach
</table>
@endsection
