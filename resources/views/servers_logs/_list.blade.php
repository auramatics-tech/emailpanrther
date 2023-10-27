@if ($items->count() > 0)
<table class="table table-box pml-table mt-2" current-page="{{ empty(request()->page) ? 1 : empty(request()->page) }}">
    @foreach ($items as $key => $item)
    <tr>
        <td width="1%">
            <div class="text-nowrap">
                <div class="checkbox inline me-1">
                    <label>
                        <input type="checkbox" class="node styled" name="uids[]" value="{{ $item->id }}" />
                    </label>
                </div>
            </div>
        </td>
        <td>
            <span class="text-muted">{{$item->title}}</span>
        </td>
        <td>
            <div class="single-stat-box pull-left ml-20">
                <span class="text-muted">{{$item->response}}</span>
            </div>
        </td>
        <td>
        <td class="text-end text-nowrap pe-0">
            <a href="{{ action('ServerLogsController@edit', ['id' => $item->id]) }}" data-popup="tooltip" title="{{ trans('messages.edit') }}" role="button" class="btn btn-secondary btn-icon"><span class="material-symbols-rounded">edit</span> {{ trans('messages.edit') }}</a>
            <a class="btn btn-danger btn-icon" link-confirm="{{ trans('messages.delete_defult') }}" href="{{ action('ServerLogsController@delete', ['id' => $item->id]) }}">
                <span class="material-symbols-rounded">delete_outline</span> {{ trans('messages.delete') }}
            </a>
        </td>
    </tr>
    @endforeach
</table>
@include('elements/_per_page_select')

@elseif (!empty(request()->keyword) || !empty(request()->filters["type"]))
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
        {{ trans('messages.no_result') }}
    </span>
</div>
@endif