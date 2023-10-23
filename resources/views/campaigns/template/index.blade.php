@extends('layouts.core.frontend', [
'menu' => 'campaign',
])

@section('title', trans('messages.campaigns') . ' - ' . trans('messages.template'))

@section('head')
<script type="text/javascript" src="{{ URL::asset('core/tinymce/tinymce.min.js') }}"></script>
<script type="text/javascript" src="{{ URL::asset('core/js/editor.js') }}"></script>

<!-- Dropzone -->
<script type="text/javascript" src="{{ URL::asset('core/dropzone/dropzone.js') }}"></script>
@include('helpers._dropzone_lang')
<link href="{{ URL::asset('core/dropzone/dropzone.css') }}" rel="stylesheet" type="text/css">
<link href="{{ URL::asset('core/css/custom_template.css') }}" rel="stylesheet" type="text/css">
<link href="https://gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css" rel="stylesheet">
<script src="https://gitcdn.github.io/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js"></script>
<script src="https://cdn.tiny.cloud/1/dk78ysfqverbufn0z00d1195sqyep60xygf4zefi08wkgtne/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
@endsection

@section('page_header')

<div class="page-title">
    <ul class="breadcrumb breadcrumb-caret position-right">
        <li class="breadcrumb-item"><a href="{{ action('HomeController@index') }}">{{ trans('messages.home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ action('CampaignController@index') }}">{{ trans('messages.campaigns') }}</a></li>
    </ul>
    <h1>
        <span class="text-semibold"><span class="material-symbols-rounded me-2">forward_to_inbox</span>
            {{ $campaign->name }}</span>
    </h1>

    @include('campaigns._steps', ['current' => 3])
</div>

@endsection

@section('content')
<!--  latest html start -->
<div class="row main_fix_container mb-4">
    <div class="col-md-auto width-left h-100 pr-md-0">
        <div class="all_steps">
            <input type="hidden" id="steps_count" value="{{ count($campaign->steps) }}">
            @if (count($campaign->steps))
            @foreach ($campaign->steps as $key => $step)
            @include('campaigns.template.steps')
            @endforeach
            @endif
            <div class="mb-0">
                <button class="btn btn-primary w-100" id="add_step">Add step</button>
            </div>
        </div>
    </div>
    @include('campaigns.template.step_template')

</div>
<!--  latest html end -->

<!-- modal start-->
<div class="modal fade" id="stepDeleteModal" tabindex="-1" aria-labelledby="stepDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content py-4">
            <div class="modal-header border-0 justify-content-center">
                <h5 class="modal-title" id="stepDeleteModalLabel">Are you sure?</h5>
            </div>
            <div class="modal-foote border-0 d-flex justify-content-center align-items-center p-3">
                <button data-step="" type="button" class="btn btn-danger me-3" id="delete_steps">Delete step</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

@include('campaigns.template.step_settings')

<!-- modal end -->
<hr>
<a href="{{ action('CampaignController@confirm', ['uid' => $campaign->uid]) }}" class="btn btn-secondary">
    {{ trans('messages.next') }} <span class="material-symbols-rounded">arrow_forward</span>
</a>

<script>
    var templatePopup = new Popup();

    $(document).ready(function() {
        $('.template-start').click(function() {
            var url = $(this).attr('data-url');

            templatePopup.load(url);
        });

        $('.template-compose').click(function(e) {
            e.preventDefault();

            var url = $(this).attr('href');

            openBuilder(url);
        });

        $('.template-compose-classic').click(function(e) {
            e.preventDefault();

            var url = $(this).attr('href');

            openBuilderClassic(url);
        });
    });

    $('#calculate-score').click(function() {
        spamPopup = new Popup("{{ action('CampaignController@spamScore', ['uid' => $campaign->uid]) }}");
        spamPopup.load();
        return false;
    });
</script>
<script>
    // Prevent Bootstrap dialog from blocking focusin
    document.addEventListener('focusin', (e) => {
        if (e.target.closest(".tox-tinymce, .tox-tinymce-aux, .moxman-window, .tam-assetmanager-root") !==
            null) {
            e.stopImmediatePropagation();
        }
    });
    tinymce.init({
        selector: 'textarea#tiny',
        skin: 'naked',
        icons: 'small',
        toolbar_location: 'bottom',
        plugins: 'lists code table codesample link template preview',
        toolbar: 'undo redo | preview link code template | bold italic | blocks fontfamily fontsize | underline strikethrough | image media table mergetags | addcomment showcomments | spellcheckdialog a11ycheck typography | align lineheight | checklist numlist bullist indent outdent | emoticons charmap | removeformat ',
        menubar: false,
        statusbar: false,
        tinycomments_mode: 'embedded',
        mergetags_list: [{
            value: 'First.Name',
            title: 'First Name'
        }],
        //   insert template
        template_mdate_format: '%m/%d/%Y : %H:%M',
        template_replace_values: {
            username: 'Jack Black',
            staffid: '991234',
            inboth_username: 'Famous Person',
            inboth_staffid: '2213',
        },
        template_preview_replace_values: {
            preview_username: 'Jack Black',
            preview_staffid: '991234',
            inboth_username: 'Famous Person',
            inboth_staffid: '2213',
        },
        templates: [{
                title: 'Date modified example',
                description: 'Adds a timestamp indicating the last time the document modified.',
                content: '<p>Last Modified: <time class="mdate">This will be replaced with the date modified.</time></p>'
            },
            {
                title: 'Replace values example',
                description: 'These values will be replaced when the template is inserted into the editor content.',
                content: '<p>Name: {$username}, StaffID: {$staffid}</p>'
            },
            {
                title: 'Replace values preview example',
                description: 'These values are replaced in the preview, but not when inserted into the editor content.',
                content: '<p>Name: {$preview_username}, StaffID: {$preview_staffid}</p>'
            },
            {
                title: 'Replace values preview and content example',
                description: 'These values are replaced in the preview, and in the content.',
                content: '<p>Name: {$inboth_username}, StaffID: {$inboth_staffid}</p>'
            }
        ],
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:16px }',
        setup: function(ed) {
            ed.on('keyup', function(e) {
                update_content(ed.getContent());
                console.log('the content ', ed.getContent());
            });
            ed.on('change', function(e) {
                update_content(ed.getContent());
                console.log('the content ', ed.getContent());
            });
        }
    });

    $(document).on("click", "#add_step", function() {
        var current_count = parseInt($('#steps_count').val());
        var step_count = parseInt($('#steps_count').val()) + parseInt(1);

        $.ajax({
            url: "/campaigns/{{ $campaign->uid }}/add_step",
            data: {
                step_number: step_count,
                '_token': "{{ csrf_token() }}"
            },
            method: "post",
            success: function(obj) {
                $("#main_step_" + current_count).after(obj.html);
                $("#footer_step_" + current_count).show().removeClass('hide');
                $("#main_step_" + step_count).trigger('click');
            }
        })
        console.log(current_count);
        if (step_count > 1) {
            $(".delete_steps").show();
        }
        $('#steps_count').val(step_count)
    })

    $(document).on('click', '.delete_steps', function() {
        var step = $(this).attr('data-step');
        var id = $(this).attr('data-id');
        $('#delete_steps').attr('data-step', step)
        $('#delete_steps').attr('data-id', id)
        $('#stepDeleteModal').modal('show');
    })

    $(document).on('click', '#delete_steps', function() {
        var step = $(this).attr('data-step');
        var id = $(this).attr('data-id');
        var count = 0;
        $.ajax({
            url: "/campaigns/{{ $campaign->uid }}/delete_step",
            data: {
                step_number: step,
                id: id,
                '_token': "{{ csrf_token() }}"
            },
            method: "post",
            success: function(obj) {
                $("#main_step_" + step).remove();
                $('.step_card').each(function(i, val) {
                    count = i + 1
                    var step = $(this).attr('data-step');
                    $('#step_name_' + step).html('Step ' + count).attr('id', 'step_name_' +
                        count);
                    $('#delete_step_' + step).attr('id', 'delete_step_' + count).attr(
                        'data-step', count);
                    $(this).attr('data-step', count).attr('id', 'main_step_' + count);
                    $('#footer_step_' + step).attr('id', 'footer_step_' + count).attr(
                        'data-step', count);
                })

                if (count == $('.step_card').length) {
                    console.log($('.step_card').length, count, '#footer_step_' + count)
                    $('#footer_step_' + count).addClass('hide');
                }
                if (count == 1) {
                    $(".delete_steps").hide();
                }
                $('#steps_count').val(count)

                $("#main_step_1").trigger('click');

            }
        })

        $('#stepDeleteModal').modal('hide');
    })

    $(document).on('click', '.add_variant_btn', function() {
        var step = $(this).attr('data-step');

        $.ajax({
            url: "/campaigns/{{ $campaign->uid }}/add_variant",
            data: {
                step: step,
                '_token': "{{ csrf_token() }}"
            },
            method: "post",
            success: function(obj) {
                $('.sub_variant_div_' + step).remove();
                $('#variant_div_' + step).before(obj.variants)
                $('.toggle_' + step).bootstrapToggle({
                    size: 'mini',
                    // additional settings if necessary
                });
            }
        })
    })

    $(document).on('click', '.delete_variant', function() {
        var variant = $(this).attr('data-variant');
        var step = $(this).attr('data-id');

        $.ajax({
            url: "/campaigns/{{ $campaign->uid }}/delete_variant",
            data: {
                variant: variant,
                step: step,
                '_token': "{{ csrf_token() }}"
            },
            method: "post",
            success: function(obj) {
                $('.sub_variant_div_' + step).remove();
                $('#variant_div_' + step).before(obj.variants)
                $('.toggle_' + step).bootstrapToggle({
                    size: 'mini',
                    // additional settings if necessary
                });

                $('#main_step_1').trigger('click');
            }
        })
    })

    $(document).on('change', '.update_variant_status', function() {
        var variant = $(this).attr('data-variant');
        var step = $(this).attr('data-id');

        var status = 0;
        if ($(this).is(':checked')) {
            status = 1;
        }

        $.ajax({
            url: "/campaigns/{{ $campaign->uid }}/update_variant_status",
            data: {
                variant: variant,
                step: step,
                status: status,
                '_token': "{{ csrf_token() }}"
            },
            method: "post",
            success: function(obj) {}
        })
    })

    $(document).on('click', '.sub_variant_div', function(e) {
        e.stopPropagation();
        console.log('herere')
        $('#current_active_step').val($(this).attr('data-id'));
        $('#current_active_variant').val($(this).attr('data-variant'));

        var subject = $('#subject_' + $(this).attr('data-variant')).val()
        $('.step_card').removeClass('active')
        $('.step_card_' + $(this).attr('data-id')).addClass('active');
        $('#step_subject').val(subject);
        tinymce.activeEditor.setContent($('#content_' + $(this).attr('data-variant')).val())
    })

    $(document).on('click', '.step_card', function() {
        $('.step_card').removeClass('active');
        $(this).addClass('active');
        $('#current_active_step').val($(this).attr('data-id'));
        $('#current_active_variant').val($(this).attr('data-variant'));
        console.log($('#subject_' + $(this).attr('data-variant')).val(), $(this).attr('data-variant'))
        console.log($('#content_' + $(this).attr('data-variant')).val(), $(this).attr('data-variant'))
        $('#step_subject').val($('#subject_' + $(this).attr('data-variant')).val());
        tinymce.activeEditor.setContent($('#content_' + $(this).attr('data-variant')).val())
    })

    $(document).on('keyup', '#step_subject', function() {
        var active_step = $('#current_active_step').val();
        var active_variant = $('#current_active_variant').val();

        var subject = $(this).val();

        if (subject)
            $('#variant_subject_' + active_step + '_' + active_variant).html(subject)
        else
            $('#variant_subject_' + active_step + '_' + active_variant).html(
                '&lt; <span>Empty subject</span> &gt;')

        $.ajax({
            url: "/campaigns/{{ $campaign->uid }}/update_subject",
            data: {
                step_number: active_step,
                variant: active_variant,
                subject: subject,
                '_token': "{{ csrf_token() }}"
            },
            method: "post",
            success: function(obj) {
                $('#subject_' + active_variant).val(subject)
            }
        })
    })

    function update_content(content) {
        var active_step = $('#current_active_step').val();
        var active_variant = $('#current_active_variant').val();

        $.ajax({
            url: "/campaigns/{{ $campaign->uid }}/update_content",
            data: {
                step_number: active_step,
                variant: active_variant,
                content: content,
                '_token': "{{ csrf_token() }}"
            },
            method: "post",
            success: function(obj) {
                $('#content_' + active_variant).val(content)
            }
        })
    }

    $(document).on('keyup', '.wait_for', function() {
        var active_step = $('#current_active_step').val();
        var active_variant = $('#current_active_variant').val();

        var wait_for = $(this).val();
        $.ajax({
            url: "/campaigns/{{ $campaign->uid }}/wait_for",
            data: {
                step_number: active_step,
                variant: active_variant,
                next_step_wait_time: wait_for,
                '_token': "{{ csrf_token() }}"
            },
            method: "post",
            success: function(obj) {}
        })
    })
    window.onload = function() {
        if ($('.theme-default ').hasClass('mode-dark')) {
            let frameElement = document.getElementById("tiny_ifr");
            let doc = frameElement.contentDocument;
            doc.body.innerHTML = doc.body.innerHTML + '<style>body {color:#fff;}</style>';
        }
    }

    $(document).on('click', '.but-change-theme-mode', function() {
        update_color();
    })

    function update_color() {
        let frameElement = document.getElementById("tiny_ifr");
        let doc = frameElement.contentDocument;
        if ($('.theme-default ').hasClass('mode-dark')) {
            doc.body.innerHTML = doc.body.innerHTML + '<style>body {color:#fff;}</style>';
        } else {
            doc.body.innerHTML = doc.body.innerHTML + '<style>body {color:#000;}</style>';
        }
    }

    // condition row append
    var len = 0;

    function addRow(form) {
        $('#stepSettingModal').addClass('show');
        $('#apply_condition').removeClass('d-none');
        len++;
        console.log(len);
        $("#dataAdd").append('<div id="rowNum_' + len + '" class="condition_div shadow-sm px-2 py-2 mb-3">\n\
                            <div class="form-group mb-0 form_custom py-3">\n\
                                <label for="">If a lead</label>\n\
                                <select name="condition[]" class="form-select shadow-none ms-3" id="">\n\
                                    <option value="opens_email">📖 Opens this email</option>\n\
                                </select>\n\
                            </div>\n\
                            <div class="form-group mb-0 form_custom py-3">\n\
                                <label for="">Then</label>\n\
                                <select name="condition_value[]" class="form-select shadow-none ms-3" id="then_select_' + len + '" onchange="addWaitInput(' + len + ');">\n\
                                    <option value="skip_wait_time">⏭️ skip wait time before next step</option>\n\
                                    <option value="change_wait_time">🕖 change wait time before next step</option>\n\
                                </select>\n\
                            </div>\n\
                            <div class="d-none form-group form_custom" id="wait_input_' + len + '">\n\
                                <label for="">Wait <input type="number" class="form-control number_input mx-3"\n\
                                        name="wait_time[]"><span>hrs</span></label>\n\
                            </div>\n\
                            <div class="d-flex justify-content-end w-100" id="deleteCondition">\n\
                                <button class="add_condition btn btn-default bg-none text-danger remove_style"\n\
                                    type="button">\n\
                                    <span class="text-danger" onclick="removeRow(' + len + ');"> Remove</span>\n\
                                </button>\n\
                            </div>\n\
                        </div>');


    };

    function removeRow(len, id = '') {

        if (id) {
            $.ajax({
                url: "/campaigns/{{ $campaign->uid }}/remove_settings",
                method: "post",
                data: {
                    id: id,
                    '_token': "{{ csrf_token() }}"
                },
                success: function() {
                    $('#rowNum_' + len).remove();
                }
            })
        } else {
            $('#rowNum_' + len).remove();
        }
    }
    $(document).ready(function() {
        $("#stepSettingModal").on("hidden.bs.modal", function() {
            $("#dataAdd").html("");
            $('#apply_condition').addClass('d-none');
        });
    });

    function addWaitInput(len) {
        var value = $('#then_select_' + len).val();
        if (value == 'change_wait_time') {
            $('#wait_input_' + len).removeClass('d-none');
        }
    }

    $(document).on('click', '.step_settings', function() {
        var step_id = $(this).attr('data-id');
        var count = $(this).attr('data-count')
        var step_subject = $('#step_name_' + count).html()
        var next_step_wait_time = $('#next_step_wait_time_' + step_id).val();

        $('#step_id_modal').val(step_id)
        $("#settings_next_step_wait_time").val(next_step_wait_time)
        $('#step_subject_span').html(step_subject)

        $.ajax({
            url: "/campaigns/{{ $campaign->uid }}/get_settings",
            method: "get",
            data: {
                step_id: step_id
            },
            success: function(obj) {
                len = obj.count;
                $("#dataAdd").append(obj.conditions)
            }
        })

        $('#stepSettingModal').modal('show')
    })



    $(document).on('click', '#apply_condition', function() {
        $.ajax({
            url: "/campaigns/{{ $campaign->uid }}/save_settings",
            method: "post",
            data: $('#settings_modal').serialize(),
            success: function() {
                $('#setting_saved').show()

                setTimeout(() => {
                    $('#setting_saved').hide()
                }, 3000);
            }
        })
    })

    $(document).on('click', '.insert_tag_button', function() {
        var tag = $(this).attr('data-tag-name');

        tinymce.activeEditor.execCommand('mceInsertContent', false, tag);

        update_content(tinyMCE.activeEditor.getContent());
        console.log('the content ', tinyMCE.activeEditor.getContent());
    })
</script>


@endsection