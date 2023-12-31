<?php

namespace Acelle\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Acelle\Model\Campaign;
use Acelle\Model\Subscriber;
use Acelle\Model\Setting;
use Illuminate\Support\Carbon;
use Acelle\Library\Traits\Trackable;

class LoadCampaign implements ShouldQueue
{
    use Trackable;
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 7200;
    public $failOnTimeout = true;
    public $tries = 1;
    public $maxExceptions = 1;

    protected $campaign;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Campaign $campaign)
    {
        $this->campaign = $campaign;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        // Clear any HTML cache
        $this->campaign->clearTemplateCache();

        // Last chance for campaign to setup
        // @todo Find a better place for the following method, afterSave for example
        $this->campaign->updateLinks();

        // Update status
        $this->campaign->setSending();

        // Iterating through a big list ans create job objects may cause memory leak
        // So, LoadCampaign only loads a certain numbers subscribers each time, then just finish job to release the queue listener (remember to configure the queue correctly to release after a short time)
        // When a campaign is done, it will automaticall launch a new LoadCampaign job if there are more subscribers to send
        //$loadLimit = 100 + rand(1, 9);
        $loadLimit = 2;
        $this->campaign->logger()->info(sprintf('Loading contacts to shoot (up to %s)', $loadLimit));

        // Iterate through contacts and launch sending process
        $this->campaign->prepare(function ($campaign, $subscriber, $server, $step, $variant) {
            $job = new SendMessage($campaign, $subscriber, $server, NULL, $step, $variant);
            $stopOnError = Setting::isYes('campaign.stop_on_error'); // true or false
            $job->setStopOnError($stopOnError);
            $this->batch()->add($job);
        }, $loadLimit);


        $loadLimit2 = 50;
        $this->campaign->prepare(function ($campaign, $subscriber, $server, $step, $variant) {
            $this->campaign->logger()->info(sprintf('Loading contacts to shoot extra (up to %s)', $campaign->last_hr_extra_mails));
            if ($campaign->last_hr_extra_mails) {
                $this->campaign->logger()->info(sprintf('Loading contacts to shoot extra (up to %s)', $campaign->last_hr_extra_mails));
                $job_extra = new SendMessageExtraLimit($campaign, $subscriber, $server, NULL, $step, $variant);
                $stopOnError = Setting::isYes('campaign.stop_on_error'); // true or false
                $job_extra->setStopOnError($stopOnError);
                $this->batch()->add($job_extra);
                $campaign->last_hr_extra_mails = $campaign->last_hr_extra_mails - 1;
                $campaign->save();
            }
        }, $loadLimit2);
    }
}
