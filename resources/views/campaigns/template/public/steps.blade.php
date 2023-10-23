<div data-variant="{{ $step->variants[0]->id }}" data-id="{{ $step->id }}" id="main_step_{{ $step->step_number }}" class="card step_card mb-3 step_card_{{ $step->id }} @if($key == 0) active @endif" data-step="{{ $step->step_number }}">
    <div class="card-header bg-none d-flex align-items-center justify-content-between py-3">
        <div class="header_txt">
            <span class="material-symbols-rounded fs-5 me-2 fw-bold">mail</span>
            <span class="step_counts" id="step_name_{{ $step->step_number }}">Step {{ $step->step_number }}</span>
        </div>
        <div>
            <button data-count="{{ $step->step_number }}" data-id="{{ $step->id }}" id="settings_{{ $step->id }}" type="button" class="border-0 bg-none step_settings">
                <span class="material-symbols-rounded fs-5 fw-bold">settings</span>
            </button>
            <button data-id="{{ $step->id }}" id="delete_step_{{ $step->step_number }}" type="button" class="border-0 bg-none delete_steps" data-step="{{ $step->step_number }}" @if(count($campaign->stepsTemp) == 1) style="display:none;" @endif>
                <span class="material-symbols-rounded fs-5 fw-bold">delete</span>
            </button>
        </div>
    </div>
    <div class="card-body bg-none" id="card_body_{{ $step->step_number }}">
        <div class="old_variants_{{ $step->id }}">
            @include('campaigns.template.public.variants')
        </div>
        <div class="w-100 text-center mt-4" id="variant_div_{{ $step->id }}">
            <button data-variants="{{ $step->variant }}" data-step="{{ $step->id }}" class="add_variant_btn border-0 bg-none mx-auto"> <span class="material-symbols-rounded fs-5 me-1 fw-bold">add</span> Add variant</button>
        </div>
    </div>
    <div class="card-footer bg-none d-flex gap-3 align-items-center justify-content-center py-3" @if(count($campaign->stepsTemp) == 1 || count($campaign->stepsTemp) == ($key+1)) style="display:none !important" @endif id="footer_step_{{ $step->step_number }}">
        <span>wait for</span>
        <input type="number" name="number" id="next_step_wait_time_{{ $step->id }}" class="form-control number_input wait_for" placeholder="1" value="{{ $step->next_step_wait_time }}">
        <span>days, then</span>
    </div>
</div>