@extends('layouts.popup.large')

@section('bar-title')
    {{ trans('messages.subscriber_import') }}
@endsection

@section('content')
	<div class="popup-wizard">
        @include('subscribers.import2._sidebar', ['step' => 'review'])
        
        <div class="wizard-content">
            <p>{!! trans('messages.subscriber.import.running.wording', [
                'link' => url('files/csv_import_example.csv')
            ]) !!}</p>   

            <div class="content-group-sm mt-20">
                <div class="pull-right text-teal-800 text-semibold">
                    <span class="text-muted progress-xxs">2/10000</span>
                    &nbsp;&nbsp;&nbsp;20%
                </div>
                <span class="text-semibold mb-5 mt-0">Importing...</span>
                <div class="progress mt-2">
                    <div class="progress-bar progress-bar-striped bg-info" style="width: 20%">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $('.wizard-link').click(function(e){
            e.preventDefault();

            var url = $(this).attr('href');
            wizard.load(url);
        });
    </script>
@endsection