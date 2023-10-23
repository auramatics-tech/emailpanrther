@if (!$server->id)
<form id="editServerForm" action="{{ action('SendingServerController@store', ["type" => request()->type]) }}" method="POST" class="form-validate-jqueryz">
    {{ csrf_field() }}
    <input type="hidden" name="type" value="{{ $server->type }}" />
    @else
    <form id="editServerForm" enctype="multipart/form-data" action="{{ action('SendingServerController@update', [$server->uid, $server->type]) }}" method="POST" class="form-validate-jqueryz">
        <input type="hidden" name="_method" value="PATCH">
        {{ csrf_field() }}
        @endif

        <div class="mc_section">
            <div class="row">
                <div class="col-md-6">


                    @include('helpers.form_control', [
                    'type' => 'text',
                    'class' => '',
                    'name' => 'default_from_email',
                    'value' => $server->default_from_email,
                    'help_class' => 'sending_server',
                    'rules' => $server->getConfigRules(),
                    'disabled' =>($server->id && $errors->isEmpty()),
                    'label' => 'Default from email'
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
                    'value' => $server->imap_host,
                    'help_class' => 'sending_server',
                    'rules' => $server->getRules(),
                    'disabled' =>($server->id && $errors->isEmpty()),
                    ])

                    @include('helpers.form_control', [
                    'type' => 'text',
                    'class' => '',
                    'label' => 'IMAP Username',
                    'name' => 'imap_username',
                    'value' => $server->imap_username,
                    'help_class' => 'sending_server',
                    'rules' => $server->getRules(),
                    'disabled' =>($server->id && $errors->isEmpty()),
                    ])

                    @include('helpers.form_control', [
                    'type' => 'password',
                    'class' => '',
                    'label' => 'IMAP Password',
                    'name' => 'imap_password',
                    'value' => $server->imap_password,
                    'help_class' => 'sending_server',
                    'rules' => $server->getRules(),
                    'eye' => true,
                    'disabled' =>($server->id && $errors->isEmpty()),
                    ])

                    @include('helpers.form_control', [
                    'type' => 'text',
                    'class' => '',
                    'name' => 'imap_port',
                    'label' => 'IMAP Port',
                    'value' => $server->imap_port,
                    'help_class' => 'sending_server',
                    'rules' => $server->getRules(),
                    'disabled' =>($server->id && $errors->isEmpty()),
                    ])

                    @include('helpers.form_control', [
                    'type' => 'text',
                    'class' => '',
                    'name' => 'imap_protocol',
                    'label' => 'IMAP Protocol',
                    'value' => $server->imap_protocol,
                    'help_class' => 'sending_server',
                    'rules' => $server->getRules(),
                    'disabled' =>($server->id && $errors->isEmpty()),
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
                    'value' => $server->host,
                    'help_class' => 'sending_server',
                    'rules' => $server->getRules(),
                    'disabled' =>($server->id && $errors->isEmpty()),
                    ])

                    @include('helpers.form_control', [
                    'type' => 'text',
                    'class' => '',
                    'name' => 'smtp_username',
                    'value' => $server->smtp_username,
                    'help_class' => 'sending_server',
                    'rules' => $server->getRules(),
                    'disabled' =>($server->id && $errors->isEmpty()),
                    ])

                    @include('helpers.form_control', [
                    'type' => 'password',
                    'class' => '',
                    'name' => 'smtp_password',
                    'value' => $server->smtp_password,
                    'help_class' => 'sending_server',
                    'rules' => $server->getRules(),
                    'eye' => true,
                    'disabled' =>($server->id && $errors->isEmpty()),
                    ])

                    @include('helpers.form_control', [
                    'type' => 'text',
                    'class' => '',
                    'name' => 'smtp_port',
                    'value' => $server->smtp_port,
                    'help_class' => 'sending_server',
                    'rules' => $server->getRules(),
                    'disabled' =>($server->id && $errors->isEmpty()),
                    ])

                    @include('helpers.form_control', [
                    'type' => 'text',
                    'class' => '',
                    'name' => 'smtp_protocol',
                    'value' => $server->smtp_protocol,
                    'help_class' => 'sending_server',
                    'rules' => $server->getRules(),
                    'disabled' =>($server->id && $errors->isEmpty()),
                    ])

                    <div id="radioset">
                        <label>
                            Domain Type
                        </label><br><br>
                        <input value="gandi" type="radio" @if($server->domain_type == 'gandi') checked @endif id="Gandi" name="domain_type">
                        <label for="gandi">Gandi</label><br><br>
                        <input value="namecheap" type="radio" @if($server->domain_type == 'namecheap') checked @endif id="Namecheap" name="domain_type">
                        <label for="radio2">Namecheap</label><br><br>
                        <input value="godaddy" type="radio" @if($server->domain_type == 'godaddy') checked @endif id="godaddy" name="domain_type">
                        <label for="radio2">Godaddy</label><br><br>
                    </div>

                </div>
            </div>
            <div class="text-left">
                @if ($server->id && Auth::user()->customer->can('test', $server) && $errors->isEmpty())
                <span class="edit-group">
                    <a href="{{ action('SendingServerController@testConnection', $server->uid) }}" role="button" class="btn btn-secondary me-2 test-connection-button" mask-title="{{ trans('messages.sending_server.testing') }}">
                        {{ trans('messages.sending_server.test_connection') }}
                    </a>
                    <a id="SendTestEmailButton" href="{{ action('SendingServerController@test', $server->uid) }}" role="button" class="btn btn-secondary me-2 modal_link" data-in-form="true" link-method="GET">
                        {{ trans('messages.sending_server.send_a_test_email') }}
                    </a>
                    <a href="javascript:;" role="button" class="btn btn-link switch-form-toggle">
                        {{ trans('messages.edit') }}
                    </a>
                </span>
                <span class="cancel-group hide">
                    <button class="btn btn-secondary me-2">{{ trans('messages.save') }}</button>
                    <a href="javascript:;" role="button" class="btn btn-link switch-form-toggle">
                        {{ trans('messages.cancel') }}
                    </a>
                </span>
                @else
                <button class="btn btn-secondary me-2">{{ trans('messages.save') }}</button>
                <a href="{{ action('SendingServerController@index') }}" role="button" class="btn btn-link">
                    {{ trans('messages.cancel') }}
                </a>
                @endif


            </div>
        </div>
    </form>
    @if ($server->id)
    <form action="{{ action('SendingServerController@config', $server->uid) }}" method="POST" class="form-validate-jqueryz">
        {{ csrf_field() }}
        <div class="mc_section">
            <div class="row">
                <div class="col-md-6">
                    <h2 class=" mt-20">{{ trans('messages.sending_servers.configuration_settings') }}</h2>
                    <p>
                        {{ trans('messages.sending_servers.configuration_settings.sendgrid.intro') }}
                    </p>

                    <!-- @include('helpers.form_control', [
                    'type' => 'text',
                    'class' => '',
                    'name' => 'name',
                    'value' => $server->name,
                    'help_class' => 'sending_server',
                    'rules' => $server->getConfigRules(),
                ])

                @include('helpers.form_control', [
                    'type' => 'text',
                    'class' => '',
                    'name' => 'default_from_email',
                    'value' => $server->default_from_email,
                    'help_class' => 'sending_server',
                    'rules' => $server->getConfigRules(),
                ])
                
                @include('helpers.form_control', [
                    'type' => 'select',
                    'class' => '',
                    'name' => 'bounce_handler_id',
                    'label' => trans("messages.bounce_handler"),
                    'value' => $server->bounce_handler_id,
                    'help_class' => 'sending_server',
                    'include_blank' => trans('messages.choose'),
                    'options' => Acelle\Model\BounceHandler::getSelectOptions(),
                    'rules' => $server->getConfigRules(),
                ])
                
                @include('helpers.form_control', [
                    'type' => 'select',
                    'class' => '',
                    'name' => 'feedback_loop_handler_id',
                    'label' => trans("messages.feedback_loop_handler"),
                    'value' => $server->feedback_loop_handler_id,
                    'help_class' => 'sending_server',
                    'include_blank' => trans('messages.choose'),
                    'options' => Acelle\Model\FeedbackLoopHandler::getSelectOptions(),
                    'rules' => $server->getConfigRules(),
                ]) -->

                    <p>{!! trans('messages.sending_servers.sending_limit.mailgun.intro') !!}</p>

                    <div class="sendind-limit-select-custom" data-url="{{ action('SendingServerController@sendingLimit', ['uid' => ($server->uid ? $server->uid : 0)]) }}">
                        @include ('sending_servers.form._sending_limit', [
                        'quotaValue' => $server->quota_value,
                        'quotaBase' => $server->quota_base,
                        'quotaUnit' => $server->quota_unit,
                        ])
                    </div>

                </div>
            </div>
        </div>

        <div class="mc_section boxing">
            <div class="row">
                <div class="col-md-6">
                    <h3 class="mt-0">{{ trans('messages.sending_servers.sending_identity') }}</h3>
                    <p>
                        {!! trans('messages.sending_servers.local_identity.intro') !!}
                    </p>
                </div>

                <div class="col-md-9">
                    @if (is_null($identities))
                    @include('elements._notification', [
                    'level' => 'warning',
                    'title' => 'Error fetching identities list',
                    'message' => 'Please check your connection to AWS',
                    ])
                    @else
                    <table class="table table-box table-box-head field-list">
                        <thead>
                            <tr>
                                <td>{{ trans('messages.domain') }}</td>
                                <td>{{ trans('messages.status') }}</td>
                                <td align="center" class="xtooltip" title="Set whether or not this identity is available for all users">Available for All</td>
                                <td>Added By</td>
                                <td>DO Status</td>
                                <td>Action</td>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($allIdentities as $domain => $attributes)
                            <tr class="odd">
                                <td>
                                    {{ $domain }}
                                </td>
                                <td>
                                    @if ($attributes['VerificationStatus'] == 'Success')
                                    <span class="label label-flat bg-active">{{ trans('messages.sending_domain_status_active') }}</span>
                                    @else
                                    <span class="label label-flat bg-inactive">{{ trans('messages.sending_domain_status_inactive') }}</span>
                                    @endif

                                </td>

                                @if (!is_null($attributes['UserId']))
                                <td align="center"><span class="xtooltip" title="This domain is private and is available for the owner user only">Private</span></td>
                                @elseif ($attributes['VerificationStatus'] == 'Success')
                                <td align="center">
                                    <label>
                                        @if (checkEmail($domain))
                                        <input type="checkbox" name="options[emails][]" value="{{ $domain }}" class="switchery" {{ $attributes['Selected'] ? " checked" : "" }} />
                                        @else
                                        <input type="checkbox" name="options[domains][]" value="{{ $domain }}" class="switchery" {{ $attributes['Selected'] ? " checked" : "" }} />
                                        @endif
                                    </label>
                                </td>
                                @else
                                <td align="center"></td>
                                @endif
                                <td>
                                    <a href="#" target="_blank">{{ $attributes['UserName'] }}</a>
                                </td>
                                <td>
                                    <span class="label label-flat {{ (!$server->domain_created_attached) ? 'bg-danger' : 'bg-active' }}">{{ (!$server->domain_created_attached) ? "In Process" : "Ready" }}</span>
                                </td>
                                <td>
                                    <a href="{{ action('SendingServerController@removeDomain', [$server->uid, base64_encode($domain)]) }}" link-confirm="{{ trans('messages.sending_server.domain.remove_domain.confirm') }}" class="text-warning" link-method="POST">
                                        {{ trans('messages.sending_serbers.remove_email_domain') }}
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
                <div class="col-md-6">
                    <!-- <br> -->
                    <!-- <a href="{{ action('SendingServerController@addDomain', $server->uid) }}"
                  class="btn btn-secondary me-2 add-domain-button" data-size="md">
                    {{ trans('messages.sending_serbers.add_email_domain') }}
                </a> -->

                    <!-- <p class="mt-5">
                        {{ trans('messages.sending_serbers.php-mail.allow_verify.intro') }}
                    </p> -->

                    <!-- @include('helpers.form_control', [
                    'type' => 'checkbox2',
                    'label' => trans('messages.allow_unverified_from_email'),
                    'name' => 'options[allow_unverified_from_email]',
                    'value' => $server->getOption('allow_unverified_from_email'),
                    'help_class' => 'sending_server',
                    'options' => ['no', 'yes'],
                    ])

                    @include('helpers.form_control', [
                    'type' => 'checkbox2',
                    'label' => trans('messages.allow_verify_domain_against_system'),
                    'name' => 'options[allow_verify_domain_against_acelle]',
                    'value' => $server->getOption('allow_verify_domain_against_acelle'),
                    'help_class' => 'sending_server',
                    'options' => ['no', 'yes'],
                    ])

                    @include('helpers.form_control', [
                    'type' => 'checkbox2',
                    'label' => trans('messages.allow_verify_email_against_system'),
                    'name' => 'options[allow_verify_email_against_acelle]',
                    'value' => $server->getOption('allow_verify_email_against_acelle'),
                    'help_class' => 'sending_server',
                    'options' => ['no', 'yes'],
                    ]) -->
                    <!-- @include('helpers.form_control', [
                    'type' => 'checkbox2',
                    'label' => 'Use Sending Identity as Email Engine Proxy',
                    'name' => 'options[email_engine_proxy]',
                    'value' => $server->getOption('email_engine_proxy'),
                    'help_class' => 'sending_server',
                    'options' => ['no', 'yes'],
                    ]) -->
                    <div class="mt-20">
                        <button class="btn btn-secondary me-2">{{ trans('messages.save') }}</button>
                        <a href="{{ action('SendingServerController@index') }}" role="button" class="btn btn-link">
                            {{ trans('messages.cancel') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </form>

    <script>
        var addDomain;

        $(function() {
            $('[name="options[allow_verify_email_against_acelle]"]').change(function() {
                var value = $(this).is(':checked');
                if (value) {
                    $('.use_custom_verification_email').show();
                } else {
                    $('.use_custom_verification_email').hide();
                }
            });
            $('[name="options[allow_verify_email_against_acelle]"]').change();

            // add domain
            $('.add-domain-button').on('click', function(e) {
                e.preventDefault();

                var url = $(this).attr('href');
                addDomain = new Popup({
                    url: url
                });

                addDomain.load();
            });
        });
    </script>

    @endif
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