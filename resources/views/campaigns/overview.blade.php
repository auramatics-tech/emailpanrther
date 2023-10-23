@extends('layouts.core.frontend', [
    'menu' => 'campaign',
])

@section('title', $campaign->name)

@section('head')
    <script type="text/javascript" src="{{ URL::asset('core/echarts/echarts.min.js') }}"></script>
    <script type="text/javascript" src="{{ URL::asset('core/echarts/dark.js') }}"></script> 
@endsection

@section('page_header')

    @include("campaigns._header")

@endsection

@section('content')

    @include("campaigns._menu", [
        'menu' => 'overview',
    ])

    @include("campaigns._info")

    <br />

    @include("campaigns._chart")

@endsection
