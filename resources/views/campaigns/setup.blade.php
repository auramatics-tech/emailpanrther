@extends('layouts.core.frontend', [
    'menu' => 'campaign',
])

@section('title', trans('messages.campaigns') . " - " . trans('messages.setup'))

@section('head')
	<script type="text/javascript" src="{{ URL::asset('core/js/group-manager.js') }}"></script>

    <link href="{{ URL::asset('core/emojionearea/emojionearea.min.css') }}" rel="stylesheet">
    <script type="text/javascript" src="{{ URL::asset('core/emojionearea/emojionearea.min.js') }}"></script>
@endsection
	
@section('page_header')
	
	<div class="page-title">
		<ul class="breadcrumb breadcrumb-caret position-right">
			<li class="breadcrumb-item"><a href="{{ action("HomeController@index") }}">{{ trans('messages.home') }}</a></li>
			<li class="breadcrumb-item"><a href="{{ action("CampaignController@index") }}">{{ trans('messages.campaigns') }}</a></li>
		</ul>
		<h1>
			<span class="text-semibold"><span class="material-symbols-rounded me-2 me-2">forward_to_inbox</span> {{ $campaign->name }}</span>
		</h1>

		@include('campaigns._steps', ['current' => 2])
	</div>

@endsection

@section('content')
	<form action="{{ action('CampaignController@setup', $campaign->uid) }}" method="POST" class="form-validate-jqueryz">
		{{ csrf_field() }}
		
		<div class="row">
			<div class="col-md-6 list_select_box" target-box="segments-select-box" segments-url="{{ action('SegmentController@selectBox') }}">
				@include('helpers.form_control', ['type' => 'text',
					'name' => 'name',
					'label' => trans('messages.name_your_campaign'),
					'value' => $campaign->name,
					'rules' => $rules,
					'help_class' => 'campaign'
				])
				
				<!-- <div class="has-emoji">
					@include('helpers.form_control', ['type' => 'text',
						'name' => 'subject',
						'label' => trans('messages.email_subject'),
						'value' => $campaign->subject,
						'rules' => $rules,
						'help_class' => 'campaign',
						'attributes' => [
							'data-emojiable' => 'true',
						]
					])
				</div> -->
												
				@include('helpers.form_control', ['type' => 'text',
					'name' => 'from_name',
					'label' => trans('messages.from_name'),
					'value' => $campaign->from_name,
					'rules' => $rules,
					'help_class' => 'campaign'
				])
				
				<div class="hiddable-box" data-control="[name=use_default_sending_server_from_email]" data-hide-value="1">
				@if($campaign->server_type == 'smtp')
					<label>From email <span class="text-danger">*</span></label>
					<select class="select select2 top-menu-select form-control" name="from_email[]" multiple="multiple">
						@foreach(\Acelle\Model\SendingServer::where(['type'=>'smtp','status'=>'active'])->get() as $sending_servers)
							<option @if(in_array($sending_servers->name,$linked_sending_servers)) selected @endif value="{{ $sending_servers->id }}">{{ $sending_servers->name }}</option>
						@endforeach
					</select>
				@elseif($campaign->server_type == 'multi-smtp')
					<label>From Domain <span class="text-danger">*</span></label>
					<select class="select select2 top-menu-select form-control" name="from_email[]" multiple="multiple">
						@foreach(\Acelle\Model\SendingServer::where(['type'=>'multi-smtp','status'=>'active','multi_server_linked_with'=>0])->get() as $sending_servers)
							<option @if(in_array($sending_servers->name,$linked_sending_servers)) selected @endif value="{{ $sending_servers->id }}">{{ $sending_servers->name }}</option>
						@endforeach
					</select>
				@endif
				</div>

				@include('helpers.form_control', ['type' => 'checkbox2',
					'name' => 'use_default_sending_server_from_email',
					'label' => trans('messages.use_sending_server_default_value'),
					'value' => $campaign->use_default_sending_server_from_email,
					'rules' => $rules,
					'class' => 'd-none',
					'help_class' => 'campaign',
					'options' => ['0','1'],
				])
												
				@include('helpers.form_control', [
                    'type' => 'autofill',
                    'id' => 'sender_reply_to_input',
                    'name' => 'reply_to',
                    'label' => '',
					'class' => 'd-none',
                    'value' => $campaign->reply_to,
                    'url' => action('SenderController@dropbox'),
                    'rules' => $campaign->rules(),
                    'help_class' => 'campaign',
                    'empty' => trans('messages.sender.dropbox.empty'),
                    'error' => trans('messages.sender.dropbox.reply.error.' . Auth::user()->customer->allowUnverifiedFromEmailAddress(), [
                        'sender_link' => action('SenderController@index'),
                    ]),
                    'header' => trans('messages.verified_senders'),
                ])

				@include('helpers.form_control', [
                    'type' => 'text',
                    'id' => 'campaign_group_id',
                    'name' => 'group_id',
                    'label' => 'Group Id',
                    'value' => $campaign->group_id,
                ])
				
			</div>
			<div class="col-md-6 segments-select-box" style="display: none;">
				<div class="form-group checkbox-right-switch">
					@if ($campaign->type != 'plain-text')
						@include('helpers.form_control', ['type' => 'checkbox',
													'name' => 'track_open',
													'label' => trans('messages.track_opens'),
													'value' => $campaign->track_open,
													'options' => [false,true],
													'help_class' => 'campaign',
													'rules' => $rules
												])
					
						@include('helpers.form_control', ['type' => 'checkbox',
													'name' => 'track_click',
													'label' => trans('messages.track_clicks'),
													'value' => $campaign->track_click,
													'options' => [false,true],
													'help_class' => 'campaign',
													'rules' => $rules
												])
					@endif
					
					@include('helpers.form_control', ['type' => 'checkbox',
													'name' => 'sign_dkim',
													'label' => trans('messages.sign_dkim'),
													'value' => $campaign->sign_dkim,
													'options' => [false,true],
													'help_class' => 'campaign',
													'rules' => $rules
												])
					@include('helpers.form_control', [
						'type' => 'checkbox',
						'name' => 'custom_tracking_domain',
						'label' => trans('messages.custom_tracking_domain'),
						'value' => Auth::user()->customer->isCustomTrackingDomainRequired() ? true : $campaign->tracking_domain_id,
						'options' => [false,true],
						'help_class' => 'campaign',
						'rules' => $rules
					])
					
					<div class="select-tracking-domain" style="display: none;">
						@include('helpers.form_control', [
							'type' => 'select',
							'name' => 'tracking_domain_uid',
							'label' => '',
							'value' => $campaign->trackingDomain? $campaign->trackingDomain->uid : null,
							'options' => Auth::user()->customer->getVerifiedTrackingDomainOptions(),
							'include_blank' => trans('messages.campaign.select_tracking_domain'),
							'help_class' => 'campaign',
							'rules' => $rules
						])
					</div>
												
					@if ($campaign->type == 'plain-text')
						<div class="alert alert-warning">
							{!! trans('messages.campaign.plain_text.open_click_tracking_wanring') !!}
						</div>
					@endif
					
					@if ($campaign->template)
						<div class="webhooks-management" style="display: none;">
							<div class="d-flex align-items-center mb-2">
                                <h3 class="mb-0 me-2"> {{ trans('messages.webhooks') }}</h3>
                                <span class="badge badge-info">{{ number_with_delimiter($campaign->campaignWebhooks()->count()) }}</span>
                            </div>
							<div class="d-flex">
								<p>{{ trans('messages.webhooks.wording') }}</p>
								<div class="ms-4">
									<a href="javascript:;" class="btn btn-secondary manage_webhooks_but">
										{{ trans('messages.webhooks.manage') }}
									</a>
								</div>
							</div>
						</div>
					@endif
				</div>
			</div>
			@if (Auth::user()->customer->useOwnSendingServer())
				<div class="sub_section" style="display: none;">
					
					@if(!\Auth::user()->customer->activeSendingServers()->count())
						<div class="alert alert-danger mt-3">
							{!! trans('messages.list.there_no_subaccount_sending_server') !!}
						</div>
					@else
						<div class="sending-servers">
							<hr>
							<div class="row text-muted text-semibold">
								<div class="col-md-3">
									<label>{{ trans('messages.select_sending_servers') }}</label>
								</div>
								<div class="col-md-3">
									<label>{{ trans('messages.fitness') }}</label>
								</div>
							</div>
							@foreach (\Auth::user()->customer->activeSendingServers()->orderBy("name")->get() as $server)
								<div class="row mb-5 form-groups-bottom-0">
									<div class="col-md-3">
										@include('helpers.form_control', [
											'type' => 'checkbox2',
											'name' => 'sending_servers[' . $server->uid . '][check]',
											'value' => $campaign->campaignSendingServers->contains('sending_server_id', $server->id),
											'label' => $server->name,
											'options' => [false, true],
											'help_class' => 'list',
											'rules' => Acelle\Model\MailList::$rules
										])
									</div>
									<div class="col-md-3" show-with-control="input[name='{{ 'sending_servers[' . $server->uid . '][check]' }}']">
										@include('helpers.form_control', [
											'type' => 'text',
											'class' => 'numeric',
											'name' => 'sending_servers[' . $server->uid . '][fitness]',
											'label' => '',
											'value' => ($campaign->campaignSendingServers()->where('sending_server_id', $server->id)->first() ? $campaign->campaignSendingServers()->where('sending_server_id', $server->id)->first()->fitness : "100"),
											'help_class' => 'list',
											'rules' => Acelle\Model\MailList::$rules
										])
									</div>
								</div>
							@endforeach
						</div>
					@endif
				</div>
				<script>
					$(document).ready(function() {
						// all sending servers checking
						$(document).on("change", "input[name='all_sending_servers']", function(e) {
							if($("input[name='all_sending_servers']:checked").length) {
								$(".sending-servers").find("input[type=checkbox]").each(function() {
									if($(this).is(":checked")) {
										$(this).parents(".form-group").find(".switchery").eq(1).click();
									}
								});
								$(".sending-servers").hide();
							} else {
								$(".sending-servers").show();
							}
						});
						$("input[name='all_sending_servers']").trigger("change");
					});
				</script>
			@endif
		</div>
		<hr>
		<div class="text-end {{ Auth::user()->customer->allowUnverifiedFromEmailAddress() ? '' : 'unverified_next_but' }}">
			<button class="btn btn-secondary">{{ trans('messages.save_and_next') }} <span class="material-symbols-rounded">arrow_forward</span> </button>
		</div>
		
	<form>
	
	<script>
		var CampaignsSetup = {
			webhooksPopup: null,
			getWebhooksPopup: function() {
				if (this.webhooksPopup == null) {
					this.webhooksPopup = new Popup({
						url: '{{ action('CampaignController@webhooks', [
							'uid' => $campaign->uid,
						]) }}',
						onclose: function() {
							CampaignsSetup.refresh();
						}
					});
				}

				return this.webhooksPopup;
			},

			refresh: function() {
                $.ajax({
                    url: "",
                    method: 'GET',
                    data: {
                        _token: CSRF_TOKEN
                    },
                    success: function (response) {
                        var html = $('<div>').html(response).find('.webhooks-management').html();

                        $('.webhooks-management').html(html);
                    }
                });
            }
		}

		var CampaignsSetupNextButton = {
			manager: null,

			getManager: function() {
				if (this.manager == null) {
					this.manager = new GroupManager();
					this.manager.add({
						isError: function() {
							// return $('.autofill-error:visible').length;
						},
						nextButton: $('.unverified_next_but'),
						inputs: $('[name=reply_to], [name=from_email]')
					});

					this.manager.bind(function(group) {
						group.check = function() {
							if (!group.isError()) {
								group.nextButton.removeClass('pointer-events-none');
								group.nextButton.removeClass('disabled');
							} else {
								group.nextButton.addClass('pointer-events-none');
								group.nextButton.addClass('disabled');
							}
						}

						group.check();

						group.inputs.on('change keyup', function() {
							group.check();
						});
					});
				}

				return this.manager;
			},

			check: function() {
				this.getManager().groups.forEach(function(group) {
					group.check();
				});
			}
		}

		$(function() {
			// check next button
			CampaignsSetupNextButton.check();

			// manage webhooks button click
			$('.manage_webhooks_but').on('click', function(e) {
				e.preventDefault();

				CampaignsSetup.getWebhooksPopup().load();
			});

			// @Legacy
			// auto fill
			var box = $('#sender_from_input').autofill({
				messages: {
					header_found: '{{ trans('messages.sending_identity') }}',
					header_not_found: '{{ trans('messages.sending_identity.not_found.header') }}'
				},
				callback: function() {
					CampaignsSetupNextButton.check();
				}
			});
			box.loadDropbox(function() {
				$('#sender_from_input').focusout();
				box.updateErrorMessage();
			})

			// auto fill 2
			var box2 = $('#sender_reply_to_input').autofill({
				messages: {
					header_found: '{{ trans('messages.sending_identity') }}',
					header_not_found: '{{ trans('messages.sending_identity.reply.not_found.header') }}'
				},
				callback: function() {
					CampaignsSetupNextButton.check();
				}
			});
			box2.loadDropbox(function() {
				$('#sender_reply_to_input').focusout();
				box2.updateErrorMessage();
			})

			$('[name="from_email"]').blur(function() {
				$('[name="reply_to"]').val($(this).val()).change();
			});
			$('[name="from_email"]').change(function() {
				$('[name="reply_to"]').val($(this).val()).change();
			});

			// select custom tracking domain
			$('[name=custom_tracking_domain]').change(function() {
				var value = $('[name=custom_tracking_domain]:checked').val();

				if (value) {
					$('.select-tracking-domain').show();
				} else {
					$('.select-tracking-domain').hide();
				}
			});
			$('[name=custom_tracking_domain]').change();

			// legacy
			$('.hiddable-box').each(function() {
				var box = $(this);
				var control = $(box.attr('data-control'));
				var hide_value = box.attr('data-hide-value');
				
				control.change(function() {            
					var val;
					
					control.each(function() {
						if ($(this).is(':checked')) {
							val = $(this).val();
						}
					});
					
					if(hide_value == val) {
						box.addClass('hide');
					} else {
						box.removeClass('hide');
					}
				});
				
				control.change();
			});

			$(function() {
				$('.has-emoji input[type=text]').emojioneArea();
			});
		})
	</script>
				
@endsection
