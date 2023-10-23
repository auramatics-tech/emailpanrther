@extends('layouts.popup.large')

@section('bar-title')
    {{ trans('messages.subscriber_import') }}
@endsection

@section('content')
    <!-- Dropzone -->
	<script type="text/javascript" src="{{ URL::asset('core/dropzone/dropzone.js') }}"></script>
	<link href="{{ URL::asset('core/dropzone/dropzone.css') }}" rel="stylesheet" type="text/css">

	<div class="popup-wizard">

        @include('subscribers.import2._sidebar', ['step' => 'mapping'])

        <div class="wizard-content">
            <p>{!! trans('messages.subscriber.import.mapping.wording', [
                'link' => url('files/csv_import_example.csv')
            ]) !!}</p>   

            <ul class="import-mapping mb-4">
                @foreach($header as $key => $column)
                    <li class="">
                        <input type="hidden" name="input" value="{{ $column }}" class="field-option-radio" />

                        <div class="d-flex align-items-center">
                            <div class="field-checkbox">
                                <label>
                                    <input  type="checkbox" name="field_{{ $key }}" value="true" class="styled select-field">
                                </label>
                            </div>
                            <label class="m-0 field-name mr-auto">{{ $column }}</label>
                            <div class="field-actions">
                                <span class="material-symbols-rounded toogle-icon" style="display: none">keyboard_arrow_up</span>

                                <div class="quick-info exist" style="display:none">
                                    <div class="d-flex align-items-center">
                                        <span class="mr-2 text-nowrap">{{ trans('messages.subscriber.import.associate_with') }}</span>
                                        <select class="select" style="max-width: 100px;">
                                            @foreach ($list->fields as $field)
                                                <option value="{{ $field->tag }}">{{ $field->tag }}</option>
                                            @endforeach
                                            <option value="more">{{ trans('messages.subscriber.import.more_options') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="quick-info create" style="display:none">
                                    <div class="d-flex align-items-center">
                                        <span class="mr-2 text-nowrap">{{ trans('messages.subscriber.import.create') }}</span>
                                        <select class="select " style="max-width: 100px;">
                                            <option value="" data-value="create-field"></option>
                                            <option value="more">{{ trans('messages.subscriber.import.more_options') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="field-options">
                            <div class="radio-group">
                                <div class="field-option d-flex">
                                    <input type="radio" name="field_{{ $key }}[option]" value="true" class="field-option-radio" />
                                    <div class="option-intro">
                                        <label>{{ trans('messages.subscriber.import.associate_exist') }}</label>
                                        <p class="text-muted mb-4 option-empty">{{ trans('messages.subscriber.import.associate_exist.intro') }}</p>
                                        <p class="text-muted mb-4 option-done" style="display: none">
                                            {!! trans('messages.subscriber.import.selected_field.summary') !!}                                            
                                        </p>

                                        <div class="option-update">
                                            <div class="d-flex align-items-center mb-3">
                                                <select class="select data-associated-to" style="max-width: 100px;">
                                                    <option value="">{{ trans('messages.subscriber.import.select_field') }}</option>
                                                    @foreach ($list->fields as $field)
                                                        <option value="{{ $field->tag }}">{{ $field->tag }}</option>
                                                    @endforeach
                                                </select>
                                                <a href="javascript:;" class="text-nowrap ml-4 done-button" style="text-decoration: underline">
                                                    {{ trans('messages.subscriber.import.ok_i_done') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="field-option d-flex">
                                    <input type="radio" name="field_{{ $key }}[option]" value="true" class="field-option-radio" />
                                    <div class="option-intro">
                                        <label>{{ trans('messages.subscriber.import.create_new_field') }}</label>
                                        <p class="text-muted mb-4 option-empty">{{ trans('messages.subscriber.import.create_new_field.intro') }}</p>
                                        <p class="text-muted mb-4 option-done" style="display: none">
                                            {!! trans('messages.subscriber.import.create_field.summary') !!}
                                        </p>

                                        <div class="option-update mb-4">
                                            <div class="small mb-1">{{ trans('messages.subscriber.import.field_name') }}</div>
                                            <div><input type="text" name="new_field" class="form-control mb-3 data-create" /></div>
                                            <div class="small mb-1">{{ trans('messages.subscriber.import.data_type') }}</div>
                                            <div class="d-flex align-items-center mb-3">
                                                <select class="select data-type" style="max-width: 100px;">
                                                    <option value="text">{{ trans('messages.text') }}</option>
                                                    <option value="number">{{ trans('messages.number') }}</option>
                                                    <option value="date">{{ trans('messages.date') }}</option>
                                                    <option value="datetime">{{ trans('messages.datetime') }}</option>
                                                    <option value="textarea">{{ trans('messages.textarea') }}</option>
                                                </select>
                                                <a href="javascript:;" class="text-nowrap ml-4 done-button" style="text-decoration: underline">
                                                    {{ trans('messages.subscriber.import.ok_i_done') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="field-option d-flex">
                                    <input type="radio" name="field_{{ $key }}[option]" value="true" class="field-option-radio" />
                                    <div class="option-intro">
                                        <label>{{ trans('messages.subscriber.import.skip_field') }}</label>
                                        <p class="text-muted mb-0">{{ trans('messages.subscriber.import.skip_field.intro') }}</p>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <button class="btn btn-mc_primary mb-4 field-save-button">{{ trans('messages.save') }}</button>
                        </div>
                    </li>
                @endforeach
            </ul>

            <a href="{{ action('SubscriberController@import2Run', $list->uid) }}"
                class="btn btn-mc_primary bg-teal-800 mt-4 start-button"
            >
                {{ trans('messages.subscriber.import.start') }}
            </a>
        </div>
    </div>

    <script>
        $('.wizard-link').click(function(e){
            e.preventDefault();

            var url = $(this).attr('href');
            wizard.load(url);
        });

        var mapping = new Mapping();
    </script>
@endsection