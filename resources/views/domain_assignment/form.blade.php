<form id="editServerForm" action="{{ action('DomainAssignmentController@store') }}" method="POST" class="form-validate-jqueryz">
    {{ csrf_field() }}
    <div class="mc_section">
        <div class="row">
            <div class="col-md-6">
                @include('helpers.form_control', [
                'type' => 'textarea',
                'class' => '',
                'name' => 'domain',
                'label' => 'Domains',
                'value' => old('domain'),
                'rows' => 12
                ])
            </div>

            <div class="col-md-6">
                <div id="radioset">
                    <label>
                        Domain Registrar
                    </label><br><br>
                    @foreach(\Acelle\Model\DomainRegistrar::all() as $DomainRegistrar)
                    <input @if(old('domain_registrar') == $DomainRegistrar->id) checked @endif value="{{ $DomainRegistrar->id }}" type="radio" class="domain_registrar" name="domain_registrar">
                    <label for="{{ ucfirst($DomainRegistrar->registrar) }}">{{ ucfirst($DomainRegistrar->registrar) }}</label>&nbsp;&nbsp;
                    @endforeach
                </div>
                <br>
                <br>
                <div id="radioset">
                    <label>
                        Postal Server
                    </label><br><br>
                    @foreach(\Acelle\Model\PostalServer::all() as $PostalServer)
                    <input @if(old('PostalServer') == $PostalServer->id) checked @endif value="{{ $PostalServer->id }}" type="radio" class="PostalServer" name="PostalServer">
                    <label for="{{ ucfirst($PostalServer->postal_organization) }}">{{ ucfirst($PostalServer->postal_organization) }}</label>&nbsp;&nbsp;
                    @endforeach
                </div>
                <br>
                <br>
                <div id="radioset">
                    <label>
                        MXRoute
                    </label><br>
                    <input value="1" type="checkbox" class="mxroute" name="mx_route">
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-secondary me-2">{{ trans('messages.save') }}</button>
    </div>
</form>