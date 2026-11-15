@extends('layouts.module', [
    'active' => 'ncr',
    'pageTitle' => 'Buat NCR',
    'pageCaption' => 'PT Mudalaya Indo',
])

@section('header_action')
    <a href="{{ route('ncr.index') }}"
       class="inline-flex min-h-[44px] items-center justify-center rounded-full border border-line bg-white px-5 text-[13px] font-semibold text-ink transition hover:bg-paper-soft">
        &larr; Kembali
    </a>
@endsection

@section('content')
    <form id="ncr-form" method="POST" action="{{ route('ncr.store') }}">
        @csrf
        @include('modules.ncr._form')
    </form>
@endsection
