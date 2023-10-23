@if(count($step->variants) > 1)
@foreach($step->variants as $variants)
@php
$alphabets = range('A', 'Z');
$variant_number = $variants->variant -1;
@endphp
<div class="sub_card current sub_variant_div sub_variant_div_{{ $step->id }}" data-variant="{{ $variants->id }}" data-id="{{ $step->id }}">
    <input type="hidden" id="subject_{{ $variants->id }}" value="{{ $variants->subject }}">
    <input type="hidden" id="content_{{ $variants->id }}" value="{{ $variants->content }}">
    <div class="d-flex align-items-start me-2 width_for_div">
        <div><div class="me-2 child">{{ $alphabets[$variant_number] }}</div></div>
        <span class="width_for_text" id="variant_subject_{{ $step->id }}_{{ $variants->id }}">{!! ($variants->subject) ? $variants->subject : '&lt; <span>Empty subject</span> &gt;' !!}</span>
    </div>
    <div class="d-flex align-items-center">
        <input data-variant="{{ $variants->id }}" data-id="{{ $step->id }}" @if($variants->status == 1) checked @endif type="checkbox" data-toggle="toggle" class="me-2 update_variant_status toggle_{{ $step->id }}">
        <span data-variant="{{ $variants->id }}" data-id="{{ $step->id }}" class="material-symbols-rounded fs-5 fw-bold text-danger delete_variant" style="cursor:pointer">delete_forever</span>
    </div>
</div>
@endforeach
@elseif(count($step->variants) == 1)
<input type="hidden" id="subject_{{ $step->variants[0]->id }}" value="{{ $step->variants[0]->subject }}">
<input type="hidden" id="content_{{ $step->variants[0]->id }}" value="{{ $step->variants[0]->content }}">
<div id="variant_subject_{{ $step->id }}_{{ $step->variants[0]->id }}">
    @if(isset($step->variants[0]->subject) && $step->variants[0]->subject)
    {{ $step->variants[0]->subject }}
    @else
    &lt; <span>Empty subject</span> &gt;
    @endif
</div>
@endif