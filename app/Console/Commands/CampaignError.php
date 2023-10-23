<?php

namespace Acelle\Console\Commands;

use Acelle\Model\Campaign;
use Acelle\Model\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Acelle\Model\SendingServer;
use Acelle\Model\TrackingDomain;
use Acelle\Model\TrackingLog;
use DB;
use Log;

class CampaignError extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign:error';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'checks every hour and inform on slack if any campaign mails in hr is less according to the sending server speed';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $customer = Customer::find(1);
        $campaigns = Campaign::where("status", "error")->get();

        if (count($campaigns)) {
            foreach ($campaigns as $campaign) {
                $this->info('Error '.$campaign->name);
                $campaign->log('error', $customer);

                $campaign->resume();

                // Log
                $campaign->log('restarted', $customer);
            }
        }
    }
}
