@extends('layouts.core.frontend', [
'menu' => 'sending_server',
])

@section('title', 'New Domain Assignment')

@section('page_header')

<div class="page-title">
    <ul class="breadcrumb breadcrumb-caret position-right">
        <li class="breadcrumb-item"><a href="{{ action("HomeController@index") }}">{{ trans('messages.home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ action("DomainAssignmentController@index") }}">Domain Assignment</a></li>
    </ul>
    <h1 class="mc-h1">
        <span class="text-semibold">New Domain Assignment</span>
    </h1>
</div>

@endsection

@section('content')

@include('domain_assignment.form')

@endsection