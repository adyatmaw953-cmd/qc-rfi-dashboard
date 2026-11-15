@extends('layouts.module', ['active' => 'qc_finding', 'pageTitle' => 'QC Finding'])

@section('content')
    <x-qc-finding-overview
        :overview="$overview"
        :base-url="route('qc-finding.index')"
    />
@endsection
