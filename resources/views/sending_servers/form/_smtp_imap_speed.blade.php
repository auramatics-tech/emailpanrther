@extends('layouts.popup.small')

@section('content')

<form id="SMTPIMAPForm" action="{{ action('SendingServerController@new_multi_server', $server->uid) }}" method="POST" class="form-validate-jquery">
    {{ csrf_field() }}
    <!-- <input type="hidden" name="_method" value="multi_smtp"> -->
    <input type="hidden" name="uids" value="{{ $server->id }}">
    <div class="">
        @include('helpers.form_control', [
        'type' => 'text',
        'class' => '',
        'name' => 'default_from_email',
        'value' => '',
        'help_class' => 'sending_server',
        'rules' => $server->getConfigRules(),
        'label' => 'From email'
        ])
        <p>IMAP Details</p>

        <div id="radioset">
            <label>
                Pre-Filled Options
            </label><br><br>
            <input value="Gandi" type="radio" class="imap_prefilled" name="imap_prefilled">
            <label for="gandi">Gandi</label>&nbsp;&nbsp;
            <input type="radio" value="MXRoute" class="imap_prefilled" name="imap_prefilled">
            <label for="radio2">MXRoute</label><br><br>
        </div>

        @include('helpers.form_control', [
        'type' => 'text',
        'label' => 'IMAP Hostname',
        'class' => '',
        'name' => 'imap_host',
        'value' => '',
        'help_class' => 'sending_server',
        'rules' => $server->getRules(),
        ])

        @include('helpers.form_control', [
        'type' => 'text',
        'class' => '',
        'label' => 'IMAP Username',
        'name' => 'imap_username',
        'value' => '',
        'help_class' => 'sending_server',
        'rules' => $server->getRules(),
        ])

        @include('helpers.form_control', [
        'type' => 'password',
        'class' => '',
        'label' => 'IMAP Password',
        'name' => 'imap_password',
        'value' => '',
        'help_class' => 'sending_server',
        'rules' => $server->getRules(),
        'eye' => true,
        ])

        @include('helpers.form_control', [
        'type' => 'text',
        'class' => '',
        'name' => 'imap_port',
        'label' => 'IMAP Port',
        'value' => '',
        'help_class' => 'sending_server',
        'rules' => $server->getRules(),
        ])

        @include('helpers.form_control', [
        'type' => 'text',
        'class' => '',
        'name' => 'imap_protocol',
        'label' => 'IMAP Protocol',
        'value' => '',
        'help_class' => 'sending_server',
        'rules' => $server->getRules(),
        ])
        <p>{!! trans('messages.sending_servers.smtp.intro') !!}</p>

        <div id="radioset">
            <label>
                Pre-Filled Options
            </label><br><br>
            <input value="Cohost" type="radio" class="smtp_prefilled" name="smtp_prefilled">
            <label for="gandi">Cohost</label>&nbsp;&nbsp;
            <input type="radio" value="Sharedhost" class="smtp_prefilled" name="smtp_prefilled">
            <label for="radio2">Sharedhost</label>&nbsp;&nbsp;
            <input value="securehosting" type="radio" class="smtp_prefilled" name="smtp_prefilled">
            <label for="securehosting">Securehosting</label>&nbsp;&nbsp;
            <input type="radio" value="hostmail" class="smtp_prefilled" name="smtp_prefilled">
            <label for="hostmail">Hostmail</label>
            <br><br>
        </div>
        @include('helpers.form_control', [
        'type' => 'text',
        'class' => '',
        'name' => 'host',
        'value' => '',
        'help_class' => 'sending_server',
        'rules' => $server->getRules(),
        ])

        @include('helpers.form_control', [
        'type' => 'text',
        'class' => '',
        'name' => 'smtp_username',
        'value' => '',
        'help_class' => 'sending_server',
        'rules' => $server->getRules(),
        ])

        @include('helpers.form_control', [
        'type' => 'password',
        'class' => '',
        'name' => 'smtp_password',
        'value' => '',
        'help_class' => 'sending_server',
        'rules' => $server->getRules(),
        'eye' => true,
        ])

        @include('helpers.form_control', [
        'type' => 'text',
        'class' => '',
        'name' => 'smtp_port',
        'value' => '',
        'help_class' => 'sending_server',
        'rules' => $server->getRules(),
        ])

        @include('helpers.form_control', [
        'type' => 'text',
        'class' => '',
        'name' => 'smtp_protocol',
        'value' => '',
        'help_class' => 'sending_server',
        'rules' => $server->getRules(),
        ])

    </div>
    <div class="mt-4 text-left">
        <button type="submit" href="javascript:;" role="button" class="btn btn-secondary me-1">
            {{ trans('messages.save') }}
        </button>
        <button role="button" class="btn btn-default" data-dismiss="modal">{{ trans('messages.close') }}</button>
    </div>
</form>


<script>
    $('.imap_prefilled').on('change', function() {
        if ($(this).val() == 'Gandi') {
            $('#imap_host').val('mail.gandi.net')
            $('#imap_username').val($('#default_from_email').val())
            $('#imap_password').val('import3891')
            $('#imap_port').val('993')
        } else {
            $('#imap_host').val('monday.mxrouting.net')
            $('#imap_username').val($('#default_from_email').val())
            $('#imap_password').val('export3891')
            $('#imap_port').val('993')
        }
    })


    $('.smtp_prefilled').on('change', function() {
        if ($(this).val() == 'Cohost') {
            $('#host').val('cohost.email')
            $('#smtp_username').val('cohost/smtp1')
            $('#smtp_password').val('cW2yODsG3rJIKPRFuaquAUcJ')
            $('#smtp_port').val('587')
        } else if ($(this).val() == 'Sharedhost') {
            $('#host').val('sharedhost.email')
            $('#smtp_username').val('sharedhost/smtp1')
            $('#smtp_password').val('WZ1tkPuSWFidCPKkt6INt6Wq')
            $('#smtp_port').val('587')
        } else if ($(this).val() == 'securehosting') {
            $('#host').val('securehosting.email')
            $('#smtp_username').val('securehosting/smtp1')
            $('#smtp_password').val('fHSKBuwRlm7iH1bKwKSSbpZk')
            $('#smtp_port').val('587')
        } else if ($(this).val() == 'hostmail') {
            $('#host').val('hostmail.network')
            $('#smtp_username').val('hostmail/smtp1')
            $('#smtp_password').val('MT118H630k1nQkuwtUC5cXAO')
            $('#smtp_port').val('587')
        }
    })
</script>

@endsection