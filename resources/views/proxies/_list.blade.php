@if ($items->count() > 0)

<table class="table table-box pml-table mt-2" current-page="{{ empty(request()->page) ? 1 : empty(request()->page) }}">
    @foreach ($items as $key => $item)
    <tr>
        <td>
            <div class="single-stat-box pull-left ml-20" bis_skin_checked="1">
                <span class="no-margin stat-num kq_search">{{ $item->ip_address }}</span>
                <br>
                <span class="text-muted">{{ trans('messages.created_at') }}: {{ Auth::user()->customer->formatDateTime($item->created_at, 'datetime_full') }}</span>
            </div>
        </td>
        <td>
            <div class="single-stat-box pull-left ml-20" bis_skin_checked="1">
                <span class="no-margin stat-num kq_search">{{ ucfirst($item->port) }}</span>
                <br>
                <span class="text-muted">Port</span>
            </div>
        </td>
        <td>
            <span class="text-muted2 list-status pull-left">
                <span class="label label-flat @if($item->status == 1) bg-success @else bg-danger @endif"> @if($item->status == 1)
                    Active
                    @else
                    Inactive
                    @endif</span>
            </span>
        </td>
        <td class="text-end text-nowrap pe-0">
            <a href="{{ action('ProxiesController@edit', ["proxy" => $item->id]) }}" data-popup="tooltip" title="{{ trans('messages.edit') }}" role="button" class="btn btn-secondary btn-icon"><span class="material-symbols-rounded">edit</span> {{ trans('messages.edit') }}</a>
           
            <a class="" link-confirm="" href="{{ route('delete-proxy', ["proxy" => $item->id]) }}"   class="btn btn-secondary btn-icon">
                <span class="material-symbols-rounded">delete_outline</span> {{ trans('messages.delete') }}
            </a>
          
        </td>
    </tr>
    @endforeach
</table>
@include('elements/_per_page_select')

{{--@elseif (!empty(request()->keyword) || !empty(request()->filters["domain_registrar"]) || !empty(request()->filters["postal_server"]))
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
</div>--}}
@endif