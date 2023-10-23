<div class="col-md width-right">
    <input type="hidden" value="{{ $campaign->steps[0]->id }}" id="current_active_step">
    <input type="hidden" value="{{ $campaign->steps[0]->variants[0]->id }}" id="current_active_variant">
    <!-- <p>Please add {UNSUBSCRIBE_URL} in template for unsubscibe url</p> -->
    <form action="" class="form_data_template h-100">
        <div class="form_header d-flex align-items-center">
            <div class="d-flex align-items-center w-100 py-3 ps-3 px-2">
                <span class="me-1">Subject</span>
                <input value="{{ $campaign->steps[0]->variants[0]->subject }}" type="text" name="subject" class="form-control w-100 border-0 your_subject" placeholder="Your subject" autofocus id="step_subject">
            </div>
            <div class="d-flex align-items-center ms-auto pe-3">
                <!-- <div class="dropdown mx-3">
                    <button class="btn btn-default dropdown-toggle" type="button" id="insertVariableButton1" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="material-symbols-rounded fs-5 fw-bold text_flash">flash_on</span>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="insertVariableButton1">
                        <li class="dropdown-item p-3">No variables found. Add leads with variables.</li>
                    </ul>
                </div> -->
                <div class="d-flex">
                    <!-- <div>
                        <button type="button" class="btn btn-primary">Save</button>
                    </div> -->
                    <div class="dropdown dropdownSave ">
                        <button class="btn btn-primary dropdown-toggle px-1" type="button" id="saveMnuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end my_list p-2" aria-labelledby="saveMnuButton1">
                            @php
                                $tags = Acelle\Model\Template::tags($campaign->defaultMailList);
                            @endphp
                            @if (count($tags) > 0)
                            @foreach($tags as $tag)
                            @if (!$tag["required"])
                            <li class="p-2">
                                <a data-popup="tooltip" title='{{ trans('messages.click_to_insert_tag') }}' href="javascript:;" style="padding: 3px 7px !important;
    								font-weight: normal;" draggable="false" class="btn btn-secondary text-semibold btn-xs insert_tag_button" data-tag-name="{{ "{".$tag["name"]."}" }}">
                                    {{ $tag["name"] }}
                                </a>
                            </li>
                            @endif
                            @endforeach
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <textarea class="variant_content" id="tiny" placeholder="Type here...">{{ $campaign->steps[0]->variants[0]->content }}</textarea>
    </form>
</div>