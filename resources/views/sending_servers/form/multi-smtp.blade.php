@if (!$server->id)
<form id="editServerForm" action="{{ action('SendingServerController@storenew', ["type" => request()->type]) }}" method="POST" class="form-validate-jqueryz">
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
                    'name' => 'domain',
                    'value' => $server->domain,
                    'help_class' => 'sending_server',
                    'rules' => $server->getConfigRules(),
                    'disabled' =>($server->id && $errors->isEmpty()),
                    'label' => 'Domain Name'
                    ])

                </div>

                <div id="radioset">
                    <label>
                        Domain Type
                    </label><br><br>
                    @if (!$server->id)
                    <input value="gandi" type="radio" @if($server->domain_type == 'gandi') checked @endif id="Gandi" name="domain_type">
                    <label for="gandi">Gandi</label><br><br>
                    <input value="namecheap" type="radio" @if($server->domain_type == 'namecheap') checked @endif id="Namecheap" name="domain_type">
                    <label for="radio2">Namecheap</label><br><br>
                    <input value="godaddy" type="radio" @if($server->domain_type == 'godaddy') checked @endif id="godaddy" name="domain_type">
                    <label for="radio2">Godaddy</label><br><br>
                    @else
                    <label for="gandi">{{ ucfirst($server->domain_type) }}</label>
                    @endif
                </div>

            </div>
            @if (!$server->id)
            <div class="text-left">
                <button class="btn btn-secondary me-2">{{ trans('messages.save') }}</button>
                <a href="{{ action('SendingServerController@index') }}" role="button" class="btn btn-link">
                    {{ trans('messages.cancel') }}
                </a>
            </div>
            @endif
        </div>
    </form>
    @if ($server->id)
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
        </div>
    </div>
    @if($server->domain_created_attached)
    <div class="mc_section boxing">
        <div class="row">
            <div class="col-md-6">
                <h3 class="mt-0">IMAP/SMTP</h3>
            </div>

            <div class="col-md-9">
                <table class="table table-box table-box-head field-list">
                    <thead>
                        <tr>
                            <td>From Email</td>
                            <td>IMAP Hostname</td>
                            <td>IMAP Username</td>
                            <td>IMAP Port</td>
                            <!-- <td>IMAP Portal</td> -->
                            <td>SMTP Hostname</td>
                            <td>SMTP Username</td>
                            <td>SMTP Port</td>
                            <td>Sending Credits</td>
                            <td>Speed limit</td>
                            <td>Action</td>
                        </tr>
                    </thead>
                    <tbody>
                        @if(count($server->multi_servers()))
                        @foreach($server->multi_servers() as $server)
                        @if($server->default_from_email != 'noreply@lav1.online')
                        <tr class="odd">
                            <td>{{ $server->default_from_email }}</td>
                            <td>{{ $server->imap_host }}</td>
                            <td>{{ $server->imap_username }}</td>
                            <td>{{ $server->imap_port }}</td>
                            <!-- <td>{{ $server->imap_protocol }}</td> -->
                            <td>{{ $server->host }}</td>
                            <td>{{ $server->smtp_username }}</td>
                            <td>{{ $server->smtp_port }}</td>
                            <!-- <td>{{ $server->smtp_protocol }}</td> -->
                            <td>{{ number_with_delimiter($server->getCreditsUsed('send')) }}</td>
                            <td>
                                <div class="sendind-limit-select-custom" data-url="{{ action('SendingServerController@sendingLimit', ['uid' => ($server->uid ? $server->uid : 0)]) }}">
                                    @include ('sending_servers.form._sending_limit', [
                                    'quotaValue' => $server->quota_value,
                                    'quotaBase' => $server->quota_base,
                                    'quotaUnit' => $server->quota_unit,
                                    'no_label' => 1
                                    ])
                                </div>
                            </td>
                            <td>
                                <a link-method="get" class="dropdown-item list-action-single text-danger" link-confirm="You're about to delete the sending server." link-confirm-url="{{ action('SendingServerController@delete') }}?uids={{ $server->uid }}" href="{{ action('SendingServerController@delete') }}?uids={{ $server->uid }}">Delete</a>
                                <a href="{{ action('SendingServerController@testConnection', $server->uid) }}" role="button" class="btn btn-secondary me-2 test-connection-button" mask-title="{{ trans('messages.sending_server.testing') }}">
                                    {{ trans('messages.sending_server.test_connection') }}
                                </a>
                            </td>
                        </tr>
                        @endif
                        @endforeach
                        @endif
                    </tbody>
                </table>
                <a id="AddNewServer" href="{{ action('SendingServerController@new_multi_server', $server->uid) }}" role="button" class="btn btn-secondary me-2 modal_link" data-in-form="true" link-method="GET">
                    Add New
                </a>
            </div>
        </div>
    </div>
    @endif
    @endif