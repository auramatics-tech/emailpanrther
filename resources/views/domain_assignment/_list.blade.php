@if ($items->count() > 0)

<table class="table table-box pml-table mt-2" current-page="{{ empty(request()->page) ? 1 : empty(request()->page) }}">
    @foreach ($items as $key => $item)
    <tr>
        <td>
            <div class="single-stat-box pull-left ml-20" bis_skin_checked="1">
                <span class="no-margin stat-num kq_search">{{ $item->domain }}</span>
                <br>
                <span class="text-muted">{{ trans('messages.created_at') }}: {{ Auth::user()->customer->formatDateTime($item->created_at, 'datetime_full') }}</span>
            </div>
        </td>
        <td>
            <div class="single-stat-box pull-left ml-20" bis_skin_checked="1">
                <span class="no-margin stat-num kq_search">{{ ucfirst($item->getDomainRegistrar->registrar) }}</span>
                <br>
                <span class="text-muted">Domain Registrar</span>
            </div>
        </td>
        <td>
            <div class="single-stat-box pull-left ml-20" bis_skin_checked="1">
                <span class="no-margin stat-num kq_search">{{ ucfirst($item->getPostalServer->postal_organization) }}</span>
                <br>
                <span class="text-muted">Postal Server</span>
            </div>
        </td>
        <td>
            <div class="single-stat-box pull-left ml-20" bis_skin_checked="1">
                <span class="no-margin stat-num kq_search">{{ ($item->mx_route) ? 'Yes' : 'No' }}</span>
                <br>
                <span class="text-muted">MX Route</span>
                @if($item->mx_route && $item->mx_status)
                <br>
                <span class="text-muted2 list-status pull-left">
                    <span @if($item->mx_status == 'fail') title="{{ $item->mx_error }}" @endif class="label label-flat bg-{{ $item->mx_status }}">{{ trans('messages.domain_assignment_status_' . $item->mx_status) }}</span>
                </span>
                @endif
            </div>
        </td>
        <td>
            <span class="text-muted2 list-status pull-left">
                <span @if($item->status == 'fail') title="{{ $item->error }}" @endif class="label label-flat bg-{{ $item->status }}">{{ trans('messages.domain_assignment_status_' . $item->status) }}</span>
            </span>
        </td>
    </tr>
    @endforeach
</table>
@include('elements/_per_page_select')

@elseif (!empty(request()->keyword) || !empty(request()->filters["domain_registrar"]) || !empty(request()->filters["postal_server"]))
<div class="empty-list">
    <span class="material-symbols-rounded">dns</span>
    <span class="line-1">
        {{ trans('messages.no_search_result') }}
    </span>
</div>
@else
<div class="empty-list">
    <span class="material-symbols-rounded">dns</span>
    <span class="line-1">
        No Domain assigned
    </span>
</div>
@endif