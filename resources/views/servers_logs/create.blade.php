@extends('layouts.core.frontend', [
'menu' => 'Server Logs',
])

@section('title', 'Server Logs')

@section('page_header')

<div class="page-title">
    <ul class="breadcrumb breadcrumb-caret position-right">
        <li class="breadcrumb-item"><a href="{{ action("HomeController@index") }}">{{ trans('messages.home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ action("ServerLogsController@index") }}">Server Logs</a></li>
    </ul>
    <h1 class="mc-h1">
        <span class="text-semibold">Server Logs</span>
    </h1>
</div>

@endsection

@section('content')

@include('servers_logs.form')

@endsection
