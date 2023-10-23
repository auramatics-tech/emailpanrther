@extends('layouts.core.frontend', [
'menu' => 'proxies',
])

@section('title', 'New Proxies')

@section('page_header')

<div class="page-title">
    <ul class="breadcrumb breadcrumb-caret position-right">
        <li class="breadcrumb-item"><a href="{{ action("HomeController@index") }}">{{ trans('messages.home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ action("ProxiesController@index") }}">Proxies</a></li>
    </ul>
    <h1 class="mc-h1">
        <span class="text-semibold">New proxies</span>
    </h1>
</div>

@endsection

@section('content')

@include('proxies.form')

@endsection