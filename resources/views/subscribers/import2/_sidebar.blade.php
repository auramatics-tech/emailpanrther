<div class="wizard-sidebar">
    <ul class="wizard-steps">
        <li>
            <a href="{{ action('SubscriberController@import2Wizard', $list->uid) }}" class="w-upload wizard-link">
                <span class="material-symbols-rounded">
                    upload_file
                    </span>
                <span>
                    <label>{{ trans('messages.subscriber.import.upload_csv_file') }}</label>
                    <p>{{ trans('messages.subscriber.import.upload_csv_file.intro') }}</p>
                </span>
            </a>
        </li>
        <li>
            <a href="{{ action('SubscriberController@import2Mapping', $list->uid) }}" class="w-mapping wizard-link">
                <span class="material-symbols-rounded">
                    key_visualizer
                    </span>
                    
                <span>
                    <label>{{ trans('messages.subscriber.import.mapping') }}</label>
                    <p>{{ trans('messages.subscriber.import.mapping.intro') }}</p>
                </span>
            </a>
        </li>
        <li>
            <a href="{{ action('SubscriberController@import2Run', $list->uid) }}" class="w-review wizard-link">
                <span class="material-symbols-rounded">
                    checklist
                    </span>
                <span>
                    <label>{{ trans('messages.subscriber.import.review_go') }}</label>
                    <p>{{ trans('messages.subscriber.import.review_go.intro') }}</p>
                </span>
            </a>
        </li>
    </ul>
</div>

<script>
    $(".w-{{ $step }}").addClass('current');
</script>