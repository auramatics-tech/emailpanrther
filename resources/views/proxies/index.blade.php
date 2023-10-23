@extends('layouts.core.frontend', [
'menu' => 'proxies',
])

@section('title', 'Proxies')

@section('page_header')

<div class="page-title">
    <ul class="breadcrumb breadcrumb-caret position-right">
        <li class="breadcrumb-item"><a href="{{ action("HomeController@index") }}">{{ trans('messages.home') }}</a></li>
    </ul>
    <h1>
        <span class="text-semibold"><span class="material-symbols-rounded">format_list_bulleted</span> Proxies</span>
    </h1>
</div>

@endsection

@section('content')
<div class="listing-form" sort-url="{{ action('ProxiesController@sort') }}" data-url="{{ action('ProxiesController@listing') }}" per-page="{{ Acelle\Model\SendingServer::$itemsPerPage }}">
    <div class="d-flex top-list-controls top-sticky-content">
        <div class="me-auto">
            @if ($items->count() >= 0)
            <div class="filter-box">
            {{-- <div class="checkbox inline check_all_list">
                    <label>
                        <input type="checkbox" name="page_checked" class="styled check_all">
                    </label>
                </div>
               <span class="filter-group">
                    <span class="title text-semibold text-muted">{{ trans('messages.sort_by') }}</span>
                    <select class="select" name="sort_order">
                        <option value="domain_assignment.created_at">{{ trans('messages.created_at') }}</option>
                        <option value="domain_assignment.name">{{ trans('messages.name') }}</option>
                        <option value="domain_assignment.updated_at">{{ trans('messages.updated_at') }}</option>
                    </select>
                    <input type="hidden" name="sort_direction" value="desc" />
                    <button type="button" class="btn btn-light sort-direction" data-popup="tooltip" title="{{ trans('messages.change_sort_direction') }}" role="button" class="btn btn-xs">
                        <span class="material-symbols-rounded desc">sort</span>
                    </button>
                </span> 
                <span class="filter-group">
                    <span class="title text-semibold text-muted">Domain Registrar</span>
                    <select class="select" name="domain_registrar">
                        <option value="">{{ trans('messages.all') }}</option>
                        @foreach (Acelle\Model\DomainRegistrar::all() as $key => $type)
                        <option value="{{ $type->id }}">{{ ucfirst($type->registrar) }}</option>
                        @endforeach
                    </select>
                </span>
                <span class="filter-group">
                    <span class="title text-semibold text-muted">Postal Server</span>
                    <select class="select" name="postal_server">
                        <option value="">{{ trans('messages.all') }}</option>
                        @foreach (Acelle\Model\PostalServer::all() as $key => $type)
                        <option value="{{ $type->id }}">{{ ucfirst($type->postal_organization) }}</option>
                        @endforeach
                    </select>
                </span> --}}
                <span class="text-nowrap">
                    <input type="text" name="keyword" class="form-control search" value="{{ request()->keyword }}" placeholder="{{ trans('messages.type_to_search') }}" />
                    <span class="material-symbols-rounded">search</span>
                </span>
            </div>
            @endif
        </div>
        &nbsp;
        &nbsp;
        <div class="text-end">
            <a href="{{ action('ProxiesController@create') }}" role="button" class="btn btn-secondary">
                <span class="material-symbols-rounded">add</span> New Proxy
            </a>
        </div>
    </div>

    <div class="pml-table-container">
    </div>
</div>

<script>
    var ProxiesIndex = {
        getList: function() {
            return makeList({
                url: '{{ action('ProxiesController@listing') }}',
                container: $('.listing-form'),
                content: $('.pml-table-container')
            });
        }
    };

    $(function() {
        ProxiesIndex.getList().load();
    });
</script>
@endsection