@extends('layouts.module', ['active' => request()->routeIs('qc-finding.*') ? 'qc_finding' : (request()->routeIs('ncr.*') ? 'ncr' : 'mdr'), 'pageTitle' => $title])

@section('content')
    <div class="flex min-h-56 items-center justify-center rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
        Belum ada data
    </div>
@endsection
