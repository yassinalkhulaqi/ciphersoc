@extends('reports.layout')
@section('title','Threat Intelligence Report')
@section('content')
<h2>Top IOCs by Threat Score</h2>
<table><tr><th>Value</th><th>Type</th><th>Reputation</th><th>Score</th><th>Status</th></tr>
@foreach($topIocs as $ioc)<tr><td>{{ Str::limit($ioc->value,60) }}</td><td>{{ $ioc->type }}</td><td>{{ $ioc->reputation }}</td><td>{{ $ioc->threat_score }}</td><td>{{ $ioc->status }}</td></tr>@endforeach
</table>
@endsection
