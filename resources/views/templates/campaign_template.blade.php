<!DOCTYPE html>
<html lang="en">

<head>
    @include('layouts.core._head')

    @include('layouts.core._script_vars')
    <style>
        .row {
            margin-left: -20px;
            margin-right: -20px;
        }

        .error {
            color: red
        }

        .btn-secondary {
            background-color: red !important;
            border-color: red !important;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('core/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('core/css/custom_template.css') }}">
    <script type="text/javascript" src="{{ URL::asset('core/tinymce/tinymce.min.js') }}"></script>
    <script src="https://cdn.tiny.cloud/1/dk78ysfqverbufn0z00d1195sqyep60xygf4zefi08wkgtne/tinymce/6/tinymce.min.js"></script>

</head>

<body>
    <!-- Page container -->
    <div class="page-container login-container" style="padding: 20px;">
        <!-- Page content -->
        <div class="page-content">

            <div class="row" bis_skin_checked="1">
                <div class="col-sm-12 col-md-12 col-12" bis_skin_checked="1">
                    <h4 class="text-semibold mt-0 mb-4 fw-600 fs-5" style="color:black;">Editable Email Template</h4>
                    <div class="row main_fix_container mb-4">
                        <div class="col-md-auto width-left h-100">
                            <div class="all_steps">
                                <input type="hidden" id="steps_count" value="{{ count($campaign->stepsTemp) }}">
                                @if (count($campaign->stepsTemp))
                                @foreach ($campaign->stepsTemp as $key => $step)
                                @include('campaigns.template.public.steps')
                                @endforeach
                                @endif
                                <div class="mb-0">
                                    <button class="btn btn-primary w-100" id="add_step">Add step</button>
                                </div>
                            </div>
                        </div>
                        @include('campaigns.template.public.step_template')
                    </div>
                </div>
            </div>
        </div>

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


        <div class="modal fade" id="updateTemplateModal" tabindex="-1" aria-labelledby="updateTemplateModal" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content py-4">
                    <div class="modal-header border-0 justify-content-center">
                        <h5 class="modal-title" id="stepDeleteModalLabel">Are you sure you want to update the live campaign?</h5>
                    </div>
                    <div class="modal-foote border-0 d-flex justify-content-center align-items-center p-3">
                        <button data-step="" type="button" class="btn btn-danger me-3" id="final_update">Update</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="updatingModal" tabindex="-1" aria-labelledby="updatingModal" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content py-4">
                    <div class="modal-header border-0 justify-content-center">
                        <h5 class="modal-title" id="updatingModalLabel">Updating please wait...</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModal" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content py-4">
                    <div class="modal-header border-0 justify-content-center">
                        <h5 class="modal-title" id="errorModalLabel"></h5>
                    </div>
                    <div class="modal-foote border-0 d-flex justify-content-center align-items-center p-3">
                        <button data-step="" type="button" class="btn btn-danger me-3" id="close_template">Ok</button>
                    </div>
                </div>
            </div>
        </div>

        @include('campaigns.template.public.step_settings')

        <!-- modal end -->
        <hr style="color: white; background-color:white">
        <a href="javascript:void(0);" class="btn btn-secondary save_template_data">
            Save & Update
        </a>
        <script>
            document.addEventListener('focusin', (e) => {
                if (e.target.closest(".tox-tinymce, .tox-tinymce-aux, .moxman-window, .tam-assetmanager-root") !==
                    null) {
                    e.stopImmediatePropagation();
                }
            });
            
            var $focused = 'subject';
            $(document).on('click','.your_subject',function(){
                $focused = 'subject';
            })
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
                        $focused = 'content';
                        update_content(ed.getContent());
                        console.log('the content ', ed.getContent());
                    });
                    ed.on('click', function(e) {
                        $focused = 'content';
                    });
                    ed.on('change', function(e) {
                        $focused = 'content';
                        update_content(ed.getContent());
                        console.log('the content ', ed.getContent());
                    });
                }
            });

            $(function() {
                // Click to insert tag
                $(document).on("click", ".insert_tag_button", function() {
                    console.log($focused)
                    var tag = $(this).attr("data-tag-name");
                    console.log(tag)
                    if($focused == 'subject'){
                        $('.your_subject').val($('.your_subject').val()+' '+tag)
                        $('#step_subject').trigger('keyup');
                    }else{
                        tinymce.activeEditor.execCommand('mceInsertContent', false, ' '+tag);
                    }

                });
            });

            $(document).on("click", "#add_step", function() {
                var current_count = parseInt($('#steps_count').val());
                var step_count = parseInt($('#steps_count').val()) + parseInt(1);

                $.ajax({
                    url: "/api/campaign-template/{{ $campaign->uid }}/add_step",
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
                    url: "/api/campaign-template/{{ $campaign->uid }}/delete_step",
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
                    url: "/api/campaign-template/{{ $campaign->uid }}/add_variant",
                    data: {
                        step: step,
                        '_token': "{{ csrf_token() }}"
                    },
                    method: "post",
                    success: function(obj) {
                        $('.sub_variant_div_' + step).remove();
                        $('.old_variants_'+step).remove();
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
                    url: "/api/campaign-template/{{ $campaign->uid }}/delete_variant",
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
                    url: "/api/campaign-template/{{ $campaign->uid }}/update_variant_status",
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
                    url: "/api/campaign-template/{{ $campaign->uid }}/update_subject",
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
                    url: "/api/campaign-template/{{ $campaign->uid }}/update_content",
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
                    url: "/api/campaign-template/{{ $campaign->uid }}/wait_for",
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

            // condition row append
            var len = 0;

            function addRow(form) {
                $('#stepSettingModal').addClass('show');
                $('#apply_condition').removeClass('d-none');
                len++;
                console.log(len);
                $("#dataAdd").append('<div id="rowNum_' + len + '" class="condition_div shadow-sm pb-3 py-1 mb-3">\n\
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
                                    name="wait_time[]"><span>days</span></label>\n\
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
                        url: "/api/campaign-template/{{ $campaign->uid }}/remove_settings",
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
                    url: "/api/campaign-template/{{ $campaign->uid }}/get_settings",
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
                    url: "/api/campaign-template/{{ $campaign->uid }}/save_settings",
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

            $(document).on('click', '.save_template_data', function() {
                $('#updateTemplateModal').modal('show');
            })

            $(document).on('click', '#final_update', function() {
                $('#updateTemplateModal').modal('hide');
                $('#updatingModal').modal('show');
                $.ajax({
                    url: "/api/campaign-template/{{ $campaign->uid }}/update_template",
                    method: "post",
                    data: {
                        '_token': "{{ csrf_token() }}"
                    },
                    success: function(obj) {
                        $('#updatingModal').modal('hide');
                        if (!obj.status) {
                            console.log('jere');
                            $('#errorModalLabel').html(obj.message);
                            $('#updateTemplateModal').modal('hide');
                            $('#updatingModal').modal('hide');
                            $('#errorModal').modal('show');
                        }else{
                            
                            $('#errorModalLabel').html(obj.message);
                            $('#updateTemplateModal').modal('hide');
                            $('#updatingModal').modal('hide');
                            $('#errorModal').modal('show');
                        }
                        setTimeout(() => {
                        $('#updatingModal').modal('hide');
                        }, 500);
                    }
                })
            })

            $(document).on('click','#close_template',function(){
                $('#errorModal').modal('hide');
            })
        </script>

</body>

</html>