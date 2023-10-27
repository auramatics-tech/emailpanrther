<form id="editServerForm" action="{{ action('ProxiesController@store') }}" method="POST" class="form-validate-jqueryz">
    {{ csrf_field() }}
    <input type="hidden" name="proxy[]" value="{{ isset($item->id) ? $item->id : ''}}">
    <div class="mc_section" id="main_form_section">
        <div class="row" id="form_section_0">
            <div class="col-md-6">
                @include('helpers.form_control', [
                'type' => 'text',
                'class' => '',
                'name' => 'ip_address[]',
                'label' => 'Ip Address',
                'value' => isset($item->ip_address) ? $item->ip_address : old('ip_address')
                ])
            </div>
            <div class="col-md-4">
                @include('helpers.form_control', [
                'type' => 'text',
                'class' => '',
                'name' => 'port[]',
                'label' => 'Port',
                'value' => isset($item->port) ? $item->port : old('port')
                ])
            </div>
            <div class="col-md-2">
                <a href="javascript:" role="button" class="btn btn-secondary mt-4 add_form">
                    <span class="material-symbols-rounded">add</span> More Proxy
                </a>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-secondary me-2">{{ trans('messages.save') }}</button>
</form>