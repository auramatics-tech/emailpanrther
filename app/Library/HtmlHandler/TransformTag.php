<?php

namespace Acelle\Library\HtmlHandler;

use League\Pipeline\StageInterface;
use Acelle\Library\StringHelper;
use Log;

class TransformTag implements StageInterface
{
    public $campaign;
    public $subscriber;
    public $msgId;
    public $server;

    // Campaign or email
    public function __construct($campaign, $subscriber, $msgId, $server = null)
    {
        $this->campaign = $campaign;
        $this->subscriber = $subscriber;
        $this->msgId = $msgId;
        $this->server = $server;
    }
    public function __invoke($html)
    {
        // DEPRECATED
        if (!is_null($this->server) && $this->server->isElasticEmailServer()) {
            $html = $this->server->addUnsubscribeUrl($html);
        }

        $tags = array(
            'CAMPAIGN_NAME' => $this->campaign->name,
            'CAMPAIGN_UID' => $this->campaign->uid,
            'CAMPAIGN_SUBJECT' => $this->campaign->subject,
            'CAMPAIGN_FROM_EMAIL' => $this->campaign->from_email,
            'CAMPAIGN_FROM_NAME' => $this->campaign->from_name,
            'CAMPAIGN_REPLY_TO' => $this->campaign->reply_to,
            'CURRENT_YEAR' => date('Y'),
            'CURRENT_MONTH' => date('m'),
            'CURRENT_DAY' => date('d'),
        );

        // Use in case $subscriber or $msgId is null
        $sampleLink = $this->campaign->makeSampleLink();

        # Subscriber specific
        if (is_null($this->subscriber) || $this->campaign->isStdClassSubscriber($this->subscriber)) {
            $tags['UNSUBSCRIBE_URL'] = $sampleLink;
            $tags['UPDATE_PROFILE_URL'] = $sampleLink;
            $tags['WEB_VIEW_URL'] = $sampleLink;
            $tags['SUBSCRIBER_UID'] = '%UID%';

            $tags['LIST_UID'] = '%LIST-UID%';
            $tags['LIST_NAME'] = '%LIST-NAME%';
            $tags['LIST_FROM_NAME'] = '%LIST-FROM-NAME%';
            $tags['LIST_FROM_EMAIL'] = '%LIST-FROM-EMAIL%';

            // Subscriber custom fields, including email
            $sample = '%PERSONALIZED-DATA%';

            // all lists assocated with this campaign/email
            // Notice that the Email model doesn ot have mailLists association, only defaultMailList

            if (!$this->campaign->mailLists) {
                foreach ($this->campaign->defaultMailList->fields as $field) {
                    $tags['SUBSCRIBER_'.$field->tag] = $sample;
                    $tags[$field->tag] = $sample;
                }
            } else {
                foreach ($this->campaign->mailLists as $list) {
                    foreach ($list->fields as $field) {
                        $tags['SUBSCRIBER_'.$field->tag] = $sample;
                        $tags[$field->tag] = $sample;
                    }
                }
            }

            // Special / shortcut fields
            $tags['NAME'] = $sample;
            $tags['FULL_NAME'] = $sample;

            // Only email is "reserved", overwrite previous $sample
            $tags['SUBSCRIBER_EMAIL'] = is_null($this->subscriber) ? 'email@sample.com' : $this->subscriber->email;
            $tags['FROM_NAME'] = $this->campaign->name;
            $tags['FROM_EMAIL'] = $this->campaign->from_email;
            if(isset($this->subscriber->id) && $this->subscriber->id)
                $tags['FROM_SENT'] = $this->campaign->getLastSent($this->subscriber->id);
            else
                $tags['FROM_SENT'] = '';
        } else {
            $tags['LIST_UID'] = $this->subscriber->mailList->uid;
            $tags['LIST_NAME'] = $this->subscriber->mailList->name;
            $tags['LIST_FROM_NAME'] = $this->subscriber->mailList->from_name;
            $tags['LIST_FROM_EMAIL'] = $this->subscriber->mailList->from_email;

            $updateProfileUrl = $this->subscriber->generateUpdateProfileUrl();

            if (is_null($this->msgId)) {
                $unsubscribeUrl = $sampleLink;
                $webViewUrl = $sampleLink;
            } else {
                $unsubscribeUrl = $this->subscriber->generateUnsubscribeUrl($this->msgId,true,$this->campaign->uid);
                // Log::info($unsubscribeUrl);
                $webViewUrl = StringHelper::generateWebViewerUrl($this->msgId);
            }

            if ($this->campaign->trackingDomain) {
                $updateProfileUrl = $this->campaign->trackingDomain->buildTrackingUrl($updateProfileUrl);
                $unsubscribeUrl = $this->campaign->trackingDomain->buildTrackingUrl($unsubscribeUrl);
                $webViewUrl = $this->campaign->trackingDomain->buildTrackingUrl($webViewUrl);
            }

            $tags['UPDATE_PROFILE_URL'] = $updateProfileUrl;
            $tags['UNSUBSCRIBE_URL'] = $unsubscribeUrl;
            $tags['WEB_VIEW_URL'] = $webViewUrl;
            $tags['SUBSCRIBER_UID'] = $this->subscriber->uid;

            # Subscriber custom fields
            foreach ($this->subscriber->mailList->fields as $field) {
                $tags['SUBSCRIBER_'.$field->tag] = $this->subscriber->getValueByField($field);
                $tags[$field->tag] = $this->subscriber->getValueByField($field);
            }

            // Special / shortcut fields
            $tags['NAME'] = $this->subscriber->getFullName();
            $tags['FULL_NAME'] = $this->subscriber->getFullName();
            $tags['FROM_NAME'] = $this->campaign->name;
            $tags['FROM_EMAIL'] = $this->campaign->from_email;
            $tags['FROM_SENT'] = $this->campaign->getLastSent($this->subscriber->id);
        }

        // Actually transform the message
        foreach ($tags as $tag => $value) {
            $html = str_replace('{'.$tag.'}', $value, $html);
        }

        return $html;
    }
}
