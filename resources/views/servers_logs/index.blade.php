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
        <span class="text-semibold"><span class="material-symbols-rounded">format_list_bulleted</span> Server Logs</span>
    </h1>
</div>

@endsection

@section('content')


<div class="listing-form" sort-url="{{ action('SendingServerController@sort') }}" data-url="{{ action('SendingServerController@listing') }}" per-page="{{ Acelle\Model\SendingServer::$itemsPerPage }}">
    <div class="d-flex top-list-controls top-sticky-content">
        <div class="me-auto">
            @if ($items->count() >= 0)
            <div class="filter-box">
                <div class="checkbox inline check_all_list">
                    <label>
                        <input type="checkbox" name="page_checked" class="styled check_all">
                    </label>
                </div>
                <span class="text-nowrap">
                    <input type="text" name="keyword" class="form-control search" value="{{ request()->keyword }}" placeholder="{{ trans('messages.type_to_search') }}" />
                    <span class="material-symbols-rounded">search</span>
                </span>
            </div>
            @endif
        </div>
        &nbsp;
        &nbsp;
    </div>

    <div class="pml-table-container">
    </div>
</div>

<script>
    var ServersLogsIndex = {
        getList: function() {
            return makeList({
                url: "{{ action('ServerLogsController@listing') }}",
                container: $('.listing-form'),
                content: $('.pml-table-container')
            });
        }
    };

    $(function() {
        ServersLogsIndex.getList().load();
    });
</script>
@endsection