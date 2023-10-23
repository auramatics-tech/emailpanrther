@include('helpers.form_control', [
    'type' => 'select',
    'class' => '',
    'name' => 'options[sending_limit]',
    'label' => isset($no_label) ? '' : trans('messages.plan.set_a_limit'),
    'value' => $server->getOption('sending_limit'),
    'help_class' => 'sending_server',
    'rules' => [],
    'options' => $server->getSendingLimitSelectOptions(),
])

<input type="hidden" id="quota_value_{{$server->uid}}" name="quota_value" value="{{ $quotaValue }}" />
<input type="hidden" id="quota_base_{{$server->uid}}" name="quota_base" value="{{ $quotaBase }}" />
<input type="hidden" id="quota_unit_{{$server->uid}}" name="quota_unit" value="{{ $quotaUnit }}" />

<script>
    var SendingLimit = {
        sendingLimitPopup: null,

        getBox: function() {
            return $('.sending-limit-box');
        },

        getSendingLimitPopup: function() {
            if (this.sendingLimitPopup == null) {
                this.sendingLimitPopup = new Popup({
                    url: '{{ action('SendingServerController@sendingLimit', ['uid' => ($server->uid ? $server->uid : 0)]) }}'
                });
            }

            return this.sendingLimitPopup;
        },

        updateSendingLimit:function(val){
            $.ajax({
                url: "{{ action('SendingServerController@updatesendingLimit', ['uid' => ($server->uid ? $server->uid : 0)]) }}",
                method:"post",
                data:{
                    '_token':"{{ csrf_token() }}",
                    'limit':val,
                    'quota_value': $('#quota_value_'+val).val(),
                    'quota_base': $('#quota_base_'+val).val(),
                    'quota_unit': $('#quota_unit_'+val).val(),
                },
                success:function(){

                }
            })
        }
    }

    $(function() {
    });

    $(function() {
        $('[name="options[sending_limit]"]').on('change', function() {
            var val = $(this).val();

            if (val == 'custom') {
                SendingLimit.getSendingLimitPopup().load();
            }

            @if(isset($no_label))
                if (val != 'custom') {
                    SendingLimit.updateSendingLimit(val)
                }
            @endif
        });
    });
</script>