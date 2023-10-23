@extends('layouts.core.frontend', [
'menu' => 'sending_server',
])

@section('title', trans('messages.sending_servers'))

@section('page_header')

<div class="page-title">
    <ul class="breadcrumb breadcrumb-caret position-right">
        <li class="breadcrumb-item"><a href="{{ action("HomeController@index") }}">{{ trans('messages.home') }}</a></li>
    </ul>
    <h1>
        <span class="text-semibold"><span class="material-symbols-rounded">format_list_bulleted</span> Error Logs</span>
    </h1>
</div>

@endsection

@section('content')
<div class="listing-form">
    <div class="pml-table-container" bis_skin_checked="1">
        <table class="table table-box pml-table mt-2" current-page="1">
            <tbody>
                @if(count($items))
                @foreach($items as $log)
                <tr>
                    <td>
                        <h5 class="m-0 text-bold">
                            <a class="kq_search d-block" href="https://app.emailpanther.com/sending_servers/{{ $log->uid }}/edit/smtp">{{ $log->name }}</a>
                        </h5>
                        <span class="text-muted">Error reported at: {{ $log->created_at }}</span>
                    </td>
                    <td>
                        <div class="single-stat-box pull-left ml-20" bis_skin_checked="1">
                            <span class="no-margin stat-num kq_search">{{ $log->error }}</span>
                            <br>
                            <span class="text-muted">Error</span>

                        </div>
                    </td>
                    <td>
                        <div class="single-stat-box pull-left ml-20" bis_skin_checked="1">
                            <span title="cohost.email" class="no-margin stat-num kq_search domain-truncate">{{ $log->host }}</span>
                            <br>
                            <span class="text-muted">Hostname</span>
                        </div>
                    </td>
                </tr>
                @endforeach
                @endif
            </tbody>
        </table>
        @include('elements/_per_page_select')
    </div>
</div>
@endsection