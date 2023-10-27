<form id="editServerForm" action="{{ action('ServerLogsController@store') }}" method="POST" class="form-validate-jqueryz">
    {{ csrf_field() }}
    <input type="hidden" name="id" value="{{ isset($item->id) ? $item->id : ''}}">
    <div class="mc_section" id="main_form_section">
        <div class="row" id="form_section_0">
            <div class="col-md-12">
                @include('helpers.form_control', [
                'type' => 'text',
                'class' => '',
                'name' => 'title',
                'label' => 'Title',
                'value' => isset($item->title) ? $item->title : old('title')
                ])
            </div>
            <div class="col-md-12">
                <div class="form-group control-text">
                    <label for="">Response</label>
                    <textarea name="response" id="" class="form-control" rows="10">{{isset($item->response) ? $item->response : old('response')}}</textarea>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-secondary me-2">{{ trans('messages.save') }}</button>
</form>