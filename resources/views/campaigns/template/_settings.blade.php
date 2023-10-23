@if(count($conditions))
@foreach($conditions as $key => $condition)
<div id="rowNum_{{ $key }}" class="condition_div shadow-sm pb-3 py-1 mb-3">
    <div class="form-group mb-0 form_custom py-3">
        <label for="">If a lead</label>
        <select name="condition[]" class="form-select shadow-none ms-3" id="">
            <option value="opens_email">📖 Opens this email</option>
        </select>
    </div>
    <div class="form-group mb-0 form_custom py-3">
        <label for="">Then</label>
        <select name="condition_value[]" class="form-select shadow-none ms-3" id="then_select_{{ $key }}" onchange="addWaitInput('{{ $key }}');">
            <option @if($condition->condition_purpose == "skip_wait_time") selected @endif value="skip_wait_time">⏭️ skip wait time before next step</option>
            <option @if($condition->condition_purpose == "change_wait_time") selected @endif value="change_wait_time">🕖 change wait time before next step</option>
        </select>
    </div>
    <div class="@if($condition->condition_purpose != 'change_wait_time') d-none @endif form-group form_custom" id="wait_input_{{ $key }}">
        <label for="">Wait <input type="number" class="form-control number_input mx-3" value="{{ $condition->wait_time }}" name="wait_time[]"><span>days</span></label>
    </div>
    <div class="d-flex justify-content-end w-100" id="deleteCondition">
        <button class="add_condition btn btn-default bg-none text-danger remove_style" type="button">
            <span class="text-danger" onclick="removeRow('{{ $key }}','{{ $condition->id }}');"> Remove</span>
        </button>
    </div>
</div>
@endforeach
@endif