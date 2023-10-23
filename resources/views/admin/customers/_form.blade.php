<div class="row">
    <div class="col-md-3">
        <div class="sub_section">
            <h2 class="text-semibold text-primary">{{ trans('messages.profile_photo') }}</h2>
            <div class="media profile-image">
                <div class="media-left">
                    <a href="#" class="upload-media-container">
                        <img preview-for="image" empty-src="{{ URL::asset('images/placeholder.jpg') }}" src="{{ $customer->user->getProfileImageUrl() }}" class="rounded-circle" alt="">
                    </a>
                    <input type="file" name="image" class="file-styled previewable hide">
                    <input type="hidden" name="_remove_image" value='' />
                </div>
                <div class="media-body text-center">
                    <h5 class="media-heading text-semibold">{{ trans('messages.upload_photo') }}</h5>
                    {{ trans('messages.photo_at_least', ["size" => "300px x 300px"]) }}
                    <br /><br />
                    <a href="#upload" onclick="$('input[name=image]').trigger('click')" class="btn btn-primary me-1"><span class="material-symbols-rounded">file_download</span> {{ trans('messages.upload') }}</a>
                    <a href="#remove" class="btn btn-secondary remove-profile-image"><span class="material-symbols-rounded">delete_outline</span> {{ trans('messages.remove') }}</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="sub_section" id="section_left">
            <h2 class="text-semibold text-primary">{{ trans('messages.account') }}</h2>

            @include('helpers.form_control', ['type' => 'text', 'name' => 'email', 'value' => $customer->user->email, 'help_class' => 'profile', 'rules' => $customer->user->rules()])

            @include('helpers.form_control', ['type' => 'password', 'label'=> trans('messages.new_password'), 'name' => 'password', 'rules' => $customer->user->rules()])

            @include('helpers.form_control', ['type' => 'password', 'name' => 'password_confirmation', 'rules' => $customer->user->rules()])

            @if(isset($TrackingDomain) && count($TrackingDomain))
            @foreach($TrackingDomain as $key => $domain)
            <div class="form-group control-text">
                <label>Customer Domain</label>
                <input placeholder="" value="{{ $domain->name }}" type="text" name="customer_domain[]" class="customer_domain_{{ $key }} form-control customer_domain_inputs" data-customer="{{ $customer->id }}">
                @if($key >= 1)
                <button type="button" class="btn btn-secondary remove_tracking_domain" data-key="{{ $key }}" data-customer="{{ $customer->id }}" data-id="{{ $domain->id }}">Remove</button>
                @endif
            </div>
            @endforeach
            @else
            <div class="form-group control-text">
                <label>Customer Domain</label>
                <input placeholder="" value="" type="text" name="customer_domain[]" class="customer_domain_0 form-control customer_domain_inputs" data-customer="{{ $customer->id }}">
            </div>
            @endif
        </div>
        <a href="javascript:void(0);" class="btn btn-primary me-1 another_domain">Add another domain</a>
    </div>
    <div class="col-md-5">
        <div class="sub_section">
            <h2 class="text-semibold text-primary">{{ trans('messages.basic_information') }}</h2>

            @if (get_localization_config('show_last_name_first', Auth::user()->admin->getLanguageCode()))
            <div class="row">
                <div class="col-md-6">
                    @include('helpers.form_control', ['type' => 'text', 'name' => 'last_name', 'value' => $customer->user->last_name, 'rules' => $customer->user->rules()])
                </div>
                <div class="col-md-6">
                    @include('helpers.form_control', ['type' => 'text', 'name' => 'first_name', 'value' => $customer->user->first_name, 'rules' => $customer->user->rules()])
                </div>
            </div>
            @else
            <div class="row">
                <div class="col-md-6">
                    @include('helpers.form_control', ['type' => 'text', 'name' => 'first_name', 'value' => $customer->user->first_name, 'rules' => $customer->user->rules()])
                </div>
                <div class="col-md-6">
                    @include('helpers.form_control', ['type' => 'text', 'name' => 'last_name', 'value' => $customer->user->last_name, 'rules' => $customer->user->rules()])
                </div>
            </div>
            @endif

            @if (config('custom.japan'))
            <input type="hidden" name="timezone" value="Asia/Tokyo" />
            @else
            @include('helpers.form_control', [
            'type' => 'select',
            'name' => 'timezone',
            'value' => $customer->timezone ?? config('app.timezone'),
            'options' => Tool::getTimezoneSelectOptions(),
            'include_blank' => trans('messages.choose'),
            'rules' => $customer->user->rules()
            ])
            @endif


            @if (config('custom.japan'))
            <input type="hidden" name="language_id" value="{{ Acelle\Model\Language::getJapan()->id }}" />
            @else
            @include('helpers.form_control', [
            'type' => 'select',
            'name' => 'language_id',
            'label' => trans('messages.language'),
            'value' => $customer->language_id ?? \Acelle\Model\Language::getDefaultLanguage()->id,
            'options' => Acelle\Model\Language::getSelectOptions(),
            'include_blank' => trans('messages.choose'),
            'rules' => $customer->user->rules()
            ])
            @endif

            <div class="form-group control-text">
                <label>Existing domains?</label>
                <input type="checkbox" name="existing_domains" id="existing_domains">
            </div>
            @include('helpers.form_control', ['type' => 'select', 'name' => 'user_type', 'label' => 'User Type', 'value' => $customer->user->user_type, 'options' => Acelle\Model\User::getSelectOptions(), 'rules' => $customer->user->rules()])


        </div>
    </div>

</div>
<hr>
<div class="text-left">
    <button class="btn btn-secondary"><i class="icon-check"></i> {{ trans('messages.save') }}</button>
</div>

<script>
    $(function() {
        // Preview upload image
        $("input.previewable").on('change', function() {
            var img = $("img[preview-for='" + $(this).attr("name") + "']");
            previewImageBrowse(this, img);
        });
        $(".remove-profile-image").click(function() {
            var img = $(this).parents(".profile-image").find("img");
            var imput = $(this).parents(".profile-image").find("input[name='_remove_image']");
            img.attr("src", img.attr("empty-src"));
            imput.val("true");
        });


        $(document).on("click", ".another_domain", function() {
            var input = '<div class="form-group control-text"><input placeholder="" value="" type="text" name="customer_domain[]" class="form-control customer_domain_inputs"><button type="button" class="btn btn-secondary remove_domain">Remove</button></div>';
            $("#section_left").append(input)
        })

        $(document).on("click", ".remove_domain", function() {
            $(this).parent().remove();
        })

        $(document).on("click", ".remove_tracking_domain", function() {
            var key = $(this).attr("data-key");
            var customer = $(this).attr("data-customer");
            var tracking_id = $(this).attr("data-id");
            $.ajax({
                url: "/admin/remove-domain",
                data: {
                    customer: customer,
                    tracking_id: tracking_id
                },
                success: function() {
                    $('.customer_domain_' + key).parent().remove();
                    $(this).remove();
                }
            })
        })
    });
</script>